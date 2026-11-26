<?php

use App\Enums\BookingStatus;
use App\Enums\TripStatus;
use App\Jobs\DisburseToMitraJob;
use App\Models\Booking;
use App\Models\Package;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;

uses(RefreshDatabase::class);

function makeTripForCompletion(array $overrides = []): Trip
{
    $mitra = User::factory()->mitra()->create(['must_change_password' => false]);
    $client = User::factory()->client()->create(['must_change_password' => false]);
    $package = Package::factory()->forMitra($mitra)->aktif()->create();

    $booking = Booking::factory()
        ->forPackage($package)
        ->forClient($client)
        ->create([
            'mitra_id' => $mitra->id,
            'status' => BookingStatus::CONFIRMED->value,
        ]);

    return Trip::factory()
        ->forPackage($package)
        ->forBooking($booking)
        ->create(array_merge([
            'mitra_id' => $mitra->id,
            'status' => TripStatus::ACTIVE->value,
            'tanggal_mulai' => now()->subDays(4)->format('Y-m-d'),
            'tanggal_selesai' => now()->subDay()->format('Y-m-d'),
            'timezone' => 'Asia/Makassar',
        ], $overrides));
}

it('menyelesaikan trip ACTIVE yang sudah lewat tanggal selesai', function () {
    Bus::fake();

    $trip = makeTripForCompletion();

    $this->artisan('trips:complete')->assertExitCode(0);

    expect($trip->fresh()->status)->toBe(TripStatus::COMPLETED)
        ->and($trip->booking->fresh()->status)->toBe(BookingStatus::COMPLETED);

    Bus::assertDispatched(DisburseToMitraJob::class);
});

it('tidak menyentuh trip yang tanggal selesainya belum lewat', function () {
    Bus::fake();

    $trip = makeTripForCompletion([
        'tanggal_selesai' => now()->addDay()->format('Y-m-d'),
    ]);

    $this->artisan('trips:complete')->assertExitCode(0);

    expect($trip->fresh()->status)->toBe(TripStatus::ACTIVE);

    Bus::assertNotDispatched(DisburseToMitraJob::class);
});
