<?php

namespace App\Services\Trip\Contracts;

use App\Models\Booking;
use App\Models\Trip;

/**
 * Kontrak sinkronisasi status Trip -> Booking/TripDay.
 *
 * Dipisah ke interface agar TripService bisa di-unit-test tanpa
 * menyentuh database dan implementing class bisa diganti.
 */
interface TripStatusSynchronizerInterface
{
    public function markActivated(Trip $trip): ?Booking;

    public function markCompleted(Trip $trip): ?Booking;
}
