<?php

use App\Models\Package;
use App\Models\PackageDay;
use App\Models\User;
use App\Services\Package\PackageDayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

/*
|==========================================================================
| SETUP — Integration Test
|==========================================================================
|
| PackageDayService — persistence `PackageDay::create` + `delete` re-number
| (`PackageDay::where()->each()`).
|
| Yang di-test di sini (yang di-skip di unit test):
| - `create` — persistence `PackageDay::create` + `hari_ke` auto-assign.
| - `delete` — re-number `PackageDay::where()->each()`.
|
| Catatan:
| - `Log::info` + `Log::warning` di-mock — `PackageDayService` panggil
|   langsung.
| - `Package::factory()`, `PackageDay::factory()` — real factory + real DB.
|
*/

beforeEach(function () {
    Log::shouldReceive('info')->byDefault()->andReturnNull();
    Log::shouldReceive('warning')->byDefault()->andReturnNull();

    $this->service = app(PackageDayService::class);

    $this->mitra = User::factory()->mitra()->create([
        'must_change_password' => false,
    ]);
});

/*
|==========================================================================
| HELPER — buat paket dengan durasi
|==========================================================================
*/
function makePackageForDay(User $mitra, int $durasiHari = 3, array $overrides = []): Package
{
    return Package::factory()->forMitra($mitra)->create(array_merge([
        'durasi_hari' => $durasiHari,
        'durasi_malam' => max(0, $durasiHari - 1),
    ], $overrides));
}

/*
|==========================================================================
| KATEGORI 1 — CREATE (PERSISTENCE)
|==========================================================================
*/

describe('create — persistence', function () {
    it('create → PackageDay tersimpan di DB', function () {
        $package = makePackageForDay($this->mitra, 3);

        $day = $this->service->create($package, [
            'judul' => 'Hari 1: Penjemputan',
            'deskripsi' => 'Penjemputan di bandara',
            'jam_mulai' => '08:00',
            'jam_selesai' => '12:00',
            'lokasi' => 'Bandara',
        ]);

        $this->assertDatabaseHas('package_days', [
            'id' => $day->id,
            'package_id' => $package->id,
            'hari_ke' => 1,
            'judul' => 'Hari 1: Penjemputan',
        ]);
    });

    it('create → hari_ke auto-assign = 1 untuk hari pertama', function () {
        $package = makePackageForDay($this->mitra, 3);

        $day = $this->service->create($package, ['judul' => 'Hari 1']);

        expect($day->hari_ke)->toBe(1);
    });

    it('create hari kedua → hari_ke = 2 (auto-increment)', function () {
        $package = makePackageForDay($this->mitra, 3);

        PackageDay::factory()->create([
            'package_id' => $package->id,
            'hari_ke' => 1,
        ]);

        $day = $this->service->create($package, ['judul' => 'Hari 2']);

        expect($day->hari_ke)->toBe(2);
    });

    it('create hari ketiga → hari_ke = 3 (auto-increment)', function () {
        $package = makePackageForDay($this->mitra, 3);

        PackageDay::factory()->create(['package_id' => $package->id, 'hari_ke' => 1]);
        PackageDay::factory()->create(['package_id' => $package->id, 'hari_ke' => 2]);

        $day = $this->service->create($package, ['judul' => 'Hari 3']);

        expect($day->hari_ke)->toBe(3);
    });

    it('create → validasi durasi habis → ValidationException', function () {
        $package = makePackageForDay($this->mitra, 2);

        PackageDay::factory()->create(['package_id' => $package->id, 'hari_ke' => 1]);
        PackageDay::factory()->create(['package_id' => $package->id, 'hari_ke' => 2]);

        expect(fn () => $this->service->create($package, ['judul' => 'Hari 3']))
            ->toThrow(ValidationException::class);
    });

    it('create → field opsional null → tersimpan null', function () {
        $package = makePackageForDay($this->mitra, 3);

        $day = $this->service->create($package, ['judul' => 'Hari 1']);

        $day->refresh();
        expect($day->deskripsi)->toBeNull()
            ->and($day->jam_mulai)->toBeNull()
            ->and($day->jam_selesai)->toBeNull()
            ->and($day->lokasi)->toBeNull();
    });
});

/*
|==========================================================================
| KATEGORI 2 — UPDATE (PERSISTENCE)
|==========================================================================
*/

describe('update — persistence', function () {
    it('update → data berubah di DB', function () {
        $package = makePackageForDay($this->mitra, 3);
        $day = PackageDay::factory()->create([
            'package_id' => $package->id,
            'hari_ke' => 1,
            'judul' => 'Judul Lama',
        ]);

        $this->service->update($day, [
            'judul' => 'Judul Baru',
            'deskripsi' => 'Deskripsi Baru',
            'jam_mulai' => '09:00',
            'jam_selesai' => '11:00',
            'lokasi' => 'Lokasi Baru',
        ]);

        $this->assertDatabaseHas('package_days', [
            'id' => $day->id,
            'judul' => 'Judul Baru',
            'deskripsi' => 'Deskripsi Baru',
        ]);
    });

    it('update → hari_ke TIDAK berubah', function () {
        $package = makePackageForDay($this->mitra, 3);
        $day = PackageDay::factory()->create([
            'package_id' => $package->id,
            'hari_ke' => 2,
        ]);

        $this->service->update($day, ['judul' => 'Judul Baru']);

        $day->refresh();
        expect($day->hari_ke)->toBe(2);
    });
});

/*
|==========================================================================
| KATEGORI 3 — DELETE (RE-NUMBER)
|==========================================================================
*/

describe('delete — re-number', function () {
    it('delete hari ke-2 dari (1, 2, 3) → hari ke-3 jadi ke-2', function () {
        $package = makePackageForDay($this->mitra, 3);

        $day1 = PackageDay::factory()->create(['package_id' => $package->id, 'hari_ke' => 1, 'judul' => 'Hari 1']);
        $day2 = PackageDay::factory()->create(['package_id' => $package->id, 'hari_ke' => 2, 'judul' => 'Hari 2']);
        $day3 = PackageDay::factory()->create(['package_id' => $package->id, 'hari_ke' => 3, 'judul' => 'Hari 3']);

        $this->service->delete($day2);

        $remaining = PackageDay::where('package_id', $package->id)
            ->orderBy('hari_ke')
            ->get();

        expect($remaining)->toHaveCount(2)
            ->and($remaining[0]->hari_ke)->toBe(1)
            ->and($remaining[0]->judul)->toBe('Hari 1')
            ->and($remaining[1]->hari_ke)->toBe(2)
            ->and($remaining[1]->judul)->toBe('Hari 3');  // ← hari ke-3 jadi ke-2
    });

    it('delete hari terakhir (ke-3) → hari lain tidak berubah', function () {
        $package = makePackageForDay($this->mitra, 3);

        $day1 = PackageDay::factory()->create(['package_id' => $package->id, 'hari_ke' => 1]);
        $day2 = PackageDay::factory()->create(['package_id' => $package->id, 'hari_ke' => 2]);
        $day3 = PackageDay::factory()->create(['package_id' => $package->id, 'hari_ke' => 3]);

        $this->service->delete($day3);

        $remaining = PackageDay::where('package_id', $package->id)
            ->orderBy('hari_ke')
            ->get();

        expect($remaining)->toHaveCount(2)
            ->and($remaining[0]->hari_ke)->toBe(1)
            ->and($remaining[1]->hari_ke)->toBe(2);
    });

    it('delete hari pertama (ke-1) → hari ke-2 jadi ke-1, ke-3 jadi ke-2', function () {
        $package = makePackageForDay($this->mitra, 3);

        $day1 = PackageDay::factory()->create(['package_id' => $package->id, 'hari_ke' => 1]);
        $day2 = PackageDay::factory()->create(['package_id' => $package->id, 'hari_ke' => 2]);
        $day3 = PackageDay::factory()->create(['package_id' => $package->id, 'hari_ke' => 3]);

        $this->service->delete($day1);

        $remaining = PackageDay::where('package_id', $package->id)
            ->orderBy('hari_ke')
            ->get();

        expect($remaining)->toHaveCount(2)
            ->and($remaining[0]->hari_ke)->toBe(1)
            ->and($remaining[1]->hari_ke)->toBe(2);
    });

    it('delete → hari hilang dari DB', function () {
        $package = makePackageForDay($this->mitra, 3);
        $day = PackageDay::factory()->create(['package_id' => $package->id, 'hari_ke' => 1]);

        $this->service->delete($day);

        $this->assertDatabaseMissing('package_days', ['id' => $day->id]);
    });

    it('delete hari tengah → hari setelahnya re-number', function () {
        $package = makePackageForDay($this->mitra, 5);

        for ($i = 1; $i <= 5; $i++) {
            PackageDay::factory()->create(['package_id' => $package->id, 'hari_ke' => $i]);
        }

        $day3 = PackageDay::where('package_id', $package->id)->where('hari_ke', 3)->first();
        $this->service->delete($day3);

        $remaining = PackageDay::where('package_id', $package->id)
            ->orderBy('hari_ke')
            ->pluck('hari_ke')
            ->all();

        // (1, 2, 3, 4, 5) → hapus 3 → (1, 2, 3, 4) — re-number
        expect($remaining)->toBe([1, 2, 3, 4]);
    });
});

/*
|==========================================================================
| KATEGORI 4 — IDEMPOTENCY
|==========================================================================
*/

describe('Idempotency', function () {
    it('update 2x dengan data sama → hasil konsisten', function () {
        $package = makePackageForDay($this->mitra, 3);
        $day = PackageDay::factory()->create(['package_id' => $package->id, 'hari_ke' => 1]);

        $payload = ['judul' => 'Judul Sama'];

        $this->service->update($day, $payload);
        $day->refresh();

        $this->service->update($day, $payload);
        $day->refresh();

        expect($day->judul)->toBe('Judul Sama');
    });
});
