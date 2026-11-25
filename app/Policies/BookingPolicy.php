<?php

namespace App\Policies;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\User;

/**
 * Otorisasi terpusat untuk resource Booking.
 *
 * Menggantikan pengecekan manual `abort_unless($booking->user_id === auth()->id(), 403)`
 * dan sejenisnya di Controller (S-02, S-03: OWASP A01 Broken Access Control).
 *
 * Prinsip:
 * - Client hanya boleh mengakses booking miliknya (`user_id`).
 * - Mitra hanya boleh mengakses booking yang ditujukan kepadanya (`mitra_id`).
 * - Admin boleh mengakses semua booking (untuk monitoring).
 * - Aksi yang bergantung pada status (upload, cancel, verify) tetap diperiksa
 *   di sini agar Controller tidak lagi menyimpan aturan bisnis.
 */
final class BookingPolicy
{
    /**
     * Melihat detail booking.
     *
     * Berlaku untuk halaman show (client, mitra, admin) dan route
     * download bukti transfer (privat).
     */
    public function view(User $user, Booking $booking): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isClient()) {
            return $booking->user_id === $user->id;
        }

        if ($user->isMitra()) {
            return $booking->mitra_id === $user->id;
        }

        return false;
    }

    /**
     * Mengunggah bukti transfer.
     *
     * Aturan:
     * - Client: hanya booking miliknya, dan status `pending_payment`
     *   (S-01: mencegah upload pada booking yang sudah final).
     * - Mitra: hanya booking miliknya, dan status `pending_payment`.
     * - Admin: tidak diizinkan mengunggah bukti atas nama pihak lain.
     */
    public function uploadProof(User $user, Booking $booking): bool
    {
        if ($booking->status !== BookingStatus::PENDING_PAYMENT) {
            return false;
        }

        if ($user->isClient()) {
            return $booking->user_id === $user->id;
        }

        if ($user->isMitra()) {
            return $booking->mitra_id === $user->id;
        }

        return false;
    }

    /**
     * Membatalkan booking oleh client.
     *
     * Aturan bisnis:
     * - Hanya pemilik booking.
     * - Hanya boleh saat status `pending_payment` atau `payment_verified`
     *   (lihat BookingStatus::canBeCancelledByClient).
     *
     *   FIX: cek `payments()->whereNotNull('verified_at')` DIHAPUS karena:
     * - Flow runtime tidak pernah men-set status `payment_verified` setelah
     *   payment diverifikasi. Saat `verifyPayment()`, status langsung menjadi
     *   `CONFIRMED` DAN payment dibuat dengan `verified_at`. Artinya booking
     *   `pending_payment`/`payment_verified` SELALU tidak punya payment
     *   terverifikasi — jadi cek tersebut hanya menimbulkan false-positive
     *   pada data legacy (seeder) yang status-nya `payment_verified` tapi
     *   sudah punya payment verified.
     * - `canBeCancelledByClient()` sudah membatasi status yang boleh cancel.
     * - File bukti transfer (jika sudah diupload) akan dihapus oleh
     *   `BookingCancellationService::deletePaymentProof()` saat cancel,
     *   jadi tidak ada residu.
     */
    public function cancelByClient(User $user, Booking $booking): bool
    {
        if (! $user->isClient() || $booking->user_id !== $user->id) {
            return false;
        }

        return $booking->status->canBeCancelledByClient();
    }

    /**
     * Membatalkan booking oleh mitra.
     *
     * Aturan bisnis:
     * - Hanya mitra pemilik booking.
     * - Tidak boleh bila booking sudah `completed` atau `cancelled`.
     */
    public function cancelByMitra(User $user, Booking $booking): bool
    {
        if (! $user->isMitra() || $booking->mitra_id !== $user->id) {
            return false;
        }

        return ! in_array($booking->status, [
            BookingStatus::COMPLETED,
            BookingStatus::CANCELLED,
        ], true);
    }

    /**
     * Memverifikasi pembayaran.
     *
     * Aturan bisnis:
     * - Hanya mitra pemilik booking.
     * - Hanya saat status `pending_payment`.
     */
    public function verifyPayment(User $user, Booking $booking): bool
    {
        if (! $user->isMitra() || $booking->mitra_id !== $user->id) {
            return false;
        }

        return $booking->status === BookingStatus::PENDING_PAYMENT;
    }

    /**
     * Buat payment link Xendit untuk booking manual.
     *
     * Hanya mitra pemilik yang bisa membuat link, dan hanya saat booking
     * masih menunggu pembayaran.
     */
    public function createPaymentLink(User $user, Booking $booking): bool
    {
        return $user->isMitra()
            && $booking->mitra_id === $user->id
            && $booking->status === BookingStatus::PENDING_PAYMENT;
    }

    /**
     * Menolak bukti pembayaran.
     *
     * Aturan bisnis sama dengan verifikasi: mitra pemilik,
     * status masih `pending_payment`.
     */
    public function rejectPayment(User $user, Booking $booking): bool
    {
        return $this->verifyPayment($user, $booking);
    }

    /**
     * Mengunduh E-Ticket.
     *
     * Aturan bisnis:
     * - Hanya client pemilik booking.
     * - Hanya bila booking sudah melewati tahap `pending_payment`
     *   (E-Ticket dibuat setelah pembayaran diverifikasi).
     *
     *  FIX: logika disederhanakan dengan explicit allow-list.
     * - `pending_payment` → TOLAK (belum ada E-Ticket).
     * - `payment_verified` → TOLAK (E-Ticket belum di-generate; baru setelah CONFIRMED).
     * - `confirmed`/`in_progress`/`completed` → IZINKAN (E-Ticket sudah atau sedang dibuat).
     * - `cancelled` → TOLAK.
     *
     * Catatan: untuk status `confirmed`, E-Ticket mungkin belum selesai dibuat
     * karena berjalan di queue (`GenerateETicketJob`). Controller
     * `ETicketController@download` sudah menangani kasus file tidak ada
     * dengan `abort(404)` + pesan "E-Ticket tidak ditemukan".
     */
    public function downloadETicket(User $user, Booking $booking): bool
    {
        if (! $user->isClient() || $booking->user_id !== $user->id) {
            return false;
        }

        return in_array($booking->status, [
            BookingStatus::CONFIRMED,
            BookingStatus::IN_PROGRESS,
            BookingStatus::COMPLETED,
        ], true);
    }

    /**
     * Mengajukan refund (approve/reject oleh mitra).
     *
     * Aturan bisnis:
     * - Hanya mitra pemilik booking.
     * - Status booking masih memungkinkan refund (dicek di RefundService).
     */
    public function refund(User $user, Booking $booking): bool
    {
        return $user->isMitra() && $booking->mitra_id === $user->id;
    }

    /**
     * Mengakses halaman monitoring di sisi admin.
     *
     * Saat ini semua admin boleh melihat semua booking.
     * Method ini disediakan sebagai titik ekstensi bila di kemudian
     * hari butuh pembatasan per-wilayah atau per-tim.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }
}
