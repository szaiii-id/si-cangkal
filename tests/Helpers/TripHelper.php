<?php

use App\Enums\TripStatus;
use App\Models\Trip;
use App\Models\TripDay;
use App\Models\User;
use Carbon\Carbon;
use Mockery\MockInterface;

/*
|==========================================================================
| Trip Helpers
|==========================================================================
| Fungsi global untuk membuat instance model Trip / TripDay di test
| tanpa menyentuh database. Semua fungsi di-guard dengan
| function_exists() untuk mencegah "Cannot redeclare function".
|
| Catatan:
| - `makeTripMock()` — untuk mock Trip dengan partial (bisa di-mock
|   method-nya).
| - `makeTripDayMock()` — untuk mock TripDay.
*/

if (! function_exists('makeTripMock')) {
    /**
     * Buat mock Trip untuk unit test (tanpa DB).
     */
    function makeTripMock(array $attrs = []): MockInterface
    {
        $trip = Mockery::mock(Trip::class)->makePartial();
        $trip->forceFill(array_merge([
            'id' => 1,
            'kode' => 'TRP-2026-001',
            'booking_id' => 10,
            'package_id' => 5,
            'mitra_id' => 99,
            'snapshot_package' => [],
            'tanggal_mulai' => Carbon::parse('2026-02-01'),
            'tanggal_selesai' => Carbon::parse('2026-02-02'),
            'jumlah_peserta' => 2,
            'timezone' => 'Asia/Makassar',
            'status' => TripStatus::SCHEDULED,
        ], $attrs));

        return $trip;
    }
}

if (! function_exists('makeTripDayMock')) {
    /**
     * Buat mock TripDay untuk unit test (tanpa DB).
     */
    function makeTripDayMock(array $attrs = []): MockInterface
    {
        $tripDay = Mockery::mock(TripDay::class)->makePartial();
        $tripDay->forceFill(array_merge([
            'id' => 10,
            'trip_id' => 1,
            'hari_ke' => 1,
            'tanggal' => Carbon::parse('2026-02-01'),
            'judul' => 'Day 1',
            'deskripsi' => null,
            'status' => TripDay::STATUS_PENDING,
        ], $attrs));

        return $tripDay;
    }
}

/*
|==========================================================================
| Entity Helpers — Trip dengan relasi
|==========================================================================
*/

if (! function_exists('makeTripWithBooking')) {
    /**
     * Buat Trip dengan relasi `booking` sudah di-set (untuk test yang
     * akses `$trip->booking`).
     *
     * @param  array<string, mixed>  $tripAttrs
     */
    function makeTripWithBooking(array $tripAttrs = [], $booking = null): MockInterface
    {
        $trip = makeTripMock($tripAttrs);
        $trip->setRelation('booking', $booking);

        return $trip;
    }
}

if (! function_exists('makeTripWithPackage')) {
    /**
     * Buat Trip dengan relasi `package` sudah di-set.
     *
     * @param  array<string, mixed>  $tripAttrs
     */
    function makeTripWithPackage(array $tripAttrs = [], $package = null): MockInterface
    {
        $trip = makeTripMock($tripAttrs);
        $trip->setRelation('package', $package);

        return $trip;
    }
}

if (! function_exists('makeTripWithMitra')) {
    /**
     * Buat Trip dengan relasi `mitra` sudah di-set.
     *
     * @param  array<string, mixed>  $tripAttrs
     */
    function makeTripWithMitra(array $tripAttrs = [], ?User $mitra = null): MockInterface
    {
        $trip = makeTripMock($tripAttrs);
        $trip->setRelation('mitra', $mitra);

        return $trip;
    }
}
