<?php

namespace App\Services\Booking;

use App\Enums\BookingStatus;
use App\Enums\PaymentMetode;
use App\Events\BookingConfirmed;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use App\Repositories\Contracts\BookingRepositoryInterface;
use App\Services\Notification\NotificationDispatcher;
use App\Support\AuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Service pengelolaan pembayaran manual booking.
 *
 * Hasil pemecahan `BookingService` (PRD 5.7). Bertanggung jawab atas:
 * - `uploadProof`   : unggah bukti transfer (client atau mitra).
 * - `verifyPayment` : verifikasi bukti oleh mitra -> trigger event.
 * - `rejectPayment` : tolak bukti dengan alasan.
 *
 * Perbaikan keamanan yang diterapkan di sini:
 * - S-01 : guard status `pending_payment` sebelum upload / verify / reject.
 * - S-09 : file bukti transfer disimpan di disk `local` (privat, tidak
 *          bisa diakses via `/storage/`), bukan disk `public`.
 * - B-01 : akses file bukti transfer lewat route privat ber-otorisasi
 *          (lihat {@see \App\Http\Controllers\Client\BookingController}).
 * - S-16 : setiap transisi state diaudit via `AuditLogger`.
 *
 * Catatan B-03: `verifyPayment` TIDAK lagi memanggil TripService /
 * ETicketService secara langsung. Setelah `BookingConfirmed` di-dispatch,
 * listener-lah yang bertanggung jawab membuat Trip & E-Ticket. Ini
 * mencegah N+1 yang tidak disengaja dan membuat `verifyPayment` < 200ms
 * (NF-02).
 *
 * Catatan FCM: Notifikasi via `NotificationDispatcher` mengirim
 * in-app (database) + push FCM sekaligus. Jika user tidak punya
 * `fcm_token`, push otomatis dilewati (in-app tetap jalan).
 * Jika FCM error, hanya di-log — tidak menggagalkan proses utama.
 */
final class BookingPaymentService
{
    /**
     * Disk penyimpanan bukti transfer.
     *
     * Wajib `local` (bukan `public`) agar file tidak terekspos lewat
     * `/storage/payment_proofs/...` (S-09, B-01).
     */
    public const PROOF_DISK = 'local';

    /**
     * Direktori penyimpanan bukti transfer di dalam disk.
     */
    public const PROOF_DIRECTORY = 'payment_proofs';

    public function __construct(
        private readonly BookingRepositoryInterface $bookingRepository,
        private readonly NotificationDispatcher $notificationDispatcher,
    ) {}

    /**
     * Unggah bukti transfer (client atau mitra).
     *
     * @throws ValidationException
     */
    public function uploadProof(Booking $booking, UploadedFile $file): Booking
    {
        if ($booking->status !== BookingStatus::PENDING_PAYMENT) {
            throw ValidationException::withMessages([
                'booking' => ['Booking ini sudah tidak dalam status menunggu pembayaran.'],
            ]);
        }

        $uploader = auth()->user();
        $uploaderIsMitra = $uploader?->isMitra() === true;

        $booking = DB::transaction(function () use ($booking, $file, $uploader, $uploaderIsMitra): Booking {
            $this->deleteExistingProof($booking);

            $path = $this->storeProofFile($booking, $file);

            $updated = $this->bookingRepository->update($booking, [
                'payment_proof_path' => $path,
                'payment_reject_reason' => null,
            ]);

            $this->notifyProofUploaded($updated, $uploaderIsMitra ? 'mitra' : 'client');

            AuditLogger::log('booking.payment_proof_uploaded', [
                'actor_id' => $uploader?->id,
                'actor_role' => $uploaderIsMitra ? 'mitra' : 'client',
                'booking_id' => $updated->id,
                'booking_kode' => $updated->kode,
                'path' => $path,
                'size' => $file->getSize(),
                'mime' => $file->getMimeType(),
            ]);

            return $updated;
        });

        return $booking;
    }

    /**
     * Verifikasi bukti pembayaran (mitra).
     *
     * Alur:
     * - Validasi status & kepemilikan.
     * - Buat record `Payment` (jejak pembayaran).
     * - Update status Booking menjadi `confirmed`.
     * - Dispatch event `BookingConfirmed` -> listener membuat Trip &
     *   E-Ticket secara asynchronous.
     *
     * @throws ValidationException
     */
    public function verifyPayment(Booking $booking, User $mitra): Booking
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

        $booking = DB::transaction(function () use ($booking, $mitra): Booking {
            Payment::create([
                'booking_id' => $booking->id,
                'jumlah' => $booking->total_harga,
                'metode' => PaymentMetode::TRANSFER_MANUAL->value,
                'verified_by' => $mitra->id,
                'verified_at' => now(),
                'catatan' => "Diverifikasi manual oleh Mitra: {$mitra->name}",
            ]);

            $updated = $this->bookingRepository->update($booking, [
                'status' => BookingStatus::CONFIRMED->value,
                'payment_verified_at' => now(),
            ]);

            $this->notifyClientPaymentConfirmed($updated);

            AuditLogger::log('booking.payment_verified', [
                'actor_id' => $mitra->id,
                'actor_role' => 'mitra',
                'booking_id' => $updated->id,
                'booking_kode' => $updated->kode,
                'total_harga' => (float) $updated->total_harga,
            ]);

            return $updated;
        });

        /*
         * Dispatch event DI LUAR transaksi.
         *
         * Alasan:
         * - `CreateTripForBooking` (listener sync) akan melakukan transaksi
         *   baru untuk membuat Trip. Bila dispatch di dalam transaksi luar,
         *   kegagalan listener akan ikut me-rollback transaksi utama
         *   (payment insert, status update). Itu perilaku yang TIDAK kita
         *   inginkan: pembayaran sudah sah, Trip bisa dibuat ulang.
         * - Bila listener sync gagal setelah commit, kita catat di log
         *   dan tidak menggagalkan response ke mitra. Mitra bisa
         *   me-retry lewat tombol "Buat Trip" (bila nanti disediakan).
         */
        try {
            BookingConfirmed::dispatch($booking->refresh(), $mitra->id);
        } catch (Throwable $e) {
            Log::error('BookingPaymentService: dispatch BookingConfirmed gagal', [
                'booking_id' => $booking->id,
                'booking_kode' => $booking->kode,
                'error' => $e->getMessage(),
            ]);
        }

        return $booking->load('payments');
    }

    /**
     * Tolak bukti pembayaran (mitra).
     *
     * @throws ValidationException
     */
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

    /**
     * Hapus bukti transfer lama (bila ada) dari disk.
     */
    private function deleteExistingProof(Booking $booking): void
    {
        $oldPath = $booking->payment_proof_path;

        if ($oldPath === null || $oldPath === '') {
            return;
        }

        if (Storage::disk(self::PROOF_DISK)->exists($oldPath)) {
            Storage::disk(self::PROOF_DISK)->delete($oldPath);
        }
    }

    /**
     * Simpan file bukti transfer ke disk `local`.
     *
     * Nama file di-generate oleh server (tidak mempercayai nama dari client)
     * untuk mencegah path traversal & collision.
     *
     * @throws ValidationException
     */
    private function storeProofFile(Booking $booking, UploadedFile $file): string
    {
        $extension = strtolower((string) $file->getClientOriginalExtension());
        $filename = sprintf(
            '%s_%s.%s',
            $booking->kode,
            now()->format('YmdHis'),
            $extension,
        );

        $path = Storage::disk(self::PROOF_DISK)->putFileAs(
            self::PROOF_DIRECTORY,
            $file,
            $filename,
        );

        if ($path === false) {
            throw ValidationException::withMessages([
                'payment_proof' => ['Gagal menyimpan bukti transfer. Coba lagi.'],
            ]);
        }

        return $path;
    }

    /**
     * Notifikasi ke lawan transaksi saat bukti diunggah.
     *
     * In-app + push FCM via `NotificationDispatcher`.
     * Jika FCM gagal, hanya di-log — tidak menggagalkan upload.
     */
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

        /*
         * Bila yang upload adalah mitra (pesanan manual),
         * kirim notif ke client sebagai jejak.
         */
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

    /**
     * Notifikasi ke client saat pembayaran diverifikasi.
     *
     * In-app + push FCM via `NotificationDispatcher`.
     */
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

    /**
     * Notifikasi ke client saat bukti ditolak.
     *
     * In-app + push FCM via `NotificationDispatcher`.
     */
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