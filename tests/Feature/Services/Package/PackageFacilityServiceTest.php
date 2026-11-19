<?php

use App\Enums\PackageFacilityTipe;
use App\Models\Package;
use App\Models\PackageFacility;
use App\Models\User;
use App\Services\Package\PackageFacilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

/*
|==========================================================================
| SETUP — Integration Test
|==========================================================================
|
| PackageFacilityService — persistence `PackageFacility::create` +
| `urutan` auto-assign per tipe.
|
| Yang di-test di sini (yang di-skip di unit test):
| - `create` — persistence `PackageFacility::create` + `urutan` auto-assign
|   (max existing per tipe + 1).
| - `update` — persistence `tipe` + `nama` (urutan tetap).
| - `delete` — persistence delete.
|
| Catatan:
| - `Log::info` + `Log::warning` di-mock — `PackageFacilityService`
|   panggil langsung.
| - `Package::factory()`, `PackageFacility::factory()` — real factory +
|   real DB.
|
*/

beforeEach(function () {
    Log::shouldReceive('info')->byDefault()->andReturnNull();
    Log::shouldReceive('warning')->byDefault()->andReturnNull();

    $this->service = app(PackageFacilityService::class);

    $this->mitra = User::factory()->mitra()->create([
        'must_change_password' => false,
    ]);
});

/*
|==========================================================================
| HELPER — buat package
|==========================================================================
*/
function makePackageForFacility(User $mitra, array $overrides = []): Package
{
    return Package::factory()->forMitra($mitra)->create($overrides);
}

/*
|==========================================================================
| KATEGORI 1 — CREATE (PERSISTENCE)
|==========================================================================
*/

describe('create — persistence', function () {
    it('create → PackageFacility tersimpan di DB', function () {
        $package = makePackageForFacility($this->mitra);

        $facility = $this->service->create($package, [
            'tipe' => PackageFacilityTipe::INCLUDE->value,
            'nama' => 'Transportasi',
        ]);

        $this->assertDatabaseHas('package_facilities', [
            'id' => $facility->id,
            'package_id' => $package->id,
            'tipe' => PackageFacilityTipe::INCLUDE->value,
            'nama' => 'Transportasi',
            'urutan' => 1,
        ]);
    });

    it('create fasilitas pertama tipe include → urutan = 1', function () {
        $package = makePackageForFacility($this->mitra);

        $facility = $this->service->create($package, [
            'tipe' => PackageFacilityTipe::INCLUDE->value,
            'nama' => 'Transportasi',
        ]);

        expect($facility->urutan)->toBe(1);
    });

    it('create fasilitas kedua tipe include → urutan = 2 (auto-increment)', function () {
        $package = makePackageForFacility($this->mitra);

        PackageFacility::factory()->create([
            'package_id' => $package->id,
            'tipe' => 'include',
            'urutan' => 1,
        ]);

        $facility = $this->service->create($package, [
            'tipe' => PackageFacilityTipe::INCLUDE->value,
            'nama' => 'Makan',
        ]);

        expect($facility->urutan)->toBe(2);
    });

    it('create tipe exclude → urutan dihitung terpisah per tipe', function () {
        $package = makePackageForFacility($this->mitra);

        // Include: urutan 1, 2
        PackageFacility::factory()->create(['package_id' => $package->id, 'tipe' => 'include', 'urutan' => 1]);
        PackageFacility::factory()->create(['package_id' => $package->id, 'tipe' => 'include', 'urutan' => 2]);

        // Exclude: urutan 1 (terpisah)
        $exclude = $this->service->create($package, [
            'tipe' => PackageFacilityTipe::EXCLUDE->value,
            'nama' => 'Tiket Pesawat',
        ]);

        expect($exclude->urutan)->toBe(1);
    });

    it('create tipe exclude kedua → urutan = 2', function () {
        $package = makePackageForFacility($this->mitra);

        PackageFacility::factory()->create([
            'package_id' => $package->id,
            'tipe' => 'exclude',
            'urutan' => 1,
        ]);

        $facility = $this->service->create($package, [
            'tipe' => PackageFacilityTipe::EXCLUDE->value,
            'nama' => 'Laundry',
        ]);

        expect($facility->urutan)->toBe(2);
    });

    it('create 3 fasilitas include → urutan 1, 2, 3', function () {
        $package = makePackageForFacility($this->mitra);

        $f1 = $this->service->create($package, ['tipe' => 'include', 'nama' => 'A']);
        $f2 = $this->service->create($package, ['tipe' => 'include', 'nama' => 'B']);
        $f3 = $this->service->create($package, ['tipe' => 'include', 'nama' => 'C']);

        expect($f1->urutan)->toBe(1)
            ->and($f2->urutan)->toBe(2)
            ->and($f3->urutan)->toBe(3);
    });
});

/*
|==========================================================================
| KATEGORI 2 — UPDATE (PERSISTENCE)
|==========================================================================
*/

describe('update — persistence', function () {
    it('update → tipe + nama berubah, urutan tetap', function () {
        $package = makePackageForFacility($this->mitra);
        $facility = PackageFacility::factory()->create([
            'package_id' => $package->id,
            'tipe' => 'include',
            'nama' => 'Lama',
            'urutan' => 5,
        ]);

        $this->service->update($facility, [
            'tipe' => PackageFacilityTipe::EXCLUDE->value,
            'nama' => 'Baru',
        ]);

        $this->assertDatabaseHas('package_facilities', [
            'id' => $facility->id,
            'tipe' => 'exclude',
            'nama' => 'Baru',
            'urutan' => 5,  // ← tidak berubah
        ]);
    });

    it('update → return facility yang sudah di-refresh', function () {
        $package = makePackageForFacility($this->mitra);
        $facility = PackageFacility::factory()->create([
            'package_id' => $package->id,
            'tipe' => 'include',
            'nama' => 'Lama',
        ]);

        $result = $this->service->update($facility, [
            'tipe' => 'include',
            'nama' => 'Baru',
        ]);

        expect($result->nama)->toBe('Baru');
    });
});

/*
|==========================================================================
| KATEGORI 3 — DELETE (PERSISTENCE)
|==========================================================================
*/

describe('delete — persistence', function () {
    it('delete → facility hilang dari DB', function () {
        $package = makePackageForFacility($this->mitra);
        $facility = PackageFacility::factory()->create([
            'package_id' => $package->id,
            'tipe' => 'include',
        ]);

        $this->service->delete($facility);

        $this->assertDatabaseMissing('package_facilities', ['id' => $facility->id]);
    });

    it('delete → tidak mengubah facility lain', function () {
        $package = makePackageForFacility($this->mitra);

        $f1 = PackageFacility::factory()->create(['package_id' => $package->id, 'tipe' => 'include', 'urutan' => 1]);
        $f2 = PackageFacility::factory()->create(['package_id' => $package->id, 'tipe' => 'include', 'urutan' => 2]);

        $this->service->delete($f1);

        $this->assertDatabaseHas('package_facilities', [
            'id' => $f2->id,
            'urutan' => 2,  // ← tidak berubah (tidak re-number)
        ]);
    });
});

/*
|==========================================================================
| KATEGORI 4 — IDEMPOTENCY
|==========================================================================
*/

describe('Idempotency', function () {
    it('update 2x dengan data sama → hasil konsisten', function () {
        $package = makePackageForFacility($this->mitra);
        $facility = PackageFacility::factory()->create([
            'package_id' => $package->id,
            'tipe' => 'include',
            'nama' => 'Lama',
        ]);

        $payload = ['tipe' => 'include', 'nama' => 'Sama'];

        $this->service->update($facility, $payload);
        $facility->refresh();
        $this->service->update($facility, $payload);
        $facility->refresh();

        expect($facility->nama)->toBe('Sama');
    });
});
