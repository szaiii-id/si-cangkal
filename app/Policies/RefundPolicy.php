<?php

namespace App\Policies;

use App\Enums\RefundStatus;
use App\Models\Refund;
use App\Models\User;

/**
 * Otorisasi terpusat untuk resource Refund.
 *
 * Menggantikan pengecekan manual `abort_unless($refund->mitra_id === $mitra->id, 403)`
 * di Controller (S-02, S-03: OWASP A01 Broken Access Control).
 *
 * Catatan keamanan penting:
 * - `Refund::mitra_id` adalah kolom denormalisasi. Otorisasi TIDAK boleh
 *   hanya mengandalkan kolom ini; harus diverifikasi juga terhadap
 *   `Booking::mitra_id` (sumber kebenaran). Policy ini mengecek keduanya.
 * - `approve` dan `reject` hanya boleh saat status `PENDING`, agar refund
 *   yang sudah final tidak bisa diproses ulang.
 */
final class RefundPolicy
{
    /**
     * Melihat daftar refund.
     *
     * - Admin: boleh (semua refund).
     * - Mitra: boleh (akan difilter ke miliknya di Repository/Controller).
     * - Client: tidak diizinkan (client melihat refund lewat halaman booking).
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isMitra();
    }

    /**
     * Melihat detail refund.
     *
     * - Admin: semua refund.
     * - Mitra: hanya refund miliknya (dicek via booking dan kolom denormalisasi).
     */
    public function view(User $user, Refund $refund): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $this->ownedByMitra($user, $refund);
    }

    /**
     * Menyetujui refund (memproses transfer balik secara manual).
     *
     * Aturan:
     * - Hanya mitra pemilik refund (via booking).
     * - Status refund masih `PENDING`.
     */
    public function approve(User $user, Refund $refund): bool
    {
        if (! $this->ownedByMitra($user, $refund)) {
            return false;
        }

        return $refund->status === RefundStatus::PENDING;
    }

    /**
     * Menolak refund.
     *
     * Aturan sama dengan approve: hanya mitra pemilik, hanya saat `PENDING`.
     */
    public function reject(User $user, Refund $refund): bool
    {
        return $this->approve($user, $refund);
    }

    /**
     * Cek kepemilikan refund oleh mitra.
     *
     * Memverifikasi dua sumber agar tidak bisa dibypass dengan data
     * denormalisasi yang tidak sinkron:
     * - `Refund::mitra_id` (kolom denormalisasi).
     * - `Booking::mitra_id` (sumber kebenaran).
     *
     * Refund tanpa booking terkait otomatis ditolak (defensif).
     */
    private function ownedByMitra(User $user, Refund $refund): bool
    {
        if (! $user->isMitra()) {
            return false;
        }

        if ($refund->mitra_id !== $user->id) {
            return false;
        }

        $booking = $refund->booking;

        if ($booking === null) {
            return false;
        }

        return $booking->mitra_id === $user->id;
    }
}
