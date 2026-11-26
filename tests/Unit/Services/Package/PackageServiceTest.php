<?php

use App\Enums\PackageStatus;
use App\Models\Package;
use App\Repositories\Contracts\PackageRepositoryInterface;
use App\Services\Package\PackageService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Psr\Log\LoggerInterface;

/*
|==========================================================================
| SETUP — Unit Test (NO DB)
|==========================================================================
*/

beforeEach(function () {
    Storage::fake('public');

    $this->repo = Mockery::mock(PackageRepositoryInterface::class);
    $this->service = new PackageService($this->repo);

    DB::shouldReceive('transaction')
        ->byDefault()
        ->andReturnUsing(fn ($callback) => $callback());

    DB::shouldReceive('afterCommit')
        ->byDefault()
        ->andReturnUsing(fn (callable $callback) => $callback());

    // Mock AuditLogger → Log::channel('audit')
    $auditLoggerMock = Mockery::mock(LoggerInterface::class);
    $auditLoggerMock->shouldReceive('info')->byDefault()->andReturnNull();

    Log::shouldReceive('channel')
        ->with('audit')
        ->byDefault()
        ->andReturn($auditLoggerMock);

    Log::shouldReceive('driver')
        ->byDefault()
        ->andReturn($auditLoggerMock);

    Log::shouldReceive('info')->byDefault()->andReturnNull();
    Log::shouldReceive('warning')->byDefault()->andReturnNull();
    Log::shouldReceive('error')->byDefault()->andReturnNull();
});

afterEach(function () {
    Mockery::close();
});

/*
|==========================================================================
| HELPER
|==========================================================================
*/

function validPackageData(array $overrides = []): array
{
    return array_merge([
        'nama' => 'Paket Baru',
        'deskripsi' => 'Deskripsi',
        'kategori' => 'wisata_alam',
        'durasi_hari' => 2,
        'durasi_malam' => 1,
        'harga_per_orang' => 500_000,
        'harga_per_rombongan' => null,
        'max_peserta' => null,
        'timezone' => 'Asia/Makassar',
    ], $overrides);
}

/*
|==========================================================================
| KATEGORI 1 — BVA
|==========================================================================
*/

describe('BVA — validate durasi_malam', function () {
    it('durasi_malam >= durasi_hari (2D2N) → ValidationException', function () {
        $data = validPackageData(['durasi_hari' => 2, 'durasi_malam' => 2]);

        expect(fn () => $this->service->create($data, makeUser(['id' => 1, 'role' => 'mitra'])))
            ->toThrow(ValidationException::class);
    });

    it('durasi_malam == durasi_hari - 1 (2D1N) → lolos validasi', function () {
        $data = validPackageData(['durasi_hari' => 2, 'durasi_malam' => 1]);

        $this->repo->shouldReceive('slugExists')->andReturn(false);
        $this->repo->shouldReceive('generateKode')->andReturn('PKG-2026-001');
        $this->repo->shouldReceive('create')->once()->andReturn(new Package);

        $this->service->create($data, makeUser(['id' => 1, 'role' => 'mitra']));
    });

    it('durasi_hari = 0 → tidak error "durasi_malam" (guard durasiHari > 0)', function () {
        $data = validPackageData(['durasi_hari' => 0, 'durasi_malam' => 0]);

        $this->repo->shouldReceive('slugExists')->andReturn(false);
        $this->repo->shouldReceive('generateKode')->andReturn('PKG-2026-001');
        $this->repo->shouldReceive('create')->once()->andReturn(new Package);

        $this->service->create($data, makeUser(['id' => 1, 'role' => 'mitra']));
    });
});

describe('BVA — validate harga_per_rombongan + max_peserta', function () {
    it('harga_per_rombongan > 0 + max_peserta kosong → ValidationException', function () {
        $data = validPackageData([
            'harga_per_rombongan' => 5_000_000,
            'max_peserta' => null,
        ]);

        expect(fn () => $this->service->create($data, makeUser(['id' => 1, 'role' => 'mitra'])))
            ->toThrow(ValidationException::class);
    });

    it('harga_per_rombongan > 0 + max_peserta ada → lolos', function () {
        $data = validPackageData([
            'harga_per_rombongan' => 5_000_000,
            'max_peserta' => 20,
        ]);

        $this->repo->shouldReceive('slugExists')->andReturn(false);
        $this->repo->shouldReceive('generateKode')->andReturn('PKG-2026-001');
        $this->repo->shouldReceive('create')->once()->andReturn(new Package);

        $this->service->create($data, makeUser(['id' => 1, 'role' => 'mitra']));
    });

    it('harga_per_rombongan = null → tidak validasi max_peserta', function () {
        $data = validPackageData(['harga_per_rombongan' => null, 'max_peserta' => null]);

        $this->repo->shouldReceive('slugExists')->andReturn(false);
        $this->repo->shouldReceive('generateKode')->andReturn('PKG-2026-001');
        $this->repo->shouldReceive('create')->once()->andReturn(new Package);

        $this->service->create($data, makeUser(['id' => 1, 'role' => 'mitra']));
    });

    it('harga_per_rombongan = 0 → tidak validasi max_peserta', function () {
        $data = validPackageData(['harga_per_rombongan' => 0, 'max_peserta' => null]);

        $this->repo->shouldReceive('slugExists')->andReturn(false);
        $this->repo->shouldReceive('generateKode')->andReturn('PKG-2026-001');
        $this->repo->shouldReceive('create')->once()->andReturn(new Package);

        $this->service->create($data, makeUser(['id' => 1, 'role' => 'mitra']));
    });
});

/*
|==========================================================================
| KATEGORI 2 — EQUIVALENCE
|==========================================================================
*/

describe('Equivalence — create with/without cover', function () {
    it('create tanpa cover → cover_image = null di payload', function () {
        $this->repo->shouldReceive('slugExists')->andReturn(false);
        $this->repo->shouldReceive('generateKode')->andReturn('PKG-2026-001');
        $this->repo->shouldReceive('create')
            ->once()
            ->with(Mockery::on(fn ($p) => $p['cover_image'] === null))
            ->andReturn(new Package);

        $this->service->create(validPackageData(), makeUser(['id' => 1, 'role' => 'mitra']));
    });

    it('create dengan cover UploadedFile → store "packages" di disk public', function () {
        $file = UploadedFile::fake()->image('cover.jpg');

        $this->repo->shouldReceive('slugExists')->andReturn(false);
        $this->repo->shouldReceive('generateKode')->andReturn('PKG-2026-001');
        $this->repo->shouldReceive('create')
            ->once()
            ->with(Mockery::on(fn ($p) => is_string($p['cover_image']) && str_starts_with($p['cover_image'], 'packages/')))
            ->andReturn(new Package);

        $this->service->create(validPackageData(['cover_image' => $file]), makeUser(['id' => 1, 'role' => 'mitra']));
    });
});

describe('Equivalence — update nama berubah vs sama', function () {
    it('update nama sama → tidak regenerate slug', function () {
        $package = makePackageMock(['nama' => 'Sama']);

        $this->repo->shouldReceive('slugExists')->never();
        $this->repo->shouldReceive('update')
            ->once()
            ->with($package, Mockery::on(fn ($p) => ! isset($p['slug'])))
            ->andReturn($package);

        $this->service->update($package, validPackageData(['nama' => 'Sama']));
    });

    it('update nama berubah → regenerate slug', function () {
        $package = makePackageMock(['nama' => 'Lama']);

        $this->repo->shouldReceive('slugExists')->with('baru', 5)->andReturn(false);
        $this->repo->shouldReceive('update')
            ->once()
            ->with($package, Mockery::on(fn ($p) => $p['slug'] === 'baru'))
            ->andReturn($package);

        $this->service->update($package, validPackageData(['nama' => 'Baru']));
    });
});

/*
|==========================================================================
| KATEGORI 3 — EDGE CASES
|==========================================================================
*/

describe('Edge Cases — update cover handling', function () {
    it('remove_cover_image = "1" + cover lama ada → hapus + set null', function () {
        Storage::disk('public')->put('packages/old.jpg', 'content');
        $package = makePackageMock(['cover_image' => 'packages/old.jpg', 'nama' => 'Sama']);

        $this->repo->shouldReceive('slugExists')->never();
        $this->repo->shouldReceive('update')
            ->once()
            ->with($package, Mockery::on(fn ($p) => $p['cover_image'] === null))
            ->andReturn($package);

        $this->service->update($package, validPackageData(['nama' => 'Sama', 'remove_cover_image' => '1']));

        Storage::disk('public')->assertMissing('packages/old.jpg');
    });

    it('remove_cover_image = 0 (int) → tidak hapus cover', function () {
        Storage::disk('public')->put('packages/old.jpg', 'content');
        $package = makePackageMock(['cover_image' => 'packages/old.jpg', 'nama' => 'Sama']);

        $this->repo->shouldReceive('slugExists')->never();
        $this->repo->shouldReceive('update')
            ->once()
            ->with($package, Mockery::on(fn ($p) => ! isset($p['cover_image'])))
            ->andReturn($package);

        $this->service->update($package, validPackageData(['nama' => 'Sama', 'remove_cover_image' => 0]));

        Storage::disk('public')->assertExists('packages/old.jpg');
    });

    it('update cover baru → hapus cover lama + simpan baru', function () {
        Storage::disk('public')->put('packages/old.jpg', 'content');
        $package = makePackageMock(['cover_image' => 'packages/old.jpg', 'nama' => 'Sama']);
        $file = UploadedFile::fake()->image('new.jpg');

        $this->repo->shouldReceive('slugExists')->never();
        $this->repo->shouldReceive('update')
            ->once()
            ->with($package, Mockery::on(fn ($p) => is_string($p['cover_image']) && str_starts_with($p['cover_image'], 'packages/')))
            ->andReturn($package);

        $this->service->update($package, validPackageData(['nama' => 'Sama', 'cover_image' => $file]));

        Storage::disk('public')->assertMissing('packages/old.jpg');
    });
});

describe('Edge Cases — updateWithRelations null/invalid JSON', function () {
    it('updateWithRelations days = null → hanya delete, tidak create', function () {
        $package = makePackageMock(['nama' => 'Sama']);

        $hasManyDays = Mockery::mock(HasMany::class);
        $hasManyDays->shouldReceive('delete')->once();
        $package->shouldReceive('days')->andReturn($hasManyDays);

        $hasManyFacilities = Mockery::mock(HasMany::class);
        $hasManyFacilities->shouldReceive('delete')->once();
        $package->shouldReceive('facilities')->andReturn($hasManyFacilities);

        $this->repo->shouldReceive('slugExists')->never();
        $this->repo->shouldReceive('update')->once()->andReturn($package);

        $package->shouldReceive('refresh')->andReturnSelf();
        $package->shouldReceive('load')->andReturnSelf();

        $this->service->updateWithRelations($package, validPackageData([
            'nama' => 'Sama',
            'days' => null,
            'facilities' => null,
        ]));
    });

    it('updateWithRelations days = "invalid json" → tidak crash', function () {
        $package = makePackageMock(['nama' => 'Sama']);

        $hasManyDays = Mockery::mock(HasMany::class);
        $hasManyDays->shouldReceive('delete')->once();
        $package->shouldReceive('days')->andReturn($hasManyDays);

        $hasManyFacilities = Mockery::mock(HasMany::class);
        $hasManyFacilities->shouldReceive('delete')->once();
        $package->shouldReceive('facilities')->andReturn($hasManyFacilities);

        $this->repo->shouldReceive('slugExists')->never();
        $this->repo->shouldReceive('update')->once()->andReturn($package);

        $package->shouldReceive('refresh')->andReturnSelf();
        $package->shouldReceive('load')->andReturnSelf();

        $this->service->updateWithRelations($package, validPackageData([
            'nama' => 'Sama',
            'days' => 'invalid json',
            'facilities' => 'invalid json',
        ]));
    });
});

/*
|==========================================================================
| KATEGORI 4 — HAPPY PATH
|==========================================================================
*/

describe('Happy Path — create', function () {
    it('create → return Package, status DRAFT', function () {
        $mitra = makeUser(['id' => 1, 'role' => 'mitra']);
        $created = makePackageMock(['status' => PackageStatus::DRAFT]);

        $this->repo->shouldReceive('slugExists')->andReturn(false);
        $this->repo->shouldReceive('generateKode')->once()->with((int) now()->year)->andReturn('PKG-2026-001');
        $this->repo->shouldReceive('create')
            ->once()
            ->with(Mockery::on(fn ($p) => $p['status'] === PackageStatus::DRAFT->value
                && $p['kode'] === 'PKG-2026-001'
                && $p['mitra_id'] === 1
                && $p['slug'] === 'paket-baru'))
            ->andReturn($created);

        $result = $this->service->create(validPackageData(), $mitra);

        expect($result)->toBe($created);
    });
});

describe('Happy Path — update', function () {
    it('update semua field + slug regenerated (nama berubah)', function () {
        $package = makePackageMock(['nama' => 'Lama']);

        $this->repo->shouldReceive('slugExists')->andReturn(false);
        $this->repo->shouldReceive('update')
            ->once()
            ->with($package, Mockery::on(fn ($p) => $p['nama'] === 'Baru'
                && $p['slug'] === 'baru'))
            ->andReturn($package);

        $result = $this->service->update($package, validPackageData(['nama' => 'Baru']));

        expect($result)->toBe($package);
    });
});

describe('Happy Path — updateWithRelations', function () {
    it('update package + sync days + sync facilities (JSON null, tidak butuh DB)', function () {
        $package = makePackageMock(['nama' => 'Sama']);

        $this->repo->shouldReceive('slugExists')->never();
        $this->repo->shouldReceive('update')->once()->andReturn($package);

        $hasManyDays = Mockery::mock(HasMany::class);
        $hasManyDays->shouldReceive('delete')->once();
        $package->shouldReceive('days')->andReturn($hasManyDays);

        $hasManyFacilities = Mockery::mock(HasMany::class);
        $hasManyFacilities->shouldReceive('delete')->once();
        $package->shouldReceive('facilities')->andReturn($hasManyFacilities);

        $package->shouldReceive('refresh')->once()->andReturnSelf();
        $package->shouldReceive('load')->once()->with(['days', 'facilities'])->andReturnSelf();

        // JSON null → skip PackageDay::create, PackageFacility::create
        // Test flow (refresh + load), bukan persistence
        $this->service->updateWithRelations($package, validPackageData([
            'nama' => 'Sama',
            'days' => null,
            'facilities' => null,
        ]));
    });
});

describe('Happy Path — publish', function () {
    it('publish sukses → status AKTIF', function () {
        $package = makePackageMock(['status' => PackageStatus::DRAFT, 'durasi_hari' => 1]);

        $package->shouldReceive('days')->andReturn(Mockery::mock(HasMany::class, function ($m) {
            $m->shouldReceive('count')->andReturn(1);
        }));
        $package->shouldReceive('facilities')->andReturnUsing(function () {
            $hasMany = Mockery::mock(HasMany::class);
            $hasMany->shouldReceive('where')->andReturnSelf();
            $hasMany->shouldReceive('exists')->andReturn(true);

            return $hasMany;
        });

        $this->repo->shouldReceive('update')
            ->once()
            ->with($package, ['status' => PackageStatus::AKTIF->value])
            ->andReturn($package);

        $this->service->publish($package);
    });
});

describe('Happy Path — archive / restore / delete', function () {
    it('archive dari AKTIF → ARSIP', function () {
        $package = makePackageMock(['status' => PackageStatus::AKTIF]);

        $this->repo->shouldReceive('update')
            ->once()
            ->with($package, ['status' => PackageStatus::ARSIP->value])
            ->andReturn($package);

        $this->service->archive($package);
    });

    it('restore dari ARSIP → DRAFT', function () {
        $package = makePackageMock(['status' => PackageStatus::ARSIP]);

        $this->repo->shouldReceive('update')
            ->once()
            ->with($package, ['status' => PackageStatus::DRAFT->value])
            ->andReturn($package);

        $this->service->restore($package);
    });

    it('delete tanpa booking → repo delete dipanggil', function () {
        $package = makePackageMock();

        $package->shouldReceive('bookings')->andReturn(Mockery::mock(HasMany::class, function ($m) {
            $m->shouldReceive('exists')->andReturn(false);
        }));

        $this->repo->shouldReceive('delete')->once()->with($package);

        $this->service->delete($package);
    });
});

/*
|==========================================================================
| KATEGORI 5 — NEGATIVE PATH
|==========================================================================
*/

describe('Negative Path — findDetail', function () {
    it('findDetail → null → ValidationException', function () {
        $this->repo->shouldReceive('findById')->with(999)->andReturn(null);

        expect(fn () => $this->service->findDetail(999))
            ->toThrow(ValidationException::class);
    });
});

describe('Negative Path — publish state', function () {
    it('publish sudah AKTIF → ValidationException', function () {
        $package = makePackageMock(['status' => PackageStatus::AKTIF]);

        expect(fn () => $this->service->publish($package))
            ->toThrow(ValidationException::class);
    });

    it('publish days < durasi_hari → ValidationException', function () {
        $package = makePackageMock(['status' => PackageStatus::DRAFT, 'durasi_hari' => 3]);

        $package->shouldReceive('days')->andReturn(Mockery::mock(HasMany::class, function ($m) {
            $m->shouldReceive('count')->andReturn(1);
        }));

        expect(fn () => $this->service->publish($package))
            ->toThrow(ValidationException::class);
    });

    it('publish tanpa fasilitas include → ValidationException', function () {
        $package = makePackageMock(['status' => PackageStatus::DRAFT, 'durasi_hari' => 1]);

        $package->shouldReceive('days')->andReturn(Mockery::mock(HasMany::class, function ($m) {
            $m->shouldReceive('count')->andReturn(1);
        }));

        $hasMany = Mockery::mock(HasMany::class);
        $hasMany->shouldReceive('where')->with('tipe', 'include')->andReturnSelf();
        $hasMany->shouldReceive('exists')->andReturn(false);
        $package->shouldReceive('facilities')->andReturn($hasMany);

        expect(fn () => $this->service->publish($package))
            ->toThrow(ValidationException::class);
    });

    it('publish tanpa fasilitas exclude → ValidationException', function () {
        $package = makePackageMock(['status' => PackageStatus::DRAFT, 'durasi_hari' => 1]);

        $package->shouldReceive('days')->andReturn(Mockery::mock(HasMany::class, function ($m) {
            $m->shouldReceive('count')->andReturn(1);
        }));

        $callCount = 0;
        $hasMany = Mockery::mock(HasMany::class);
        $hasMany->shouldReceive('where')->andReturnSelf();
        $hasMany->shouldReceive('exists')->andReturnUsing(function () use (&$callCount) {
            $callCount++;

            return $callCount === 1;
        });
        $package->shouldReceive('facilities')->andReturn($hasMany);

        expect(fn () => $this->service->publish($package))
            ->toThrow(ValidationException::class);
    });
});

describe('Negative Path — archive / restore / delete', function () {
    it('archive sudah ARSIP → ValidationException', function () {
        $package = makePackageMock(['status' => PackageStatus::ARSIP]);

        expect(fn () => $this->service->archive($package))
            ->toThrow(ValidationException::class);
    });

    it('restore bukan ARSIP → ValidationException', function () {
        $package = makePackageMock(['status' => PackageStatus::DRAFT]);

        expect(fn () => $this->service->restore($package))
            ->toThrow(ValidationException::class);
    });

    it('delete dengan booking → ValidationException', function () {
        $package = makePackageMock();

        $package->shouldReceive('bookings')->andReturn(Mockery::mock(HasMany::class, function ($m) {
            $m->shouldReceive('exists')->andReturn(true);
        }));

        $this->repo->shouldReceive('delete')->never();

        expect(fn () => $this->service->delete($package))
            ->toThrow(ValidationException::class);
    });
});

/*
|==========================================================================
| KATEGORI 6 — PASSTHROUGH
|==========================================================================
*/

describe('Passthrough — READ methods', function () {
    it('paginate pass-through', function () {
        $paginator = Mockery::mock(LengthAwarePaginator::class);

        $this->repo->shouldReceive('paginate')
            ->once()
            ->with(['search' => 'bali'], 25)
            ->andReturn($paginator);

        expect($this->service->paginate(['search' => 'bali'], 25))->toBe($paginator);
    });

    it('allAktif pass-through', function () {
        $collection = new Collection;

        $this->repo->shouldReceive('allAktif')->once()->andReturn($collection);

        expect($this->service->allAktif())->toBe($collection);
    });

    it('getLatestForClient pass-through', function () {
        $collection = new Collection;

        $this->repo->shouldReceive('getLatestForClient')->once()->with(4)->andReturn($collection);

        expect($this->service->getLatestForClient())->toBe($collection);
    });

    it('countByStatus pass-through', function () {
        $this->repo->shouldReceive('countByStatus')
            ->once()
            ->andReturn(['draft' => 2, 'aktif' => 5]);

        expect($this->service->countByStatus())
            ->toBe(['draft' => 2, 'aktif' => 5]);
    });
});
