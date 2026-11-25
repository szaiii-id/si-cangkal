<?php

namespace App\Services\Booking;

use App\Enums\BookingStatus;
use App\Enums\PaymentMetode;
use App\Events\BookingConfirmed;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use App\Repositories\Contracts\BookingRepositoryInterface;
use App\Services\Notification\Contracts\NotificationDispatcherInterface;
use App\Support\AuditLogger;
use App\Support\UploadStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class BookingPaymentService
{
    public const PROOF_DISK = 'local';

    public const PROOF_DIRECTORY = 'payment_proofs';

    public function __construct(
        private readonly BookingRepositoryInterface $bookingRepository,
        private readonly NotificationDispatcherInterface $notificationDispatcher,
    ) {}

    public function uploadProof(Booking $booking, UploadedFile $file): Booking
    {
        if ($booking->status !== BookingStatus::PENDING_PAYMENT) {
            throw ValidationException::withMessages([
                'booking' => ['Booking ini sudah tidak dalam status menunggu pembayaran.'],
            ]);
        }

        $uploader = auth()->user();
        $uploaderIsMitra = $uploader?->isMitra() === true;

        $updated = DB::transaction(function () use ($booking, $file): Booking {
            // Lock baris booking: cegah bukti transfer baru menimpa booking
            // yang baru saja diverifikasi/dibatalkan oleh request lain.
            $locked = $this->bookingRepository->findByIdForUpdate($booking->id) ?? $booking;

            if ($locked->status !== BookingStatus::PENDING_PAYMENT) {
                throw ValidationException::withMessages([
                    'booking' => ['Booking ini sudah tidak dalam status menunggu pembayaran.'],
                ]);
            }

            $this->deleteExistingProof($locked);

            $path = $this->storeProofFile($locked, $file);

            return $this->bookingRepository->update($locked, [
                'payment_proof_path' => $path,
                'payment_reject_reason' => null,
            ]);
        });

        // Notifikasi & audit di luar transaksi: kegagalan I/O tidak boleh
        // membatalkan bukti transfer yang sudah tersimpan.
        $this->notifyProofUploaded($updated, $uploaderIsMitra ? 'mitra' : 'client');

        AuditLogger::log('booking.payment_proof_uploaded', [
            'actor_id' => $uploader?->id,
            'actor_role' => $uploaderIsMitra ? 'mitra' : 'client',
            'booking_id' => $updated->id,
            'booking_kode' => $updated->kode,
            'path' => $updated->payment_proof_path,
            'size' => $file->getSize(),
            'mime' => $file->getMimeType(),
        ]);

        return $updated;
    }

    public function verifyPayment(Booking $booking, User $mitra): Booking
    {
        $updated = DB::transaction(function () use ($booking, $mitra): Booking {
            // Kunci baris booking agar dua request verifikasi bersamaan
            // diproses serial: hanya satu yang boleh membuat Payment dan
            // mengubah status menjadi confirmed.
            $locked = $this->bookingRepository->findByIdForUpdate($booking->id);

            if ($locked === null) {
                throw ValidationException::withMessages([
                    'booking' => ['Booking tidak ditemukan.'],
                ]);
            }

            if ($locked->mitra_id !== $mitra->id) {
                throw ValidationException::withMessages([
                    'booking' => ['Anda tidak memiliki akses ke booking ini.'],
                ]);
            }

            if ($locked->status !== BookingStatus::PENDING_PAYMENT) {
                throw ValidationException::withMessages([
                    'booking' => ['Booking ini tidak dalam status menunggu pembayaran.'],
                ]);
            }

            // Garda tambahan untuk data lama yang statusnya belum sinkron.
            if ($locked->payments()->where('jumlah', '>', 0)->exists()) {
                throw ValidationException::withMessages([
                    'booking' => ['Pembayaran booking ini sudah pernah diverifikasi.'],
                ]);
            }

            Payment::create([
                'booking_id' => $locked->id,
                'jumlah' => $locked->total_harga,
                'metode' => PaymentMetode::TRANSFER_MANUAL->value,
                'verified_by' => $mitra->id,
                'verified_at' => now(),
                'catatan' => "Diverifikasi manual oleh Mitra: {$mitra->name}",
            ]);

            return $this->bookingRepository->update($locked, [
                'status' => BookingStatus::CONFIRMED->value,
                'payment_verified_at' => now(),
            ]);
        });

        // Efek samping di luar transaksi: notifikasi in-app/FCM, audit,
        // dan event yang membuat Trip + E-Ticket.
        $this->notifyClientPaymentConfirmed($updated);

        AuditLogger::log('booking.payment_verified', [
            'actor_id' => $mitra->id,
            'actor_role' => 'mitra',
            'booking_id' => $updated->id,
            'booking_kode' => $updated->kode,
            'total_harga' => (float) $updated->total_harga,
        ]);

        try {
            BookingConfirmed::dispatch($updated->refresh(), $mitra->id);
        } catch (Throwable $e) {
            Log::error('BookingPaymentService: dispatch BookingConfirmed gagal', [
                'booking_id' => $updated->id,
                'booking_kode' => $updated->kode,
                'error' => $e->getMessage(),
            ]);
        }

        return $updated->load('payments');
    }

    public function rejectPayment(Booking $booking, User $mitra, string $reason): Booking
    {
        if ($booking->mitra_id !== $mitra->id) {
            throw ValidationException::withMessages([
                'booking' => ['Anda tidak memiliki akses ke booking ini.'],
            ]);
        }

        if ($booking->status !== BookingStatus::PENDING_PAYMENT) {
            throw ValidationException::withMessages([
                'booking' => ['Booking ini tidak dalam status menunggu pembayaran.'],
            ]);
        }

        $booking = $this->bookingRepository->update($booking, [
            'payment_reject_reason' => $reason,
        ]);

        $this->notifyClientPaymentRejected($booking, $reason);

        AuditLogger::log('booking.payment_rejected', [
            'actor_id' => $mitra->id,
            'actor_role' => 'mitra',
            'booking_id' => $booking->id,
            'booking_kode' => $booking->kode,
            'reason' => $reason,
        ]);

        return $booking;
    }

    private function deleteExistingProof(Booking $booking): void
    {
        $oldPath = $booking->payment_proof_path;

        if ($oldPath === null || $oldPath === '') {
            return;
        }

        // File lama hanya dibuang setelah transaksi berhasil di-commit.
        // Rollback tidak boleh meninggalkan DB pointing ke file yang hilang.
        UploadStorage::deleteAfterCommit(self::PROOF_DISK, $oldPath);
    }

    private function storeProofFile(Booking $booking, UploadedFile $file): string
    {
        $extension = strtolower($file->guessExtension() ?: 'jpg');
        // Suffix acak mencegah tabrakan nama saat upload ulang dalam detik
        // yang sama — penting karena file lama dihapus setelah commit.
        $filename = sprintf(
            '%s_%s_%s.%s',
            $booking->kode,
            now()->format('YmdHis'),
            Str::random(6),
            $extension,
        );

        try {
            return UploadStorage::put(
                self::PROOF_DISK,
                self::PROOF_DIRECTORY,
                $file,
                $filename,
            );
        } catch (RuntimeException) {
            throw ValidationException::withMessages([
                'payment_proof' => ['Gagal menyimpan bukti transfer. Coba lagi.'],
            ]);
        }
    }

    private function notifyProofUploaded(Booking $booking, string $uploaderRole): void
    {
        if ($uploaderRole === 'client') {
            $mitra = $booking->mitra;

            if ($mitra !== null) {
                $this->notificationDispatcher->send(
                    user: $mitra,
                    tipe: 'payment_proof_uploaded',
                    judul: 'Bukti Transfer Baru',
                    pesan: "Client telah mengunggah bukti transfer untuk booking {$booking->kode}. Silakan verifikasi.",
                    data: ['booking_id' => $booking->id],
                );
            }

            return;
        }

        $client = $booking->client;

        if ($client !== null) {
            $this->notificationDispatcher->send(
                user: $client,
                tipe: 'payment_proof_uploaded',
                judul: 'Bukti Transfer Diunggah',
                pesan: "Bukti transfer untuk booking {$booking->kode} telah diunggah oleh Mitra.",
                data: ['booking_id' => $booking->id],
            );
        }
    }

    private function notifyClientPaymentConfirmed(Booking $booking): void
    {
        $client = $booking->client;

        if ($client === null) {
            return;
        }

        $this->notificationDispatcher->send(
            user: $client,
            tipe: 'booking_confirmed',
            judul: 'Booking Dikonfirmasi',
            pesan: "Pembayaran Anda untuk booking {$booking->kode} telah diverifikasi. E-ticket sedang diproses.",
            data: ['booking_id' => $booking->id],
        );
    }

    private function notifyClientPaymentRejected(Booking $booking, string $reason): void
    {
        $client = $booking->client;

        if ($client === null) {
            return;
        }

        $this->notificationDispatcher->send(
            user: $client,
            tipe: 'payment_rejected',
            judul: 'Bukti Transfer Ditolak',
            pesan: "Bukti transfer untuk booking {$booking->kode} ditolak. Alasan: {$reason}. Silakan unggah ulang.",
            data: ['booking_id' => $booking->id],
        );
    }
}
