<?php

namespace App\Services\Trip;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Trip;
use App\Models\TripDay;
use App\Services\Trip\Contracts\TripStatusSynchronizerInterface;

/**
 * Menjaga agar status Trip dan Booking selalu sinkron.
 *
 * Sebelumnya `TripService::activate()` hanya mengubah status Trip dan
 * TripDay, sedangkan `BookingStatus::IN_PROGRESS`/`COMPLETED` tidak pernah
 * di-set oleh kode mana pun. Akibatnya fitur review (yang mensyaratkan
 * Trip selesai) tidak pernah bisa dipakai.
 *
 * Semua update di sini memakai satu query UPDATE bersyarat
 * (`WHERE status IN (...)`) sehingga aman dari race condition tanpa
 * perlu membaca ulang model.
 */
final class TripStatusSynchronizer implements TripStatusSynchronizerInterface
{
    /**
     * Sinkronkan status saat trip diaktifkan (scheduled -> active).
     *
     * @return Booking|null Booking yang berubah, atau null bila tidak ada
     *                      perubahan status (mis. booking sudah in_progress).
     */
    public function markActivated(Trip $trip): ?Booking
    {
        TripDay::query()
            ->where('trip_id', $trip->id)
            ->where('status', TripDay::STATUS_PENDING)
            ->update(['status' => TripDay::STATUS_IN_PROGRESS]);

        return $this->updateBookingStatus(
            $trip,
            [BookingStatus::CONFIRMED, BookingStatus::PAYMENT_VERIFIED],
            BookingStatus::IN_PROGRESS,
        );
    }

    /**
     * Sinkronkan status saat trip diselesaikan (active -> completed).
     *
     * @return Booking|null Booking yang berubah, atau null bila tidak ada
     *                      perubahan status.
     */
    public function markCompleted(Trip $trip): ?Booking
    {
        TripDay::query()
            ->where('trip_id', $trip->id)
            ->whereIn('status', [TripDay::STATUS_PENDING, TripDay::STATUS_IN_PROGRESS])
            ->update(['status' => TripDay::STATUS_COMPLETED]);

        return $this->updateBookingStatus(
            $trip,
            [
                BookingStatus::CONFIRMED,
                BookingStatus::PAYMENT_VERIFIED,
                BookingStatus::IN_PROGRESS,
            ],
            BookingStatus::COMPLETED,
        );
    }

    /**
     * @param  list<BookingStatus>  $from
     */
    private function updateBookingStatus(Trip $trip, array $from, BookingStatus $to): ?Booking
    {
        $affected = Booking::query()
            ->whereKey($trip->booking_id)
            ->whereIn('status', array_map(static fn (BookingStatus $status): string => $status->value, $from))
            ->update(['status' => $to->value]);

        if ($affected === 0) {
            return null;
        }

        return Booking::query()
            ->with('client')
            ->find($trip->booking_id);
    }
}
