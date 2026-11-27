<?php

namespace App\Services\Booking;

use App\Enums\BookingStatus;
use App\Enums\PaymentMetode;
use App\Enums\RefundReason;
use App\Enums\RefundStatus;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use App\Repositories\Contracts\BookingRepositoryInterface;
use App\Services\Notification\Contracts\NotificationDispatcherInterface;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class BookingCancellationService
{
    public function __construct(
        private readonly BookingRepositoryInterface $bookingRepository,
        private readonly NotificationDispatcherInterface $notificationDispatcher,
    ) {}

    public function cancelByClient(Booking $booking): Booking
    {
        if (! $booking->status->canBeCancelledByClient()) {
            throw ValidationException::withMessages([
                'status' => [
                    'Booking yang sudah dibayar tidak bisa dibatalkan langsung. Silakan hubungi mitra untuk refund.',
                ],
            ]);
        }

        if ($booking->payments()->whereNotNull('verified_at')->exists()) {
            throw ValidationException::withMessages([
                'status' => [
                    'Booking sudah memiliki pembayaran terverifikasi. Silakan hubungi mitra.',
                ],
            ]);
        }

        $cancelled = $this->cancel(
            booking: $booking,
            reason: 'Dibatalkan oleh client',
            actorId: $booking->user_id,
            actorRole: 'client',
        );

        $this->notifyMitraCancelledByClient($cancelled);

        return $cancelled;
    }

    public function cancelByMitra(Booking $booking, User $mitra, string $alasan): Booking
    {
        if ($booking->mitra_id !== $mitra->id) {
            throw ValidationException::withMessages([
                'booking' => ['Anda tidak memiliki akses ke booking ini.'],
            ]);
        }

        if ($booking->status === BookingStatus::COMPLETED) {
            throw ValidationException::withMessages([
                'status' => ['Booking yang sudah selesai tidak bisa dibatalkan.'],
            ]);
        }

        if ($booking->status === BookingStatus::CANCELLED) {
            throw ValidationException::withMessages([
                'status' => ['Booking sudah dibatalkan.'],
            ]);
        }

        $cancelled = DB::transaction(function () use ($booking, $mitra, $alasan): Booking {
            // Lock baris booking: cegah dua aktor membatalkan booking yang
            // sama bersamaan (masing-masing membuat Refund + Payment).
            $locked = $this->bookingRepository->findByIdForUpdate($booking->id) ?? $booking;

            if ($locked->mitra_id !== $mitra->id) {
                throw ValidationException::withMessages([
                    'booking' => ['Anda tidak memiliki akses ke booking ini.'],
                ]);
            }

            if ($locked->status === BookingStatus::COMPLETED) {
                throw ValidationException::withMessages([
                    'status' => ['Booking yang sudah selesai tidak bisa dibatalkan.'],
                ]);
            }

            if ($locked->status === BookingStatus::CANCELLED) {
                throw ValidationException::withMessages([
                    'status' => ['Booking sudah dibatalkan.'],
                ]);
            }

            $oldProofPath = $locked->payment_proof_path;
            $currentStatus = $locked->status; // Simpan status SEBELUM diubah

            // 1. Ubah status booking menjadi CANCELLED
            $updated = $this->bookingRepository->update($locked, [
                'status' => BookingStatus::CANCELLED->value,
                'catatan' => $this->mergeCatatan($locked->catatan, "Dibatalkan oleh mitra: {$alasan}"),
            ]);

            $this->deletePaymentProof($oldProofPath);

            // 2. Buat Refund jika booking sudah dibayar sebelumnya
            if (in_array($currentStatus, [BookingStatus::PAYMENT_VERIFIED, BookingStatus::CONFIRMED, BookingStatus::IN_PROGRESS])) {

                // Buat record Refund (Status: PENDING)
                Refund::create([
                    'booking_id' => $locked->id,
                    'mitra_id' => $mitra->id,
                    'amount' => $locked->total_harga,
                    'currency' => 'IDR',
                    'reason' => RefundReason::CANCELLATION,
                    'status' => RefundStatus::PENDING,
                    'initiated_by_mitra' => $mitra->id,
                    'catatan' => 'Refund otomatis karena booking dibatalkan oleh Mitra. Alasan: '.$alasan,
                ]);

                // BEST PRACTICE: Catat Payment negatif sebagai jejak pengembalian dana
                Payment::create([
                    'booking_id' => $locked->id,
                    'jumlah' => -1 * (float) $locked->total_harga,
                    'metode' => PaymentMetode::TRANSFER_MANUAL->value,
                    'verified_by' => $mitra->id,
                    'verified_at' => now(),
                    'catatan' => 'Dana dikembalikan via Refund (Dibatalkan oleh Mitra)',
                ]);
            }

            AuditLogger::log('booking.cancelled', [
                'actor_id' => $mitra->id,
                'actor_role' => 'mitra',
                'booking_id' => $updated->id,
                'booking_kode' => $updated->kode,
                'reason' => "Dibatalkan oleh mitra: {$alasan}",
                'had_payment_proof' => $oldProofPath !== null && $oldProofPath !== '',
            ]);

            return $updated;
        });

        $this->notifyClientCancelledByMitra($cancelled, $alasan);

        return $cancelled;
    }

    public function expirePendingBookings(): int
    {
        $expired = $this->bookingRepository->findExpired();
        $count = 0;

        foreach ($expired as $booking) {
            try {
                $cancelled = $this->cancelIfUnpaid(
                    booking: $booking,
                    reason: 'Auto-cancel: Melebihi batas waktu pembayaran 24 jam',
                    actorId: null,
                    actorRole: 'system',
                );

                // Null berarti booking sudah berubah status (mis. baru
                // dibayar) sebelum scheduler sempat membatalkannya.
                if ($cancelled === null) {
                    continue;
                }

                $this->notifyClientExpired($cancelled);

                $count++;
            } catch (Throwable $e) {
                Log::error('BookingCancellationService: gagal auto-cancel', [
                    'booking_id' => $booking->id,
                    'booking_kode' => $booking->kode,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $count;
    }

    private function cancel(
        Booking $booking,
        string $reason,
        ?string $actorId,
        string $actorRole,
    ): Booking {
        return DB::transaction(function () use ($booking, $reason, $actorId, $actorRole): Booking {
            $locked = $this->bookingRepository->findByIdForUpdate($booking->id) ?? $booking;

            if ($locked->status === BookingStatus::CANCELLED) {
                return $locked;
            }

            if ($locked->status === BookingStatus::COMPLETED) {
                throw ValidationException::withMessages([
                    'status' => ['Booking yang sudah selesai tidak bisa dibatalkan.'],
                ]);
            }

            return $this->applyCancellation($locked, $reason, $actorId, $actorRole);
        });
    }

    /**
     * Batalkan booking hanya bila masih menunggu pembayaran.
     *
     * Dipakai scheduler auto-expire: bila booking sudah dibayar di antara
     * waktu query dan proses cancel, pembatalan dilewati.
     */
    private function cancelIfUnpaid(
        Booking $booking,
        string $reason,
        ?string $actorId,
        string $actorRole,
    ): ?Booking {
        return DB::transaction(function () use ($booking, $reason, $actorId, $actorRole): ?Booking {
            $locked = $this->bookingRepository->findByIdForUpdate($booking->id) ?? $booking;

            if (! $locked->status->canBeCancelledByClient()) {
                return null;
            }

            return $this->applyCancellation($locked, $reason, $actorId, $actorRole);
        });
    }

    private function applyCancellation(
        Booking $booking,
        string $reason,
        ?string $actorId,
        string $actorRole,
    ): Booking {
        $oldProofPath = $booking->payment_proof_path;

        $updated = $this->bookingRepository->update($booking, [
            'status' => BookingStatus::CANCELLED->value,
            'catatan' => $this->mergeCatatan($booking->catatan, $reason),
        ]);

        $this->deletePaymentProof($oldProofPath);

        AuditLogger::log('booking.cancelled', [
            'actor_id' => $actorId,
            'actor_role' => $actorRole,
            'booking_id' => $updated->id,
            'booking_kode' => $updated->kode,
            'reason' => $reason,
            'had_payment_proof' => $oldProofPath !== null && $oldProofPath !== '',
        ]);

        return $updated;
    }

    private function deletePaymentProof(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        foreach ([BookingPaymentService::proofDisk(), 'local'] as $diskName) {
            $disk = Storage::disk($diskName);

            if ($disk->exists($path)) {
                $disk->delete($path);
            }
        }
    }

    private function mergeCatatan(?string $existing, string $new): string
    {
        $timestamp = now()->format('Y-m-d H:i');
        $line = "[{$timestamp}] {$new}";

        return $existing !== null && $existing !== ''
            ? "{$existing}\n{$line}"
            : $line;
    }

    // ==========================================
    // NOTIFICATION HELPERS
    // ==========================================

    private function notifyClientCancelledByMitra(Booking $booking, string $alasan): void
    {
        $client = $booking->client;
        if ($client === null) {
            return;
        }

        $this->notificationDispatcher->send(
            user: $client,
            tipe: 'booking_cancelled',
            judul: 'Booking Dibatalkan oleh Mitra',
            pesan: "Booking {$booking->kode} telah dibatalkan oleh mitra. Alasan: {$alasan}. Dana akan dikembalikan.",
            data: ['booking_id' => $booking->id],
        );
    }

    private function notifyMitraCancelledByClient(Booking $booking): void
    {
        $mitra = $booking->mitra;
        if ($mitra === null) {
            return;
        }

        $this->notificationDispatcher->send(
            user: $mitra,
            tipe: 'booking_cancelled',
            judul: 'Booking Dibatalkan oleh Client',
            pesan: "Client telah membatalkan booking {$booking->kode}.",
            data: ['booking_id' => $booking->id],
        );
    }

    private function notifyClientExpired(Booking $booking): void
    {
        $client = $booking->client;
        if ($client === null) {
            return;
        }

        $this->notificationDispatcher->send(
            user: $client,
            tipe: 'booking_cancelled',
            judul: 'Booking Expired',
            pesan: "Booking {$booking->kode} telah dibatalkan otomatis karena melewati batas waktu pembayaran.",
            data: ['booking_id' => $booking->id],
        );
    }
}
