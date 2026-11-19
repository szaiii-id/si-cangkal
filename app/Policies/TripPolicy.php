<?php

namespace App\Policies;

use App\Models\Trip;
use App\Models\User;

/**
 * Otorisasi terpusat untuk resource Trip.
 *
 * Menggantikan pengecekan manual `abort_unless($trip->mitra_id === $request->user()->id, 403)`
 * yang tersebar di Controller dan Service (S-02, S-03: OWASP A01 Broken Access Control).
 *
 * Prinsip:
 * - Mitra hanya boleh mengakses trip miliknya (`mitra_id`).
 * - Admin boleh mengakses semua trip (monitoring & manifest).
 * - Client tidak boleh mengakses endpoint manajemen trip; akses client ke
 *   informasi trip dilakukan lewat halaman booking (BookingPolicy).
 */
final class TripPolicy
{
    /**
     * Melihat daftar trip.
     *
     * - Admin: boleh (semua).
     * - Mitra: boleh (akan difilter ke miliknya di Repository).
     * - Client: tidak diizinkan.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isMitra();
    }

    /**
     * Melihat detail trip.
     *
     * - Admin: semua trip.
     * - Mitra: hanya trip miliknya.
     * - Client: tidak diizinkan.
     */
    public function view(User $user, Trip $trip): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isMitra()) {
            return $trip->mitra_id === $user->id;
        }

        return false;
    }

    /**
     * Mengunduh manifest trip (PDF itinerary + daftar peserta).
     *
     * - Admin: semua trip.
     * - Mitra: hanya trip miliknya.
     * - Client: tidak diizinkan (client memakai E-Ticket).
     */
    public function downloadManifest(User $user, Trip $trip): bool
    {
        return $this->view($user, $trip);
    }

    /**
     * Mengaktifkan trip (scheduled -> active).
     *
     * Hanya mitra pemilik trip. Admin tidak diizinkan men-trigger transisi
     * state ini dari UI untuk menjaga pemisahan peran; aktivasi otomatis
     * dilakukan oleh scheduler (`trips:activate`).
     */
    public function activate(User $user, Trip $trip): bool
    {
        return $user->isMitra() && $trip->mitra_id === $user->id;
    }

    /**
     * Menyelesaikan trip (active -> completed).
     *
     * Hanya mitra pemilik trip.
     */
    public function complete(User $user, Trip $trip): bool
    {
        return $this->activate($user, $trip);
    }

    /**
     * Membatalkan trip (scheduled/active -> cancelled).
     *
     * Hanya mitra pemilik trip.
     * Aturan tambahan (status final tidak bisa cancel) ditegakkan di
     * TripService::cancel via TripStatus::canBeCancelled().
     */
    public function cancel(User $user, Trip $trip): bool
    {
        return $this->activate($user, $trip);
    }
}
