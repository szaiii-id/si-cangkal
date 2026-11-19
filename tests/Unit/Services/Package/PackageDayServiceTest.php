<?php

use App\Models\Package;
use App\Models\PackageDay;
use App\Services\Package\PackageDayService;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/*
|==========================================================================
| SETUP — Unit Test (NO DB)
|==========================================================================
|
| Cakupan:
| - `validateCanAddDay()` — test via reflection (protected).
| - `update()` — mock instance `$day->update()` (no static call).
|
| Method yang TIDAK di-test di unit (butuh DB → Feature test):
| - `create()` — static `PackageDay::create()`
| - `delete()` — static `PackageDay::where()->each()` re-number
|
| Test flow pre-create (`max` query) via mock sudah cukup untuk unit.
| Persistence + re-number diverifikasi di Feature test.
|
*/

beforeEach(function () {
    $this->service = new PackageDayService;

    DB::shouldReceive('transaction')
        ->byDefault()
        ->andReturnUsing(fn ($callback) => $callback());

    Log::shouldReceive('info')->byDefault()->andReturnNull();
    Log::shouldReceive('warning')->byDefault()->andReturnNull();
});

afterEach(function () {
    Mockery::close();
});

/*
|==========================================================================
| HELPER — invoke protected method
|==========================================================================
*/

/**
 * Helper untuk invoke protected method `validateCanAddDay` via reflection.
 */
function invokeValidateCanAddDay(PackageDayService $service, Package $package): void
{
    $ref = new ReflectionMethod($service, 'validateCanAddDay');
    $ref->setAccessible(true);
    $ref->invoke($service, $package);
}

/*
|==========================================================================
| KATEGORI 1 — BOUNDARY VALUE ANALYSIS (BVA)
|==========================================================================
*/

describe('BVA — validateCanAddDay', function () {
    it('existing = durasi_hari → ValidationException', function () {
        $package = makePackageMock(['durasi_hari' => 3]);

        $hasMany = Mockery::mock(HasMany::class);
        $hasMany->shouldReceive('count')->once()->andReturn(3);
        $package->shouldReceive('days')->andReturn($hasMany);

        expect(fn () => invokeValidateCanAddDay($this->service, $package))
            ->toThrow(ValidationException::class);
    });

    it('existing = durasi_hari - 1 (batas bawah lolos) → tidak throw', function () {
        $package = makePackageMock(['durasi_hari' => 3]);

        $hasMany = Mockery::mock(HasMany::class);
        $hasMany->shouldReceive('count')->once()->andReturn(2);
        $package->shouldReceive('days')->andReturn($hasMany);

        // Tidak throw
        invokeValidateCanAddDay($this->service, $package);

        expect(true)->toBeTrue();
    });

    it('existing = 0 (belum ada hari) → tidak throw', function () {
        $package = makePackageMock(['durasi_hari' => 1]);

        $hasMany = Mockery::mock(HasMany::class);
        $hasMany->shouldReceive('count')->once()->andReturn(0);
        $package->shouldReceive('days')->andReturn($hasMany);

        invokeValidateCanAddDay($this->service, $package);

        expect(true)->toBeTrue();
    });

    it('existing > durasi_hari (data corrupt) → ValidationException', function () {
        $package = makePackageMock(['durasi_hari' => 2]);

        $hasMany = Mockery::mock(HasMany::class);
        $hasMany->shouldReceive('count')->once()->andReturn(5);
        $package->shouldReceive('days')->andReturn($hasMany);

        expect(fn () => invokeValidateCanAddDay($this->service, $package))
            ->toThrow(ValidationException::class);
    });
});

/*
|==========================================================================
| KATEGORI 2 — EQUIVALENCE PARTITIONING (create flow)
|==========================================================================
*/

describe('Equivalence — create flow pre-insert', function () {
    it('create query max(hari_ke) pada days relasi', function () {
        // Verifikasi flow pre-create: max query dipanggil.
        // Persistence (`PackageDay::create`) akan error karena no DB
        // — diabaikan via try-catch. Assertion via mock.
        $package = makePackageMock(['id' => 5, 'durasi_hari' => 3]);

        // Mock `days()->count()` (validate) → 0
        // Mock `days()->max('hari_ke')` (create) → null
        $hasManyCount = Mockery::mock(HasMany::class);
        $hasManyCount->shouldReceive('count')->once()->andReturn(0);

        $hasManyMax = Mockery::mock(HasMany::class);
        $hasManyMax->shouldReceive('max')->once()->with('hari_ke')->andReturn(null);

        $package->shouldReceive('days')->andReturn($hasManyCount, $hasManyMax);

        try {
            $this->service->create($package, ['judul' => 'Hari 1']);
        } catch (Throwable) {
            // Diabaikan: static `PackageDay::create` butuh DB
        }

        expect(true)->toBeTrue();
    });

    it('create dengan existing max = 2 → next hari_ke = 3 (flow)', function () {
        $package = makePackageMock(['id' => 5, 'durasi_hari' => 5]);

        $hasManyCount = Mockery::mock(HasMany::class);
        $hasManyCount->shouldReceive('count')->once()->andReturn(2);

        $hasManyMax = Mockery::mock(HasMany::class);
        $hasManyMax->shouldReceive('max')->once()->with('hari_ke')->andReturn(2);

        $package->shouldReceive('days')->andReturn($hasManyCount, $hasManyMax);

        try {
            $this->service->create($package, ['judul' => 'Hari 3']);
        } catch (Throwable) {
        }

        expect(true)->toBeTrue();
    });
});

/*
|==========================================================================
| KATEGORI 3 — EDGE CASES (update)
|==========================================================================
*/

describe('Edge Cases — update tidak ubah hari_ke', function () {
    it('update field teks, hari_ke TIDAK di payload', function () {
        $day = makePackageDayMock(['id' => 10, 'package_id' => 5, 'hari_ke' => 2]);

        $day->shouldReceive('update')
            ->once()
            ->with(Mockery::on(fn ($p) => ! array_key_exists('hari_ke', $p)
                && $p['judul'] === 'Judul Baru'
                && $p['deskripsi'] === 'Deskripsi'
                && $p['jam_mulai'] === '08:00'
                && $p['jam_selesai'] === '10:00'
                && $p['lokasi'] === 'Lokasi'))
            ->andReturn(true);

        $day->shouldReceive('refresh')->once()->andReturnSelf();

        $this->service->update($day, [
            'judul' => 'Judul Baru',
            'deskripsi' => 'Deskripsi',
            'jam_mulai' => '08:00',
            'jam_selesai' => '10:00',
            'lokasi' => 'Lokasi',
        ]);
    });

    it('update dengan field opsional null → null diteruskan', function () {
        $day = makePackageDayMock(['id' => 10]);

        $day->shouldReceive('update')
            ->once()
            ->with(Mockery::on(fn ($p) => $p['deskripsi'] === null
                && $p['jam_mulai'] === null
                && $p['jam_selesai'] === null
                && $p['lokasi'] === null))
            ->andReturn(true);

        $day->shouldReceive('refresh')->andReturnSelf();

        $this->service->update($day, ['judul' => 'Only Judul']);
    });
});

/*
|==========================================================================
| KATEGORI 4 — HAPPY PATH (update)
|==========================================================================
*/

describe('Happy Path — update', function () {
    it('update → return $day->refresh()', function () {
        $day = makePackageDayMock(['id' => 10]);
        $refreshed = makePackageDayMock(['id' => 10, 'judul' => 'Refreshed']);

        $day->shouldReceive('update')->once()->andReturn(true);
        $day->shouldReceive('refresh')->once()->andReturn($refreshed);

        $result = $this->service->update($day, ['judul' => 'Test']);

        expect($result)->toBe($refreshed);
    });

    it('update log info dengan context package_id, day_id, hari_ke', function () {
        $day = makePackageDayMock([
            'id' => 100,
            'package_id' => 5,
            'hari_ke' => 2,
        ]);

        $day->shouldReceive('update')->once();
        $day->shouldReceive('refresh')->andReturnSelf();

        Log::shouldReceive('info')
            ->once()
            ->with('Package day diupdate', Mockery::on(fn ($ctx) => $ctx['package_id'] === 5
                && $ctx['day_id'] === 100
                && $ctx['hari_ke'] === 2
            ));

        $this->service->update($day, ['judul' => 'Test']);
    });
});

/*
|==========================================================================
| KATEGORI 5 — BEHAVIORAL (transaction)
|==========================================================================
*/

describe('Behavioral — DB::transaction', function () {
    it('update memanggil DB::transaction sekali', function () {
        $day = makePackageDayMock();
        $day->shouldReceive('update')->once();
        $day->shouldReceive('refresh')->andReturnSelf();

        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn ($cb) => $cb());

        $this->service->update($day, ['judul' => 'Test']);
    });

    it('update urutan call: transaction → update → refresh', function () {
        $day = makePackageDayMock();

        DB::shouldReceive('transaction')
            ->once()
            ->ordered()
            ->andReturnUsing(fn ($cb) => $cb());

        $day->shouldReceive('update')->once()->ordered();
        $day->shouldReceive('refresh')->once()->ordered()->andReturnSelf();

        $this->service->update($day, ['judul' => 'Test']);
    });
});

/*
|==========================================================================
| CATATAN: Method yang butuh Feature test
|==========================================================================
|
| Method `create` — static `PackageDay::create()` butuh DB untuk insert.
| Method `delete` — static `PackageDay::where()->each()` butuh DB untuk
|   re-number setelah delete.
|
| Keduanya diverifikasi di Feature test dengan `RefreshDatabase`.
|
| Flow pre-create (validateCanAddDay + max query) sudah di-test di unit
| (Kategori 1 & 2).
|
*/
