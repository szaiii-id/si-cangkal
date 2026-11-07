<?php

namespace App\Services\Booking;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\User;
use App\Repositories\Contracts\BookingRepositoryInterface;
use App\Services\Notification\NotificationDispatcher;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Service pembatalan booking.
 *
 * Hasil pemecahan `BookingService` (PRD 5.7). Bertanggung jawab atas:
 * - `cancelByClient`         : client membatalkan bookingnya sendiri.
 * - `cancelByMitra`          : mitra membatalkan booking.
 * - `expirePendingBookings`  : scheduler auto-cancel booking yang expired.
 *
 * Perbaikan yang diterapkan:
 * - B-02 : file bukti transfer dihapus dari disk `local` saat booking
 *          dibatalkan, agar storage tidak bengkak.
 * - S-16 : setiap pembatalan diaudit via `AuditLogger`.
 *
 * Catatan FCM:
 * - Semua notifikasi via `NotificationDispatcher` (in-app + push).
 * - Notifikasi dikirim DI LUAR transaksi DB agar kegagalan FCM
 *   tidak me-rollback pembatalan.
 */
final class BookingCancellationService
{
    public function __construct(
        private readonly BookingRepositoryInterface $bookingRepository,
        private readonly NotificationDispatcher $notificationDispatcher,
    ) {}

    /**
     * Client membatalkan booking miliknya sendiri.
     *
     * @throws ValidationException
     */
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

        // ✅ Notifikasi ke Mitra bahwa Client membatalkan
        $this->notifyMitraCancelledByClient($cancelled);

        return $cancelled;
    }

    /**
     * Mitra membatalkan booking.
     *
     * @throws ValidationException
     */
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

        $cancelled = $this->cancel(
            booking: $booking,
            reason: "Dibatalkan oleh mitra: {$alasan}",
            actorId: $mitra->id,
            actorRole: 'mitra',
        );

        // ✅ Notifikasi ke Client bahwa Mitra membatalkan
        $this->notifyClientCancelledByMitra($cancelled, $alasan);

        return $cancelled;
    }

    /**
     * Scheduler: auto-cancel booking yang sudah melewati `expired_at`.
     *
     * @return int Jumlah booking yang berhasil di-cancel.
     */
    public function expirePendingBookings(): int
    {
        $expired = $this->bookingRepository->findExpired();
        $count = 0;

        foreach ($expired as $booking) {
            try {
                $cancelled = $this->cancel(
                    booking: $booking,
                    reason: 'Auto-cancel: Melebihi batas waktu pembayaran 24 jam',
                    actorId: null,
                    actorRole: 'system',
                );

                // ✅ Notifikasi ke Client bahwa booking expired
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

    /**
     * Inti pembatalan: update status, hapus file bukti, audit.
     * ⚠️ Hanya handle DB. Notifikasi di-handle oleh pemanggil.
     *
     * @throws \Throwable
     */
    private function cancel(
        Booking $booking,
        string $reason,
        ?int $actorId,
        string $actorRole,
    ): Booking {
        return DB::transaction(function () use ($booking, $reason, $actorId, $actorRole): Booking {
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
        });
    }

    /**
     * Hapus file bukti transfer dari disk `local` (B-02).
     */
    private function deletePaymentProof(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        $disk = Storage::disk(BookingPaymentService::PROOF_DISK);

        if ($disk->exists($path)) {
            $disk->delete($path);
        }
    }

    /**
     * Gabungkan catatan lama dengan catatan pembatalan baru.
     */
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

    /**
     * Notifikasi ke Client saat Mitra membatalkan booking.
     */
    private function notifyClientCancelledByMitra(Booking $booking, string $alasan): void
    {
        $client = $booking->client;
        if ($client === null) return;

        $this->notificationDispatcher->send(
            user: $client,
            tipe: 'booking_cancelled',
            judul: 'Booking Dibatalkan oleh Mitra',
            pesan: "Booking {$booking->kode} telah dibatalkan oleh mitra. Alasan: {$alasan}",
            data: ['booking_id' => $booking->id],
        );
    }

    /**
     * Notifikasi ke Mitra saat Client membatalkan booking.
     */
    private function notifyMitraCancelledByClient(Booking $booking): void
    {
        $mitra = $booking->mitra;
        if ($mitra === null) return;

        $this->notificationDispatcher->send(
            user: $mitra,
            tipe: 'booking_cancelled',
            judul: 'Booking Dibatalkan oleh Client',
            pesan: "Client telah membatalkan booking {$booking->kode}.",
            data: ['booking_id' => $booking->id],
        );
    }

    /**
     * Notifikasi ke Client saat booking expired (auto-cancel).
     */
    private function notifyClientExpired(Booking $booking): void
    {
        $client = $booking->client;
        if ($client === null) return;

        $this->notificationDispatcher->send(
            user: $client,
            tipe: 'booking_cancelled',
            judul: 'Booking Expired',
            pesan: "Booking {$booking->kode} telah dibatalkan otomatis karena melewati batas waktu pembayaran.",
            data: ['booking_id' => $booking->id],
        );
    }
}