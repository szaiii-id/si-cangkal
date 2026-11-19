<?php

namespace App\Policies;

use App\Models\Package;
use App\Models\User;

/**
 * Otorisasi terpusat untuk resource Package.
 *
 * Menggantikan pengecekan manual `abort_unless($package->mitra_id === auth()->id(), 403)`
 * yang tersebar di banyak Controller (S-02, S-03: OWASP A01 Broken Access Control).
 *
 * Prinsip:
 * - Mitra hanya boleh mengelola paket miliknya sendiri.
 * - Admin boleh melihat (read-only) semua paket.
 * - Client tidak boleh mengakses endpoint manajemen paket; akses client
 *   ke katalog memakai route terpisah (CatalogController) yang sudah
 *   memfilter `status = aktif`.
 */
final class PackagePolicy
{
    /**
     * Melihat daftar paket (index).
     *
     * - Admin: boleh lihat semua.
     * - Mitra: boleh lihat daftar (difilter di Controller via mitra_id).
     * - Client: tidak boleh akses endpoint manajemen.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isMitra();
    }

    /**
     * Melihat detail paket.
     *
     * - Admin: semua paket.
     * - Mitra: hanya paket miliknya.
     * - Client: hanya paket aktif (dipakai bila controller katalog
     *   memutuskan memakai policy; saat ini CatalogController tetap
     *   memakai `abort_unless($package->isAktif(), 404)` sesuai
     *   kesepakatan agar perilaku publik tidak berubah).
     */
    public function view(User $user, Package $package): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isMitra()) {
            return $package->mitra_id === $user->id;
        }

        if ($user->isClient()) {
            return $package->isAktif();
        }

        return false;
    }

    /**
     * Mengubah data paket.
     *
     * Hanya mitra pemilik paket. Admin tidak diizinkan mengubah
     * paket mitra untuk menjaga pemisahan peran (read-only).
     */
    public function update(User $user, Package $package): bool
    {
        return $user->isMitra() && $package->mitra_id === $user->id;
    }

    /**
     * Menghapus (soft delete) paket.
     *
     * Aturan tambahan (punya booking) ditegakkan di PackageService::delete,
     * bukan di sini, agar pesan error spesifik tetap bisa ditampilkan.
     */
    public function delete(User $user, Package $package): bool
    {
        return $this->update($user, $package);
    }

    /**
     * Mempublikasikan paket (draft -> aktif).
     */
    public function publish(User $user, Package $package): bool
    {
        return $this->update($user, $package);
    }

    /**
     * Mengarsipkan paket (aktif -> arsip).
     */
    public function archive(User $user, Package $package): bool
    {
        return $this->update($user, $package);
    }

    /**
     * Merestore paket dari arsip (arsip -> draft).
     */
    public function restore(User $user, Package $package): bool
    {
        return $this->update($user, $package);
    }

    /**
     * Mengelola itinerary (hari) paket.
     *
     * Dipakai di PackageDayController. Object `$package` diambil dari
     * relasi `$day->package` untuk ability update/delete pada day.
     */
    public function manageDays(User $user, Package $package): bool
    {
        return $this->update($user, $package);
    }

    /**
     * Mengelola fasilitas paket.
     *
     * Dipakai di PackageFacilityController. Object `$package` diambil dari
     * relasi `$facility->package` untuk ability update/delete pada facility.
     */
    public function manageFacilities(User $user, Package $package): bool
    {
        return $this->update($user, $package);
    }

    /**
     * Membuat pesanan manual (offline/WA) atas nama paket ini.
     */
    public function createManualBooking(User $user, Package $package): bool
    {
        return $this->update($user, $package);
    }
}
