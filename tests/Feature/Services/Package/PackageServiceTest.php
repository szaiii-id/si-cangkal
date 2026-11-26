<?php

use App\Enums\PackageKategori;
use App\Enums\PackageStatus;
use App\Models\Package;
use App\Models\PackageDay;
use App\Models\PackageFacility;
use App\Models\User;
use App\Services\Package\PackageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Psr\Log\LoggerInterface;

uses(RefreshDatabase::class);

/*
|==========================================================================
| SETUP — Integration Test
|==========================================================================
|
| PackageService — persistence `Package::create` + `PackageDay::create` +
| `PackageFacility::create` + sync.
|
| Yang di-test di sini (yang di-skip di unit test):
| - `create` — persistence `Package::create` + `PackageDay::create`
|   via `updateWithRelations`.
| - `updateWithRelations` — sync days + facilities (delete + recreate).
| - `publish` — status transition + validasi (butuh days + facilities).
|
| Catatan:
| - `Log::channel('audit')` di-mock — `AuditLogger::writer()`.
| - `Log::info` di-mock — `PackageService` panggil langsung.
| - `Storage::fake('public')` — untuk cover image.
|
*/

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('r2_public');

    // Mock Log::channel('audit') — AuditLogger::writer()
    $auditLoggerMock = Mockery::mock(LoggerInterface::class);
    $auditLoggerMock->shouldReceive('info')->byDefault()->andReturnNull();

    Log::shouldReceive('channel')->with('audit')->byDefault()->andReturn($auditLoggerMock);
    Log::shouldReceive('driver')->byDefault()->andReturn($auditLoggerMock);
    Log::shouldReceive('info')->byDefault()->andReturnNull();

    $this->service = app(PackageService::class);

    $this->mitra = User::factory()->mitra()->create([
        'must_change_password' => false,
    ]);
});

/*
|==========================================================================
| KATEGORI 1 — CREATE (PERSISTENCE)
|==========================================================================
*/

describe('create — persistence', function () {
    it('create → Package tersimpan di DB', function () {
        $package = $this->service->create(validPackagePayload(), $this->mitra);

        $this->assertDatabaseHas('packages', [
            'id' => $package->id,
            'mitra_id' => $this->mitra->id,
            'nama' => 'Paket Wisata Test',
            'status' => PackageStatus::DRAFT->value,
            'kategori' => PackageKategori::WISATA_ALAM->value,
        ]);

        expect($package->kode)->toStartWith('PKG-')
            ->and($package->slug)->toBe('paket-wisata-test');
    });

    it('create → slug unik jika nama sama', function () {
        $package1 = $this->service->create(validPackagePayload(['nama' => 'Paket Sama']), $this->mitra);
        $package2 = $this->service->create(validPackagePayload(['nama' => 'Paket Sama']), $this->mitra);

        expect($package1->slug)->toBe('paket-sama')
            ->and($package2->slug)->toBe('paket-sama-2');
    });

    it('create dengan cover → file tersimpan di storage', function () {
        $file = UploadedFile::fake()->image('cover.jpg', 800, 600);

        $package = $this->service->create(validPackagePayload([
            'cover_image' => $file,
        ]), $this->mitra);

        expect($package->cover_image)->toStartWith('packages/')
            ->and($package->cover_image)->toEndWith('.jpg');

        Storage::disk('r2_public')->assertExists($package->cover_image);
    });

    it('create → validasi durasi_malam >= durasi_hari → ValidationException', function () {
        expect(fn () => $this->service->create(validPackagePayload([
            'durasi_hari' => 2,
            'durasi_malam' => 2,
        ]), $this->mitra))->toThrow(ValidationException::class);
    });

    it('create → harga_per_rombongan > 0 tanpa max_peserta → ValidationException', function () {
        expect(fn () => $this->service->create(validPackagePayload([
            'harga_per_rombongan' => 5_000_000,
            'max_peserta' => null,
        ]), $this->mitra))->toThrow(ValidationException::class);
    });
});

/*
|==========================================================================
| KATEGORI 2 — UPDATE WITH RELATIONS (PERSISTENCE)
|==========================================================================
*/

describe('updateWithRelations — persistence', function () {
    it('updateWithRelations dengan days JSON → PackageDay tersimpan', function () {
        $package = $this->service->create(validPackagePayload(), $this->mitra);

        $daysJson = json_encode([
            ['judul' => 'Hari 1', 'deskripsi' => 'Deskripsi 1'],
            ['judul' => 'Hari 2', 'deskripsi' => 'Deskripsi 2'],
        ]);

        $this->service->updateWithRelations($package, validPackagePayload([
            'days' => $daysJson,
        ]));

        $this->assertDatabaseHas('package_days', [
            'package_id' => $package->id,
            'hari_ke' => 1,
            'judul' => 'Hari 1',
        ]);
        $this->assertDatabaseHas('package_days', [
            'package_id' => $package->id,
            'hari_ke' => 2,
            'judul' => 'Hari 2',
        ]);
    });

    it('updateWithRelations dengan facilities JSON → PackageFacility tersimpan', function () {
        $package = $this->service->create(validPackagePayload(), $this->mitra);

        $facilitiesJson = json_encode([
            ['tipe' => 'include', 'nama' => 'Transportasi'],
            ['tipe' => 'include', 'nama' => 'Makan'],
            ['tipe' => 'exclude', 'nama' => 'Tiket Pesawat'],
        ]);

        $this->service->updateWithRelations($package, validPackagePayload([
            'facilities' => $facilitiesJson,
        ]));

        $this->assertDatabaseHas('package_facilities', [
            'package_id' => $package->id,
            'tipe' => 'include',
            'nama' => 'Transportasi',
            'urutan' => 1,
        ]);
        $this->assertDatabaseHas('package_facilities', [
            'package_id' => $package->id,
            'tipe' => 'include',
            'nama' => 'Makan',
            'urutan' => 2,
        ]);
        $this->assertDatabaseHas('package_facilities', [
            'package_id' => $package->id,
            'tipe' => 'exclude',
            'nama' => 'Tiket Pesawat',
            'urutan' => 1,
        ]);
    });

    it('updateWithRelations → sync days (delete lama + create baru)', function () {
        $package = $this->service->create(validPackagePayload(), $this->mitra);

        // Hari lama
        PackageDay::factory()->create([
            'package_id' => $package->id,
            'hari_ke' => 1,
            'judul' => 'Hari Lama',
        ]);

        // Update — hari baru
        $daysJson = json_encode([
            ['judul' => 'Hari Baru 1'],
            ['judul' => 'Hari Baru 2'],
        ]);

        $this->service->updateWithRelations($package, validPackagePayload([
            'days' => $daysJson,
        ]));

        $days = PackageDay::where('package_id', $package->id)->get();

        expect($days)->toHaveCount(2)
            ->and($days[0]->judul)->toBe('Hari Baru 1')
            ->and($days[1]->judul)->toBe('Hari Baru 2');
    });

    it('updateWithRelations → sync facilities (delete lama + create baru)', function () {
        $package = $this->service->create(validPackagePayload(), $this->mitra);

        // Fasilitas lama
        PackageFacility::factory()->create([
            'package_id' => $package->id,
            'tipe' => 'include',
            'nama' => 'Fasilitas Lama',
        ]);

        $facilitiesJson = json_encode([
            ['tipe' => 'include', 'nama' => 'Fasilitas Baru'],
        ]);

        $this->service->updateWithRelations($package, validPackagePayload([
            'facilities' => $facilitiesJson,
        ]));

        $facilities = PackageFacility::where('package_id', $package->id)->get();

        expect($facilities)->toHaveCount(1)
            ->and($facilities[0]->nama)->toBe('Fasilitas Baru');
    });

    it('updateWithRelations days null → delete days lama, tidak create baru', function () {
        $package = $this->service->create(validPackagePayload(), $this->mitra);

        PackageDay::factory()->create(['package_id' => $package->id, 'hari_ke' => 1]);

        $this->service->updateWithRelations($package, validPackagePayload(['days' => null]));

        expect(PackageDay::where('package_id', $package->id)->count())->toBe(0);
    });
});

/*
|==========================================================================
| KATEGORI 3 — PUBLISH (PERSISTENCE)
|==========================================================================
*/

describe('publish — persistence', function () {
    it('publish dengan days + facilities lengkap → status AKTIF', function () {
        $package = $this->service->create(validPackagePayload([
            'durasi_hari' => 2,
            'durasi_malam' => 1,
        ]), $this->mitra);

        // Setup days
        PackageDay::factory()->create(['package_id' => $package->id, 'hari_ke' => 1]);
        PackageDay::factory()->create(['package_id' => $package->id, 'hari_ke' => 2]);

        // Setup facilities
        PackageFacility::factory()->create(['package_id' => $package->id, 'tipe' => 'include']);
        PackageFacility::factory()->create(['package_id' => $package->id, 'tipe' => 'exclude']);

        $result = $this->service->publish($package);

        $this->assertDatabaseHas('packages', [
            'id' => $package->id,
            'status' => PackageStatus::AKTIF->value,
        ]);

        expect($result->status)->toBe(PackageStatus::AKTIF);
    });

    it('publish tanpa days lengkap → ValidationException', function () {
        $package = $this->service->create(validPackagePayload([
            'durasi_hari' => 2,
            'durasi_malam' => 1,
        ]), $this->mitra);

        // Hanya 1 day (durasi 2)
        PackageDay::factory()->create(['package_id' => $package->id, 'hari_ke' => 1]);

        expect(fn () => $this->service->publish($package))
            ->toThrow(ValidationException::class);
    });

    it('publish tanpa fasilitas include → ValidationException', function () {
        $package = $this->service->create(validPackagePayload(), $this->mitra);

        PackageDay::factory()->create(['package_id' => $package->id, 'hari_ke' => 1]);
        PackageDay::factory()->create(['package_id' => $package->id, 'hari_ke' => 2]);
        PackageFacility::factory()->create(['package_id' => $package->id, 'tipe' => 'exclude']);

        expect(fn () => $this->service->publish($package))
            ->toThrow(ValidationException::class);
    });

    it('publish tanpa fasilitas exclude → ValidationException', function () {
        $package = $this->service->create(validPackagePayload(), $this->mitra);

        PackageDay::factory()->create(['package_id' => $package->id, 'hari_ke' => 1]);
        PackageDay::factory()->create(['package_id' => $package->id, 'hari_ke' => 2]);
        PackageFacility::factory()->create(['package_id' => $package->id, 'tipe' => 'include']);

        expect(fn () => $this->service->publish($package))
            ->toThrow(ValidationException::class);
    });
});

/*
|==========================================================================
| KATEGORI 4 — ARCHIVE / RESTORE / DELETE (PERSISTENCE)
|==========================================================================
*/

describe('archive / restore / delete — persistence', function () {
    it('archive → status ARSIP', function () {
        $package = $this->service->create(validPackagePayload(), $this->mitra);
        $package->update(['status' => PackageStatus::AKTIF->value]);

        $result = $this->service->archive($package->fresh());

        $this->assertDatabaseHas('packages', [
            'id' => $package->id,
            'status' => PackageStatus::ARSIP->value,
        ]);
    });

    it('restore → status DRAFT', function () {
        $package = $this->service->create(validPackagePayload(), $this->mitra);
        $package->update(['status' => PackageStatus::ARSIP->value]);

        $result = $this->service->restore($package->fresh());

        $this->assertDatabaseHas('packages', [
            'id' => $package->id,
            'status' => PackageStatus::DRAFT->value,
        ]);
    });

    it('delete tanpa booking → soft delete', function () {
        $package = $this->service->create(validPackagePayload(), $this->mitra);

        $this->service->delete($package);

        $this->assertSoftDeleted('packages', ['id' => $package->id]);
    });
});
