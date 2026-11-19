<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\BookingConfirmed;
use App\Jobs\GenerateETicketJob;
use App\Services\Trip\TripService;
use Illuminate\Support\Facades\Log;
use Throwable;

final class CreateTripForBooking
{
    public function __construct(
        private readonly TripService $tripService,
    ) {}

    /**
     * @throws Throwable
     */
    public function handle(BookingConfirmed $event): void
    {
        try {
            $trip = $this->tripService->createFromBooking($event->booking);

            GenerateETicketJob::dispatch($trip->id);
        } catch (Throwable $e) {
            Log::error('Gagal membuat Trip dari Booking yang dikonfirmasi', [
                'booking_id' => $event->booking->id,
                'booking_kode' => $event->booking->kode,
                'verified_by_mitra_id' => $event->verifiedByMitraId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
