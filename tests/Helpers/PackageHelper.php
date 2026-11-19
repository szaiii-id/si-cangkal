<?php

use App\Enums\BookingStatus;
use App\Enums\PackageStatus;
use App\Models\Booking;
use App\Models\Package;
use App\Models\PackageDay;
use App\Models\PackageDayCost;
use App\Models\PackageFacility;
use Mockery\MockInterface;

/*
|==========================================================================
| Package Helpers
|==========================================================================
| Fungsi global untuk membuat instance model Package* di test
| tanpa menyentuh database. Semua fungsi di-guard dengan
| function_exists() untuk mencegah "Cannot redeclare function".
*/

if (! function_exists('makePackageMock')) {
    function makePackageMock(array $attrs = []): MockInterface
    {
        $package = Mockery::mock(Package::class)->makePartial();
        $package->forceFill(array_merge([
            'id' => 5,
            'kode' => 'PKG-001',
            'nama' => 'Paket Test',
            'status' => PackageStatus::AKTIF,
            'timezone' => 'Asia/Makassar',
            'harga_per_orang' => '500000.00',
            'harga_per_rombongan' => null,
            'min_peserta' => 2,
            'max_peserta' => 10,
            'durasi_hari' => 3,
            'durasi_malam' => 2,
        ], $attrs));

        return $package;
    }
}

if (! function_exists('makePackageDayMock')) {
    function makePackageDayMock(array $attrs = []): MockInterface
    {
        $day = Mockery::mock(PackageDay::class)->makePartial();
        $day->forceFill(array_merge([
            'id' => 10,
            'package_id' => 5,
            'hari_ke' => 1,
            'judul' => 'Day 1',
            'deskripsi' => null,
            'jam_mulai' => null,
            'jam_selesai' => null,
            'lokasi' => null,
        ], $attrs));

        return $day;
    }
}

if (! function_exists('makePackageDayCostMock')) {
    function makePackageDayCostMock(array $attrs = []): MockInterface
    {
        $cost = Mockery::mock(PackageDayCost::class)->makePartial();
        $cost->forceFill(array_merge([
            'id' => 100,
            'package_day_id' => 10,
            'nama_biaya' => 'Makan Siang',
            'kategori' => 'konsumsi',
            'jumlah' => '50000.00',
            'satuan' => 'per_orang',
            'catatan' => null,
        ], $attrs));

        return $cost;
    }
}

if (! function_exists('makePackageFacilityMock')) {
    function makePackageFacilityMock(array $attrs = []): MockInterface
    {
        $facility = Mockery::mock(PackageFacility::class)->makePartial();
        $facility->forceFill(array_merge([
            'id' => 200,
            'package_id' => 5,
            'tipe' => 'include',
            'nama' => 'Breakfast',
            'urutan' => 1,
        ], $attrs));

        return $facility;
    }
}

if (! function_exists('makeBookingMock')) {
    /**
     * Buat mock Booking untuk unit test (tanpa DB).
     */
    function makeBookingMock(array $attrs = []): MockInterface
    {
        $booking = Mockery::mock(Booking::class)->makePartial();
        $booking->forceFill(array_merge([
            'id' => 1,
            'kode' => 'BKG-2026-001',
            'package_id' => 5,
            'user_id' => 10,
            'mitra_id' => 99,
            'status' => BookingStatus::PENDING_PAYMENT,
            'jumlah_peserta' => 2,
            'total_harga' => '1000000.00',
            'tanggal_keberangkatan' => '2026-02-01',
            'expired_at' => null,
            'catatan' => null,
        ], $attrs));

        return $booking;
    }
}

if (! function_exists('validPackagePayload')) {
    /**
     * Payload valid untuk `PackageService::create` / `update` /
     * `updateWithRelations`.
     *
     * Dipakai di:
     * - `tests/Feature/Mitra/PackageControllerTest.php`
     * - `tests/Feature/Services/Package/PackageServiceTest.php`
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function validPackagePayload(array $overrides = []): array
    {
        return array_merge([
            'nama' => 'Paket Wisata Test',
            'deskripsi' => 'Deskripsi paket',
            'kategori' => 'wisata_alam',
            'durasi_hari' => 2,
            'durasi_malam' => 1,
            'harga_per_orang' => 500_000,
            'harga_per_rombongan' => null,
            'max_peserta' => null,
            'timezone' => 'Asia/Makassar',
        ], $overrides);
    }
}
