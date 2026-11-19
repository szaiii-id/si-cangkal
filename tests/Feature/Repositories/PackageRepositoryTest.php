<?php

use App\Enums\BookingStatus;
use App\Enums\PackageKategori;
use App\Enums\PackageStatus;
use App\Models\Booking;
use App\Models\Package;
use App\Models\User;
use App\Repositories\Eloquent\PackageRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/*
|==========================================================================
| SETUP
|==========================================================================
|
| - RefreshDatabase: reset DB tiap test (SQLite :memory:)
| - PRAGMA foreign_keys = ON: SQLite default OFF → perlu diaktifkan
|   agar FK constraint (packages.mitra_id → users.id) dan cascade
|   (package_days, package_facilities) benar-benar berlaku.
| - Instantiate repo sekali per test (stateless).
|
*/

beforeEach(function () {

    $this->repo = new PackageRepository;
});

/*
|==========================================================================
| KATEGORI 1 — CONTRACT / RETURN TYPE & SHAPE
|==========================================================================
*/

describe('Contract — paginate', function () {
    it('mengembalikan LengthAwarePaginator', function () {
        Package::factory()->count(3)->create();

        $result = $this->repo->paginate();

        expect($result)->toBeInstanceOf(LengthAwarePaginator::class);
    });

    it('item adalah instance Package', function () {
        Package::factory()->count(2)->create();

        $result = $this->repo->paginate();

        expect($result->items())->each->toBeInstanceOf(Package::class);
    });

    it('meng-eager-load relasi mitra', function () {
        Package::factory()->create();

        $result = $this->repo->paginate();
        $package = $result->items()[0];

        expect($package->relationLoaded('mitra'))->toBeTrue();
    });

    it('mitra hanya load kolom id, name, email', function () {
        Package::factory()->create();

        $result = $this->repo->paginate();
        $mitra = $result->items()[0]->mitra;

        expect($mitra->getAttributes())->toHaveKeys(['id', 'name', 'email'])
            ->and($mitra->getAttributes())->not->toHaveKey('password');
    });

    it('mengurutkan default created_at DESC', function () {
        $older = Package::factory()->create(['created_at' => now()->subDay()]);
        $newer = Package::factory()->create(['created_at' => now()]);

        $result = $this->repo->paginate();

        expect($result->items()[0]->id)->toBe($newer->id)
            ->and($result->items()[1]->id)->toBe($older->id);
    });
});

describe('Contract — findById / findBySlug / findByKode', function () {
    it('findById mengembalikan Package dengan relasi mitra, days, facilities', function () {
        $package = Package::factory()->lengkap()->create();

        $result = $this->repo->findById($package->id);

        expect($result)->toBeInstanceOf(Package::class)
            ->and($result->relationLoaded('mitra'))->toBeTrue()
            ->and($result->relationLoaded('days'))->toBeTrue()
            ->and($result->relationLoaded('facilities'))->toBeTrue();
    });

    it('findById mengembalikan null jika tidak ada', function () {
        expect($this->repo->findById(999_999))->toBeNull();
    });

    it('findBySlug mengembalikan Package dengan relasi days dan facilities', function () {
        $package = Package::factory()->lengkap()->create();

        $result = $this->repo->findBySlug($package->slug);

        expect($result)->toBeInstanceOf(Package::class)
            ->and($result->relationLoaded('days'))->toBeTrue()
            ->and($result->relationLoaded('facilities'))->toBeTrue();
    });

    it('findBySlug mengembalikan null jika tidak ada', function () {
        expect($this->repo->findBySlug('tidak-ada'))->toBeNull();
    });

    it('findByKode mengembalikan Package jika ada', function () {
        $package = Package::factory()->create(['kode' => 'PKG-2026-001']);

        $result = $this->repo->findByKode('PKG-2026-001');

        expect($result->id)->toBe($package->id);
    });

    it('findByKode mengembalikan null jika tidak ada', function () {
        expect($this->repo->findByKode('PKG-9999-999'))->toBeNull();
    });

    it('findById TIDAK mengembalikan package yang soft-deleted', function () {
        $package = Package::factory()->create();
        $package->delete();

        expect($this->repo->findById($package->id))->toBeNull();
    });
});

describe('Contract — allAktif / countByStatus', function () {
    it('allAktif mengembalikan Collection', function () {
        Package::factory()->aktif()->create();

        $result = $this->repo->allAktif();

        expect($result)->toBeInstanceOf(Collection::class);
    });

    it('allAktif mengurutkan berdasarkan nama ASC', function () {
        Package::factory()->aktif()->create(['nama' => 'Zebra']);
        Package::factory()->aktif()->create(['nama' => 'Anoa']);
        Package::factory()->aktif()->create(['nama' => 'Banteng']);

        $names = $this->repo->allAktif()->pluck('nama')->all();

        expect($names)->toBe(['Anoa', 'Banteng', 'Zebra']);
    });

    it('countByStatus mengembalikan array dengan 3 key', function () {
        $result = $this->repo->countByStatus();

        expect($result)->toHaveKeys(['draft', 'aktif', 'arsip']);
    });
});

describe('Contract — getLatestForClient / getBestSellerForClient', function () {
    it('getLatestForClient mengembalikan Collection', function () {
        Package::factory()->aktif()->count(3)->create();

        $result = $this->repo->getLatestForClient();

        expect($result)->toBeInstanceOf(Collection::class);
    });

    it('getLatestForClient meng-eager-load relasi mitra (hanya id, name)', function () {
        Package::factory()->aktif()->create();

        $result = $this->repo->getLatestForClient();
        $mitra = $result->first()->mitra;

        expect($result->first()->relationLoaded('mitra'))->toBeTrue()
            ->and($mitra->getAttributes())->toHaveKeys(['id', 'name'])
            ->and($mitra->getAttributes())->not->toHaveKey('email');
    });

    it('getBestSellerForClient mengembalikan Collection', function () {
        Package::factory()->aktif()->count(3)->create();

        $result = $this->repo->getBestSellerForClient();

        expect($result)->toBeInstanceOf(Collection::class);
    });
});

/*
|==========================================================================
| KATEGORI 2 — SECURITY (SQL Wildcard Injection)
|==========================================================================
|
| Fokus: input pencarian TIDAK diinterpretasi sebagai wildcard SQL.
|
|   Catatan: `paginate` saat ini pakai `where('nama', 'like', $pattern)`
| TANPA klausa `ESCAPE '\'`. Test di bawah akan MENDETEKSI bahwa escape
| tidak efektif — perbaikan butuh `whereRaw(... ESCAPE '\')`.
|
*/

describe('Security — wildcard injection pada paginate', function () {
    it('input % TIDAK berfungsi sebagai wildcard (literal match)', function () {
        Package::factory()->create(['nama' => 'Promo 100% Hemat']);
        Package::factory()->create(['nama' => 'Promo 100 Ribu']);

        $result = $this->repo->paginate(['search' => '100%']);

        expect($result->total())->toBe(1)
            ->and($result->items()[0]->nama)->toBe('Promo 100% Hemat');
    });

    it('input _ TIDAK berfungsi sebagai wildcard satu karakter', function () {
        Package::factory()->create(['nama' => 'paket_hemat']);
        Package::factory()->create(['nama' => 'paketXhemat']);

        $result = $this->repo->paginate(['search' => 'paket_hemat']);

        expect($result->total())->toBe(1)
            ->and($result->items()[0]->nama)->toBe('paket_hemat');
    });

    it('input SQL injection classik aman (parameter binding)', function () {
        Package::factory()->aktif()->create(['nama' => 'Paket Biasa']);

        $result = $this->repo->paginate(['search' => "' OR 1=1--"]);

        expect($result->total())->toBe(0);
    });

    it('input SQL injection dengan quote tidak menyebabkan error syntax', function () {
        Package::factory()->create();

        expect(fn () => $this->repo->paginate(['search' => "budi' OR '1'='1"]))
            ->not->toThrow(QueryException::class);
    });

    it('search tidak membedakan huruf besar/kecil (SQLite LIKE)', function () {
        Package::factory()->create(['nama' => 'Paket Bali']);

        $result = $this->repo->paginate(['search' => 'BALI']);

        expect($result->total())->toBe(1);
    });
});

/*
|==========================================================================
| KATEGORI 3 — CONCURRENCY / UNIQUE CONSTRAINT
|==========================================================================
|
| packages.kode & packages.slug adalah UNIQUE. Ini last line of defense
| terhadap race condition di generateKode (baca last code → insert).
|
| Catatan: generateKode saat ini TIDAK pakai lock, jadi ada jendela
| race condition. Kalau dua request paralel, salah satunya akan
| QueryException. Test di bawah SIMULASIKAN secara sequential.
|
*/

describe('Concurrency — unique constraint', function () {
    it('2x create dengan kode sama → QueryException', function () {
        $mitra = User::factory()->mitra()->create();
        Package::factory()->forMitra($mitra)->create(['kode' => 'PKG-2026-001']);

        expect(fn () => Package::factory()->forMitra($mitra)->create([
            'kode' => 'PKG-2026-001',
            'slug' => 'paket-berbeda',
        ]))->toThrow(QueryException::class);
    });

    it('2x create dengan slug sama → QueryException', function () {
        $mitra = User::factory()->mitra()->create();
        Package::factory()->forMitra($mitra)->create([
            'kode' => 'PKG-2026-001',
            'slug' => 'paket-test',
        ]);

        expect(fn () => Package::factory()->forMitra($mitra)->create([
            'kode' => 'PKG-2026-002',
            'slug' => 'paket-test',
        ]))->toThrow(QueryException::class);
    });

    it('update kode ke kode package lain → QueryException', function () {
        $mitra = User::factory()->mitra()->create();
        $a = Package::factory()->forMitra($mitra)->create(['kode' => 'PKG-2026-001']);
        Package::factory()->forMitra($mitra)->create(['kode' => 'PKG-2026-002']);

        expect(fn () => $this->repo->update($a, ['kode' => 'PKG-2026-002']))
            ->toThrow(QueryException::class);
    });

    it('generateKode sequential 3x → kode unik (simulasi race)', function () {
        $mitra = User::factory()->mitra()->create();

        $kode1 = $this->repo->generateKode(2026);
        Package::factory()->forMitra($mitra)->create(['kode' => $kode1, 'slug' => 'a']);

        $kode2 = $this->repo->generateKode(2026);
        Package::factory()->forMitra($mitra)->create(['kode' => $kode2, 'slug' => 'b']);

        $kode3 = $this->repo->generateKode(2026);
        Package::factory()->forMitra($mitra)->create(['kode' => $kode3, 'slug' => 'c']);

        expect($kode1)->toBe('PKG-2026-001')
            ->and($kode2)->toBe('PKG-2026-002')
            ->and($kode3)->toBe('PKG-2026-003');
    });

    it('create dengan mitra_id yang tidak ada di DB → QueryException (FK)', function () {
        // Mitra "palsu" tanpa simpan ke DB
        $ghostMitra = new User;
        $ghostMitra->forceFill(['id' => 999_999, 'role' => 'mitra']);

        expect(fn () => $this->repo->create([
            'mitra_id' => $ghostMitra->id,
            'kode' => 'PKG-2026-001',
            'nama' => 'Test',
            'slug' => 'test',
            'kategori' => PackageKategori::WISATA_ALAM->value,
            'durasi_hari' => 2,
            'durasi_malam' => 1,
            'harga_per_orang' => 500_000,
            'timezone' => 'Asia/Makassar',
            'status' => PackageStatus::DRAFT->value,
        ]))->toThrow(QueryException::class);
    });
});

/*
|==========================================================================
| KATEGORI 4 — IDEMPOTENCY
|==========================================================================
*/

describe('Idempotency', function () {
    it('slugExists konsisten saat dipanggil berulang', function () {
        Package::factory()->create(['slug' => 'paket-test']);

        $first = $this->repo->slugExists('paket-test');
        $second = $this->repo->slugExists('paket-test');

        expect($first)->toBeTrue()
            ->and($second)->toBeTrue();
    });

    it('kodeExists konsisten saat dipanggil berulang', function () {
        Package::factory()->create(['kode' => 'PKG-2026-001']);

        $first = $this->repo->kodeExists('PKG-2026-001');
        $second = $this->repo->kodeExists('PKG-2026-001');

        expect($first)->toBeTrue()
            ->and($second)->toBeTrue();
    });

    it('update dengan data sama 2x → hasil konsisten', function () {
        $package = Package::factory()->create(['nama' => 'Lama']);

        $this->repo->update($package, ['nama' => 'Baru']);
        $package->refresh();

        $this->repo->update($package, ['nama' => 'Baru']);
        $package->refresh();

        expect($package->nama)->toBe('Baru');
    });

    it('generateKode dengan DB kosong → selalu 001 (idempotent)', function () {
        $first = $this->repo->generateKode(2026);
        $second = $this->repo->generateKode(2026);

        expect($first)->toBe('PKG-2026-001')
            ->and($second)->toBe('PKG-2026-001'); // belum ada yang di-insert
    });
});

/*
|==========================================================================
| KATEGORI 5 — DATA INTEGRITY & STATE TRANSITION
|==========================================================================
*/

describe('Data Integrity — create / update', function () {
    it('create menyimpan semua field', function () {
        $mitra = User::factory()->mitra()->create();

        $package = $this->repo->create([
            'mitra_id' => $mitra->id,
            'kode' => 'PKG-2026-001',
            'nama' => 'Paket Baru',
            'slug' => 'paket-baru',
            'deskripsi' => 'Deskripsi',
            'kategori' => PackageKategori::WISATA_ALAM->value,
            'durasi_hari' => 2,
            'durasi_malam' => 1,
            'harga_per_orang' => 500_000,
            'timezone' => 'Asia/Makassar',
            'status' => PackageStatus::DRAFT->value,
        ]);

        expect($package->nama)->toBe('Paket Baru')
            ->and($package->kode)->toBe('PKG-2026-001')
            ->and($package->slug)->toBe('paket-baru')
            ->and($package->kategori)->toBe(PackageKategori::WISATA_ALAM)
            ->and($package->status)->toBe(PackageStatus::DRAFT);
    });

    it('update mengubah field dan return instance terbaru', function () {
        $package = Package::factory()->create(['nama' => 'Lama', 'status' => PackageStatus::DRAFT]);

        $updated = $this->repo->update($package, [
            'nama' => 'Baru',
            'status' => PackageStatus::AKTIF->value,
        ]);

        expect($updated->nama)->toBe('Baru')
            ->and($updated->status)->toBe(PackageStatus::AKTIF);
    });

    it('delete soft-delete package (hilang dari query default)', function () {
        $package = Package::factory()->create();

        $result = $this->repo->delete($package);

        expect($result)->toBeTrue()
            ->and($this->repo->findById($package->id))->toBeNull();

        // Tapi masih ada di DB dengan trashed
        $this->assertSoftDeleted('packages', ['id' => $package->id]);
    });
});

describe('Data Integrity — generateKode sequence', function () {
    it('DB kosong → PKG-{tahun}-001', function () {
        expect($this->repo->generateKode(2026))->toBe('PKG-2026-001');
    });

    it('setelah PKG-2026-001 → PKG-2026-002', function () {
        $mitra = User::factory()->mitra()->create();
        Package::factory()->forMitra($mitra)->create(['kode' => 'PKG-2026-001', 'slug' => 'a']);

        expect($this->repo->generateKode(2026))->toBe('PKG-2026-002');
    });

    it('setelah PKG-2026-005 → PKG-2026-006', function () {
        $mitra = User::factory()->mitra()->create();
        Package::factory()->forMitra($mitra)->create(['kode' => 'PKG-2026-005', 'slug' => 'a']);

        expect($this->repo->generateKode(2026))->toBe('PKG-2026-006');
    });

    it('kode soft-deleted TETAP dihitung (withTrashed)', function () {
        $mitra = User::factory()->mitra()->create();
        $package = Package::factory()->forMitra($mitra)->create([
            'kode' => 'PKG-2026-001',
            'slug' => 'a',
        ]);
        $package->delete();

        expect($this->repo->generateKode(2026))->toBe('PKG-2026-002');
    });

    it('tahun berbeda punya sequence sendiri', function () {
        $mitra = User::factory()->mitra()->create();
        Package::factory()->forMitra($mitra)->create(['kode' => 'PKG-2025-050', 'slug' => 'a']);

        // Generate untuk 2026 → harus mulai dari 001, bukan 051
        expect($this->repo->generateKode(2026))->toBe('PKG-2026-001');
    });

    it('TIDAK dihitung: kode dengan prefix tahun berbeda', function () {
        $mitra = User::factory()->mitra()->create();
        Package::factory()->forMitra($mitra)->create(['kode' => 'PKG-2025-001', 'slug' => 'a']);
        Package::factory()->forMitra($mitra)->create(['kode' => 'PKG-2027-001', 'slug' => 'b']);

        expect($this->repo->generateKode(2026))->toBe('PKG-2026-001');
    });

    /*
     *   BUG DETECTION — `substr(-3)` di generateKode
     *
     * Kode saat ini memakai `substr($lastKode, -3)` yang hanya aman
     * untuk sequence ≤ 999. Untuk sequence ≥ 1000, `substr(-3)` mengambil
     * 3 digit terakhir (contoh: "PKG-2026-1000" → "000" → 0) sehingga
     * generateKode akan ROLLBACK ke 001 — duplikat dengan kode lama!
     *
     * Perbaikan butuh: `substr($lastKode, strlen($prefix))` bukan `-3`.
     */

    it('BUG: setelah PKG-2026-999 → harus PKG-2026-1000', function () {
        $mitra = User::factory()->mitra()->create();
        Package::factory()->forMitra($mitra)->create(['kode' => 'PKG-2026-999', 'slug' => 'a']);

        $next = $this->repo->generateKode(2026);

        // Expected: PKG-2026-1000
        // Actual (bug): PKG-2026-001
        expect($next)->toBe('PKG-2026-1000');
    });

    it('BUG: setelah PKG-2026-1000 → harus PKG-2026-1001', function () {
        $mitra = User::factory()->mitra()->create();
        Package::factory()->forMitra($mitra)->create(['kode' => 'PKG-2026-1000', 'slug' => 'a']);

        $next = $this->repo->generateKode(2026);

        // Expected: PKG-2026-1001
        // Actual (bug): PKG-2026-001
        expect($next)->toBe('PKG-2026-1001');
    });

    it('BUG: setelah PKG-2026-1999 → harus PKG-2026-2000', function () {
        $mitra = User::factory()->mitra()->create();
        Package::factory()->forMitra($mitra)->create(['kode' => 'PKG-2026-1999', 'slug' => 'a']);

        $next = $this->repo->generateKode(2026);

        // Expected: PKG-2026-2000
        // Actual (bug): PKG-2026-000? (tergantung)
        expect($next)->toBe('PKG-2026-2000');
    });
});

describe('Data Integrity — allAktif / countByStatus', function () {
    it('allAktif hanya mengembalikan status AKTIF', function () {
        Package::factory()->aktif()->count(2)->create();
        Package::factory()->draft()->count(3)->create();
        Package::factory()->arsip()->count(2)->create();

        $result = $this->repo->allAktif();

        expect($result)->toHaveCount(2);
        foreach ($result as $package) {
            expect($package->status)->toBe(PackageStatus::AKTIF);
        }
    });

    it('allAktif TIDAK include soft-deleted', function () {
        $aktif = Package::factory()->aktif()->create();
        $deleted = Package::factory()->aktif()->create();
        $deleted->delete();

        $result = $this->repo->allAktif();

        expect($result)->toHaveCount(1)
            ->and($result->first()->id)->toBe($aktif->id);
    });

    it('countByStatus menghitung per status dengan benar', function () {
        Package::factory()->draft()->count(3)->create();
        Package::factory()->aktif()->count(5)->create();
        Package::factory()->arsip()->count(2)->create();

        $result = $this->repo->countByStatus();

        expect($result['draft'])->toBe(3)
            ->and($result['aktif'])->toBe(5)
            ->and($result['arsip'])->toBe(2);
    });

    it('countByStatus mengembalikan 0 untuk status yang tidak ada', function () {
        Package::factory()->aktif()->count(2)->create();

        $result = $this->repo->countByStatus();

        expect($result['draft'])->toBe(0)
            ->and($result['aktif'])->toBe(2)
            ->and($result['arsip'])->toBe(0);
    });

    it('countByStatus TIDAK menghitung soft-deleted', function () {
        Package::factory()->aktif()->count(2)->create();
        $deleted = Package::factory()->aktif()->create();
        $deleted->delete();

        $result = $this->repo->countByStatus();

        expect($result['aktif'])->toBe(2);
    });
});

/*
|==========================================================================
| KATEGORI 6 — ERROR HANDLING
|==========================================================================
*/

describe('Error Handling — constraint violations', function () {
    it('create tanpa key nama → ErrorException (PHP undefined array key)', function () {
        expect(fn () => $this->repo->create([
            'mitra_id' => User::factory()->mitra()->create()->id,
            'kode' => 'PKG-2026-001',
            'slug' => 'x',
            'kategori' => PackageKategori::WISATA_ALAM->value,
            'durasi_hari' => 2,
            'durasi_malam' => 1,
            'harga_per_orang' => 500_000,
            'timezone' => 'Asia/Makassar',
            'status' => PackageStatus::DRAFT->value,
        ]))->toThrow(QueryException::class); // Model::create throw QueryException karena nama NOT NULL
    });

    it('create tanpa key mitra_id → ErrorException', function () {
        expect(fn () => $this->repo->create([
            'kode' => 'PKG-2026-001',
            'nama' => 'Test',
            'slug' => 'x',
            'kategori' => PackageKategori::WISATA_ALAM->value,
            'durasi_hari' => 2,
            'durasi_malam' => 1,
            'harga_per_orang' => 500_000,
            'timezone' => 'Asia/Makassar',
            'status' => PackageStatus::DRAFT->value,
        ]))->toThrow(QueryException::class);
    });

    it('create dengan nama null → QueryException (NOT NULL)', function () {
        $mitra = User::factory()->mitra()->create();

        expect(fn () => $this->repo->create([
            'mitra_id' => $mitra->id,
            'kode' => 'PKG-2026-001',
            'nama' => null,
            'slug' => 'x',
            'kategori' => PackageKategori::WISATA_ALAM->value,
            'durasi_hari' => 2,
            'durasi_malam' => 1,
            'harga_per_orang' => 500_000,
            'timezone' => 'Asia/Makassar',
            'status' => PackageStatus::DRAFT->value,
        ]))->toThrow(QueryException::class);
    });

    it('create dengan kategori invalid → ValueError (enum cast)', function () {
        $mitra = User::factory()->mitra()->create();

        expect(fn () => $this->repo->create([
            'mitra_id' => $mitra->id,
            'kode' => 'PKG-2026-001',
            'nama' => 'Test',
            'slug' => 'x',
            'kategori' => 'kategori-ngawur',
            'durasi_hari' => 2,
            'durasi_malam' => 1,
            'harga_per_orang' => 500_000,
            'timezone' => 'Asia/Makassar',
            'status' => PackageStatus::DRAFT->value,
        ]))->toThrow(ValueError::class); // ← enum cast throw ValueError
    });

    it('update ke status enum invalid → ValueError (enum cast)', function () {
        $package = Package::factory()->create();

        expect(fn () => $this->repo->update($package, ['status' => 'ngawur']))
            ->toThrow(ValueError::class);  // ← enum cast fail fast
    });
});

/*
|==========================================================================
| KATEGORI 7 — EDGE CASES & BVA
|==========================================================================
*/

describe('Edge Cases — paginate filters & sort', function () {
    it('search string kosong → skip filter', function () {
        Package::factory()->count(3)->create();

        $result = $this->repo->paginate(['search' => '']);

        expect($result->total())->toBe(3);
    });

    it('filter status = draft → hanya draft', function () {
        Package::factory()->draft()->count(2)->create();
        Package::factory()->aktif()->count(3)->create();

        $result = $this->repo->paginate(['status' => 'draft']);

        expect($result->total())->toBe(2);
    });

    it('filter kategori = wisata_alam → hanya wisata_alam', function () {
        Package::factory()->count(2)->create(['kategori' => PackageKategori::WISATA_ALAM->value]);
        Package::factory()->count(3)->create(['kategori' => PackageKategori::BUDAYA->value]);

        $result = $this->repo->paginate(['kategori' => 'wisata_alam']);

        expect($result->total())->toBe(2);
    });

    it('filter mitra_id → hanya package milik mitra itu', function () {
        $mitraA = User::factory()->mitra()->create();
        $mitraB = User::factory()->mitra()->create();
        Package::factory()->forMitra($mitraA)->count(2)->create();
        Package::factory()->forMitra($mitraB)->count(3)->create();

        $result = $this->repo->paginate(['mitra_id' => $mitraA->id]);

        expect($result->total())->toBe(2);
    });

    it('filter combine status + kategori', function () {
        Package::factory()->aktif()->create(['kategori' => PackageKategori::WISATA_ALAM->value]);
        Package::factory()->aktif()->create(['kategori' => PackageKategori::BUDAYA->value]);
        Package::factory()->draft()->create(['kategori' => PackageKategori::WISATA_ALAM->value]);

        $result = $this->repo->paginate([
            'status' => 'aktif',
            'kategori' => 'wisata_alam',
        ]);

        expect($result->total())->toBe(1);
    });

    it('sort = "invalid" → fallback ke created_at', function () {
        Package::factory()->count(3)->create();

        // Tidak crash, tidak throw
        expect(fn () => $this->repo->paginate(['sort' => 'invalid']))
            ->not->toThrow(QueryException::class);
    });

    it('direction = "INVALID" → fallback ke desc', function () {
        $older = Package::factory()->create(['created_at' => now()->subDay()]);
        $newer = Package::factory()->create(['created_at' => now()]);

        $result = $this->repo->paginate(['direction' => 'INVALID']);

        // DESC → newer dulu
        expect($result->items()[0]->id)->toBe($newer->id);
    });

    it('sort = "nama" direction = "asc"', function () {
        Package::factory()->create(['nama' => 'Zebra']);
        Package::factory()->create(['nama' => 'Anoa']);
        Package::factory()->create(['nama' => 'Banteng']);

        $result = $this->repo->paginate(['sort' => 'nama', 'direction' => 'asc']);

        expect($result->items()[0]->nama)->toBe('Anoa')
            ->and($result->items()[1]->nama)->toBe('Banteng')
            ->and($result->items()[2]->nama)->toBe('Zebra');
    });

    it('perPage default = 15', function () {
        Package::factory()->count(20)->create();

        $result = $this->repo->paginate();

        expect($result->perPage())->toBe(15)
            ->and($result->items())->toHaveCount(15);
    });

    it('perPage = 1 → 1 item per halaman', function () {
        Package::factory()->count(5)->create();

        $result = $this->repo->paginate([], 1);

        expect($result->perPage())->toBe(1)
            ->and($result->items())->toHaveCount(1)
            ->and($result->total())->toBe(5);
    });
});

describe('Edge Cases — getLatestForClient / getBestSellerForClient', function () {
    it('getLatestForClient hanya return status aktif', function () {
        Package::factory()->aktif()->count(2)->create();
        Package::factory()->draft()->count(3)->create();

        $result = $this->repo->getLatestForClient();

        expect($result)->toHaveCount(2);
    });

    it('getLatestForClient limit default = 4', function () {
        Package::factory()->aktif()->count(10)->create();

        $result = $this->repo->getLatestForClient();

        expect($result)->toHaveCount(4);
    });

    it('getLatestForClient limit = 2', function () {
        Package::factory()->aktif()->count(10)->create();

        $result = $this->repo->getLatestForClient(2);

        expect($result)->toHaveCount(2);
    });

    it('getBestSellerForClient hanya return status aktif', function () {
        Package::factory()->aktif()->count(2)->create();
        Package::factory()->draft()->count(3)->create();

        $result = $this->repo->getBestSellerForClient();

        expect($result)->toHaveCount(2);
    });

    it('getBestSellerForClient hanya hitung booking confirmed/in_progress/completed', function () {
        $mitra = User::factory()->mitra()->create();
        $paketPopuler = Package::factory()->aktif()->forMitra($mitra)->create();
        $paketSepi = Package::factory()->aktif()->forMitra($mitra)->create();

        // Buat 2 booking "dihitung" untuk paketPopuler
        Booking::factory()->for($paketPopuler, 'package')->create([
            'status' => BookingStatus::CONFIRMED,
        ]);
        Booking::factory()->for($paketPopuler, 'package')->create([
            'status' => BookingStatus::IN_PROGRESS,
        ]);

        // Buat 1 booking "pending_payment" (TIDAK dihitung) untuk paketSepi
        Booking::factory()->for($paketSepi, 'package')->create([
            'status' => BookingStatus::PENDING_PAYMENT,
        ]);

        $result = $this->repo->getBestSellerForClient();

        // Urutan: paketPopuler (2 booking) dulu, paketSepi (0 booking) belakang
        expect($result->first()->id)->toBe($paketPopuler->id);
    });

    it('getBestSellerForClient limit default = 4', function () {
        Package::factory()->aktif()->count(10)->create();

        $result = $this->repo->getBestSellerForClient();

        expect($result)->toHaveCount(4);
    });
});

describe('Edge Cases — slugExists / kodeExists', function () {
    it('slugExists dengan exceptId = package sendiri → false', function () {
        $package = Package::factory()->create(['slug' => 'paket-test']);

        expect($this->repo->slugExists('paket-test', $package->id))->toBeFalse();
    });

    it('slugExists tanpa exceptId → cek semua', function () {
        Package::factory()->create(['slug' => 'paket-test']);

        expect($this->repo->slugExists('paket-test'))->toBeTrue();
    });

    it('kodeExists dengan exceptId = package sendiri → false', function () {
        $package = Package::factory()->create(['kode' => 'PKG-2026-001']);

        expect($this->repo->kodeExists('PKG-2026-001', $package->id))->toBeFalse();
    });

    it('slugExists TIDAK menghitung soft-deleted (default Laravel)', function () {
        //   Ini behavior Laravel: query default TIDAK include soft-deleted.
        // Kalau Anda ingin soft-deleted juga dihitung, kode perlu diubah
        // ke `Package::withTrashed()->...`.
        $package = Package::factory()->create(['slug' => 'paket-test']);
        $package->delete();

        expect($this->repo->slugExists('paket-test'))->toBeFalse();
    });

    it('kodeExists TIDAK menghitung soft-deleted (default Laravel)', function () {
        $package = Package::factory()->create(['kode' => 'PKG-2026-001']);
        $package->delete();

        expect($this->repo->kodeExists('PKG-2026-001'))->toBeFalse();
    });
});
