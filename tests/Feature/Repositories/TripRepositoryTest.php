<?php

use App\Enums\TripStatus;
use App\Models\Booking;
use App\Models\Package;
use App\Models\Trip;
use App\Models\User;
use App\Repositories\Eloquent\TripRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/*
|==========================================================================
| SETUP
|==========================================================================
|
| - RefreshDatabase: reset DB tiap test (SQLite :memory:)
| - PRAGMA foreign_keys = ON: SQLite default OFF → perlu diaktifkan
| - Carbon::setTestNow: freeze time untuk test deterministik
|
| Catatan: Trip TIDAK pakai SoftDeletes dan TIDAK punya method delete().
| Trip tidak pernah dihapus — hanya di-cancel (lihat TripStatus).
|
*/

beforeEach(function () {

    // Freeze waktu untuk determinisme
    Carbon::setTestNow(Carbon::create(2026, 1, 15, 10, 0, 0, 'Asia/Makassar'));

    $this->repo = new TripRepository;
});

afterEach(function () {
    Carbon::setTestNow();
});

/*
|==========================================================================
| KATEGORI 1 — CONTRACT / RETURN TYPE & SHAPE
|==========================================================================
*/

describe('Contract — paginate', function () {
    it('mengembalikan LengthAwarePaginator', function () {
        Trip::factory()->count(3)->create();

        $result = $this->repo->paginate();

        expect($result)->toBeInstanceOf(LengthAwarePaginator::class);
    });

    it('item adalah instance Trip', function () {
        Trip::factory()->count(2)->create();

        $result = $this->repo->paginate();

        expect($result->items())->each->toBeInstanceOf(Trip::class);
    });

    it('meng-eager-load relasi package, booking, booking.client, mitra', function () {
        Trip::factory()->create();

        $result = $this->repo->paginate();
        $trip = $result->items()[0];

        expect($trip->relationLoaded('package'))->toBeTrue()
            ->and($trip->relationLoaded('booking'))->toBeTrue()
            ->and($trip->relationLoaded('mitra'))->toBeTrue()
            ->and($trip->booking->relationLoaded('client'))->toBeTrue();
    });

    it('mengurutkan default tanggal_mulai DESC', function () {
        $older = Trip::factory()->create(['tanggal_mulai' => '2026-01-01']);
        $newer = Trip::factory()->create(['tanggal_mulai' => '2026-06-01']);

        $result = $this->repo->paginate();

        expect($result->items()[0]->id)->toBe($newer->id)
            ->and($result->items()[1]->id)->toBe($older->id);
    });
});

describe('Contract — findById / findByKode / findByBookingId', function () {
    it('findById mengembalikan Trip dengan relasi lengkap', function () {
        $trip = Trip::factory()->lengkap()->create();

        $result = $this->repo->findById($trip->id);

        expect($result)->toBeInstanceOf(Trip::class)
            ->and($result->relationLoaded('package'))->toBeTrue()
            ->and($result->relationLoaded('booking'))->toBeTrue()
            ->and($result->relationLoaded('mitra'))->toBeTrue()
            ->and($result->relationLoaded('days'))->toBeTrue();
    });

    it('findById mengembalikan null jika tidak ada', function () {
        expect($this->repo->findById(999_999))->toBeNull();
    });

    it('findByKode mengembalikan Trip dengan relasi', function () {
        $trip = Trip::factory()->create(['kode' => 'TRP-2026-001']);

        $result = $this->repo->findByKode('TRP-2026-001');

        expect($result->id)->toBe($trip->id)
            ->and($result->relationLoaded('package'))->toBeTrue()
            ->and($result->relationLoaded('mitra'))->toBeTrue()
            ->and($result->relationLoaded('days'))->toBeTrue();
    });

    it('findByKode mengembalikan null jika tidak ada', function () {
        expect($this->repo->findByKode('TRP-9999-999'))->toBeNull();
    });

    it('findByBookingId mengembalikan Trip untuk booking tsb', function () {
        $trip = Trip::factory()->create();

        $result = $this->repo->findByBookingId($trip->booking_id);

        expect($result->id)->toBe($trip->id)
            ->and($result->relationLoaded('package'))->toBeTrue()
            ->and($result->relationLoaded('mitra'))->toBeTrue();
    });

    it('findByBookingId mengembalikan null jika tidak ada', function () {
        expect($this->repo->findByBookingId(999_999))->toBeNull();
    });
});

describe('Contract — findDueScheduled', function () {
    it('mengembalikan Collection', function () {
        expect($this->repo->findDueScheduled())
            ->toBeInstanceOf(Collection::class);
    });

    it('mengembalikan semua trip scheduled tanpa pemotongan tanggal global', function () {
        // Carbon sudah frozen di beforeEach ke 2026-01-15.
        // Pemeriksaan "waktunya" (per trip, timezone-aware) dilakukan di Service.
        Trip::factory()->create([
            'status' => TripStatus::SCHEDULED->value,
            'tanggal_mulai' => '2026-01-15',
        ]);
        Trip::factory()->create([
            'status' => TripStatus::SCHEDULED->value,
            'tanggal_mulai' => '2026-01-20',
        ]);

        $result = $this->repo->findDueScheduled();

        expect($result)->toHaveCount(2);
    });

    it('meng-eager-load mitra, booking, package', function () {
        Trip::factory()->create([
            'status' => TripStatus::SCHEDULED->value,
            'tanggal_mulai' => '2026-01-15',
        ]);

        $result = $this->repo->findDueScheduled();

        expect($result)->toHaveCount(1)
            ->and($result->first()?->relationLoaded('mitra'))->toBeTrue()
            ->and($result->first()?->relationLoaded('booking'))->toBeTrue()
            ->and($result->first()?->relationLoaded('package'))->toBeTrue();
    });
});

describe('Contract — countByStatus', function () {
    it('countByStatus mengembalikan semua status enum', function () {
        $result = $this->repo->countByStatus();

        expect($result)->toHaveKeys(TripStatus::values());
    });

    it('countByStatusForMitra mengembalikan semua status enum', function () {
        $mitra = User::factory()->mitra()->create();

        $result = $this->repo->countByStatusForMitra($mitra);

        expect($result)->toHaveKeys(TripStatus::values());
    });
});

/*
|==========================================================================
| KATEGORI 2 — SECURITY (SQL Wildcard Injection)
|==========================================================================
*/

describe('Security — wildcard injection pada paginate', function () {
    it('input % TIDAK berfungsi sebagai wildcard pada kode', function () {
        Trip::factory()->create(['kode' => 'TRP-2026-100']);
        Trip::factory()->create(['kode' => 'TRP-2026-200']);

        $result = $this->repo->paginate(['search' => 'TRP-2026-1%']);

        expect($result->total())->toBe(0);
    });

    it('input _ TIDAK berfungsi sebagai wildcard pada kode', function () {
        Trip::factory()->create(['kode' => 'TRP-2026-001']);
        Trip::factory()->create(['kode' => 'TRP-2026-002']);

        $result = $this->repo->paginate(['search' => 'TRP-2026-00_']);

        expect($result->total())->toBe(0);
    });

    it('input backslash di-escape dengan benar (bukan literal)', function () {
        $client = User::factory()->client()->create(['name' => 'Budi Santoso']);
        $booking = Booking::factory()->forClient($client)->create();
        Trip::factory()->forBooking($booking)->create();

        $result = $this->repo->paginate(['search' => 'Budi\\']);

        expect($result->total())->toBe(0);
    });

    it('input SQL injection classik aman (parameter binding)', function () {
        Trip::factory()->create();

        $result = $this->repo->paginate(['search' => "' OR 1=1--"]);

        expect($result->total())->toBe(0);
    });

    it('input SQL injection dengan quote tidak menyebabkan error syntax', function () {
        Trip::factory()->create();

        expect(fn () => $this->repo->paginate(['search' => "budi' OR '1'='1"]))
            ->not->toThrow(QueryException::class);
    });

    it('search tidak membedakan huruf besar/kecil', function () {
        $client = User::factory()->client()->create(['name' => 'Budi Santoso']);
        $booking = Booking::factory()->forClient($client)->create();
        Trip::factory()->forBooking($booking)->create();

        $result = $this->repo->paginate(['search' => 'BUDI']);

        expect($result->total())->toBe(1);
    });
});

/*
|==========================================================================
| KATEGORI 3 — CONCURRENCY / UNIQUE CONSTRAINT
|==========================================================================
*/

describe('Concurrency — unique constraint', function () {
    it('2x create dengan kode sama → QueryException', function () {
        Trip::factory()->create(['kode' => 'TRP-2026-001']);

        expect(fn () => Trip::factory()->create(['kode' => 'TRP-2026-001']))
            ->toThrow(QueryException::class);
    });

    it('2x create dengan booking_id sama → QueryException (unique)', function () {
        $booking = Booking::factory()->create();
        Trip::factory()->forBooking($booking)->create();

        expect(fn () => Trip::factory()->forBooking($booking)->create())
            ->toThrow(QueryException::class);
    });

    it('create dengan mitra_id yang tidak ada di DB → QueryException (FK)', function () {
        $ghostMitra = new User;
        $ghostMitra->forceFill(['id' => 999_999, 'role' => 'mitra']);

        $booking = Booking::factory()->create();
        $package = Package::factory()->create();

        expect(fn () => $this->repo->create([
            'kode' => 'TRP-2026-001',
            'booking_id' => $booking->id,
            'package_id' => $package->id,
            'mitra_id' => $ghostMitra->id,
            'snapshot_package' => [],
            'tanggal_mulai' => '2026-01-15',
            'tanggal_selesai' => '2026-01-16',
            'jumlah_peserta' => 2,
            'timezone' => 'Asia/Makassar',
            'status' => TripStatus::SCHEDULED->value,
        ]))->toThrow(QueryException::class);
    });
});

describe('Concurrency — generateKode', function () {
    it('DB kosong → TRP-{tahun}-001', function () {
        expect($this->repo->generateKode(2026))->toBe('TRP-2026-001');
    });

    it('setelah TRP-2026-001 → TRP-2026-002', function () {
        Trip::factory()->create(['kode' => 'TRP-2026-001']);

        expect($this->repo->generateKode(2026))->toBe('TRP-2026-002');
    });

    it('tahun berbeda punya sequence sendiri', function () {
        Trip::factory()->create(['kode' => 'TRP-2025-050']);

        expect($this->repo->generateKode(2026))->toBe('TRP-2026-001');
    });

    /*
     *   BUG DETECTION — generateKode setelah sequence ≥ 1000
     *
     * Test di bawah akan GAGAL dengan kode lama, dan LULUS setelah fix
     * `generateKode` (substr + orderByRaw).
     */

    it('BUG: setelah TRP-2026-999 → harus TRP-2026-1000', function () {
        Trip::factory()->create(['kode' => 'TRP-2026-999']);

        expect($this->repo->generateKode(2026))->toBe('TRP-2026-1000');
    });

    it('BUG: setelah TRP-2026-1000 → harus TRP-2026-1001', function () {
        Trip::factory()->create(['kode' => 'TRP-2026-1000']);

        expect($this->repo->generateKode(2026))->toBe('TRP-2026-1001');
    });

    it('BUG: generateKode 2x setelah TRP-2026-999 menghasilkan kode unik', function () {
        Trip::factory()->create(['kode' => 'TRP-2026-999']);

        $kode1 = $this->repo->generateKode(2026);
        Trip::factory()->create(['kode' => $kode1]);

        $kode2 = $this->repo->generateKode(2026);

        expect($kode1)->not->toBe($kode2)
            ->and($kode1)->toBe('TRP-2026-1000')
            ->and($kode2)->toBe('TRP-2026-1001');
    });
});

/*
|==========================================================================
| KATEGORI 4 — IDEMPOTENCY
|==========================================================================
*/

describe('Idempotency', function () {
    it('kodeExists konsisten saat dipanggil berulang', function () {
        Trip::factory()->create(['kode' => 'TRP-2026-001']);

        expect($this->repo->kodeExists('TRP-2026-001'))->toBeTrue()
            ->and($this->repo->kodeExists('TRP-2026-001'))->toBeTrue();
    });

    it('update dengan data sama 2x → hasil konsisten', function () {
        $trip = Trip::factory()->create(['status' => TripStatus::SCHEDULED]);

        $this->repo->update($trip, ['status' => TripStatus::ACTIVE->value]);
        $trip->refresh();

        $this->repo->update($trip, ['status' => TripStatus::ACTIVE->value]);
        $trip->refresh();

        expect($trip->status)->toBe(TripStatus::ACTIVE);
    });

    it('generateKode dengan DB kosong → selalu 001', function () {
        expect($this->repo->generateKode(2026))->toBe('TRP-2026-001')
            ->and($this->repo->generateKode(2026))->toBe('TRP-2026-001');
    });
});

/*
|==========================================================================
| KATEGORI 5 — DATA INTEGRITY
|==========================================================================
|
| Catatan: Trip TIDAK punya method delete() — sesuai desain bisnis
| (trip tidak pernah dihapus, hanya di-cancel). Test delete tidak relevan.
|
*/

describe('Data Integrity — create / update', function () {
    it('create menyimpan semua field', function () {
        $mitra = User::factory()->mitra()->create();
        $package = Package::factory()->forMitra($mitra)->create();
        $booking = Booking::factory()->create();
        $snapshot = ['nama' => 'Test Paket', 'durasi_hari' => 2];

        $trip = $this->repo->create([
            'kode' => 'TRP-2026-001',
            'booking_id' => $booking->id,
            'package_id' => $package->id,
            'mitra_id' => $mitra->id,
            'snapshot_package' => $snapshot,
            'tanggal_mulai' => '2026-02-01',
            'tanggal_selesai' => '2026-02-02',
            'jumlah_peserta' => 3,
            'timezone' => 'Asia/Makassar',
            'status' => TripStatus::SCHEDULED->value,
        ]);

        expect($trip->kode)->toBe('TRP-2026-001')
            ->and($trip->snapshot_package)->toBe($snapshot)
            ->and($trip->status)->toBe(TripStatus::SCHEDULED)
            ->and($trip->jumlah_peserta)->toBe(3);
    });

    it('update mengubah status dan return instance terbaru', function () {
        $trip = Trip::factory()->create(['status' => TripStatus::SCHEDULED]);

        $updated = $this->repo->update($trip, ['status' => TripStatus::ACTIVE->value]);

        expect($updated->status)->toBe(TripStatus::ACTIVE);
    });

    it('desain: TripRepository TIDAK punya method delete()', function () {
        // Dokumentasi: trip tidak pernah dihapus — hanya di-cancel.
        expect(method_exists($this->repo, 'delete'))->toBeFalse();
    });
});

describe('Data Integrity — kodeExists', function () {
    it('kodeExists true jika kode sudah dipakai', function () {
        Trip::factory()->create(['kode' => 'TRP-2026-001']);

        expect($this->repo->kodeExists('TRP-2026-001'))->toBeTrue();
    });

    it('kodeExists dengan exceptId = trip sendiri → false', function () {
        $trip = Trip::factory()->create(['kode' => 'TRP-2026-001']);

        expect($this->repo->kodeExists('TRP-2026-001', $trip->id))->toBeFalse();
    });
});

describe('Data Integrity — countByStatus', function () {
    it('countByStatus menghitung per status dengan benar', function () {
        Trip::factory()->scheduled()->count(2)->create();
        Trip::factory()->active()->count(3)->create();
        Trip::factory()->cancelled()->count(1)->create();

        $result = $this->repo->countByStatus();

        expect($result[TripStatus::SCHEDULED->value])->toBe(2)
            ->and($result[TripStatus::ACTIVE->value])->toBe(3)
            ->and($result[TripStatus::CANCELLED->value])->toBe(1);
    });

    it('countByStatus mengembalikan 0 untuk status yang tidak ada', function () {
        Trip::factory()->scheduled()->create();

        $result = $this->repo->countByStatus();

        expect($result[TripStatus::ACTIVE->value])->toBe(0)
            ->and($result[TripStatus::COMPLETED->value])->toBe(0);
    });

    it('countByStatusForMitra hanya menghitung trip mitra itu', function () {
        $mitraA = User::factory()->mitra()->create();
        $mitraB = User::factory()->mitra()->create();

        Trip::factory()->scheduled()->count(3)->create(['mitra_id' => $mitraA->id]);
        Trip::factory()->scheduled()->count(5)->create(['mitra_id' => $mitraB->id]);

        $result = $this->repo->countByStatusForMitra($mitraA);

        expect($result[TripStatus::SCHEDULED->value])->toBe(3);
    });
});

describe('Data Integrity — findDueScheduled', function () {
    it('mengembalikan trip scheduled (tanpa pemotongan tanggal global)', function () {
        $due = Trip::factory()->create([
            'status' => TripStatus::SCHEDULED->value,
            'tanggal_mulai' => '2026-01-15',
        ]);
        Trip::factory()->create([
            'status' => TripStatus::SCHEDULED->value,
            'tanggal_mulai' => '2026-01-20',
        ]);
        Trip::factory()->create([
            'status' => TripStatus::ACTIVE->value,
            'tanggal_mulai' => '2026-01-15',
        ]);

        $result = $this->repo->findDueScheduled();

        expect($result)->toHaveCount(2)
            ->and($result->pluck('id')->contains($due->id))->toBeTrue();
    });
});

/*
|==========================================================================
| KATEGORI 6 — ERROR HANDLING
|==========================================================================
*/

describe('Error Handling — constraint violations', function () {
    it('create dengan tanggal_mulai null → QueryException (NOT NULL)', function () {
        $mitra = User::factory()->mitra()->create();
        $package = Package::factory()->forMitra($mitra)->create();
        $booking = Booking::factory()->create();

        expect(fn () => $this->repo->create([
            'kode' => 'TRP-2026-001',
            'booking_id' => $booking->id,
            'package_id' => $package->id,
            'mitra_id' => $mitra->id,
            'snapshot_package' => [],
            'tanggal_mulai' => null,
            'tanggal_selesai' => '2026-02-02',
            'jumlah_peserta' => 2,
            'timezone' => 'Asia/Makassar',
            'status' => TripStatus::SCHEDULED->value,
        ]))->toThrow(QueryException::class);
    });

    it('create dengan status enum invalid → ValueError (enum cast)', function () {
        $mitra = User::factory()->mitra()->create();
        $package = Package::factory()->forMitra($mitra)->create();
        $booking = Booking::factory()->create();

        expect(fn () => $this->repo->create([
            'kode' => 'TRP-2026-001',
            'booking_id' => $booking->id,
            'package_id' => $package->id,
            'mitra_id' => $mitra->id,
            'snapshot_package' => [],
            'tanggal_mulai' => '2026-01-15',
            'tanggal_selesai' => '2026-01-16',
            'jumlah_peserta' => 2,
            'timezone' => 'Asia/Makassar',
            'status' => 'ngawur',
        ]))->toThrow(ValueError::class);
    });

    it('update ke status enum invalid → ValueError', function () {
        $trip = Trip::factory()->create();

        expect(fn () => $this->repo->update($trip, ['status' => 'ngawur']))
            ->toThrow(ValueError::class);
    });
});

/*
|==========================================================================
| KATEGORI 7 — EDGE CASES & BVA
|==========================================================================
*/

describe('Edge Cases — paginate filters', function () {
    it('filter status = active → hanya active', function () {
        Trip::factory()->active()->count(2)->create();
        Trip::factory()->scheduled()->count(3)->create();

        $result = $this->repo->paginate(['status' => 'active']);

        expect($result->total())->toBe(2);
    });

    it('filter package_id → hanya trip package itu', function () {
        $mitra = User::factory()->mitra()->create();
        $packageA = Package::factory()->forMitra($mitra)->create();
        $packageB = Package::factory()->forMitra($mitra)->create();

        Trip::factory()->forPackage($packageA)->count(2)->create();
        Trip::factory()->forPackage($packageB)->count(3)->create();

        $result = $this->repo->paginate(['package_id' => $packageA->id]);

        expect($result->total())->toBe(2);
    });

    it('filter mitra_id → hanya trip milik mitra itu', function () {
        $mitraA = User::factory()->mitra()->create();
        $mitraB = User::factory()->mitra()->create();

        Trip::factory()->count(2)->create(['mitra_id' => $mitraA->id]);
        Trip::factory()->count(3)->create(['mitra_id' => $mitraB->id]);

        $result = $this->repo->paginate(['mitra_id' => $mitraA->id]);

        expect($result->total())->toBe(2);
    });

    it('filter tanggal_dari + tanggal_sampai', function () {
        Trip::factory()->create(['tanggal_mulai' => '2026-01-15']);
        Trip::factory()->create(['tanggal_mulai' => '2026-02-15']);
        Trip::factory()->create(['tanggal_mulai' => '2026-03-15']);

        $result = $this->repo->paginate([
            'tanggal_dari' => '2026-02-01',
            'tanggal_sampai' => '2026-02-28',
        ]);

        expect($result->total())->toBe(1);
    });

    it('filter combine status + mitra_id', function () {
        $mitraA = User::factory()->mitra()->create();
        $mitraB = User::factory()->mitra()->create();

        Trip::factory()->active()->create(['mitra_id' => $mitraA->id]);
        Trip::factory()->scheduled()->create(['mitra_id' => $mitraA->id]);
        Trip::factory()->active()->create(['mitra_id' => $mitraB->id]);

        $result = $this->repo->paginate([
            'status' => 'active',
            'mitra_id' => $mitraA->id,
        ]);

        expect($result->total())->toBe(1);
    });

    it('sort = "invalid" → fallback ke tanggal_mulai', function () {
        Trip::factory()->count(3)->create();

        expect(fn () => $this->repo->paginate(['sort' => 'invalid']))
            ->not->toThrow(QueryException::class);
    });

    it('direction = "INVALID" → fallback ke desc', function () {
        $older = Trip::factory()->create(['tanggal_mulai' => '2026-01-01']);
        $newer = Trip::factory()->create(['tanggal_mulai' => '2026-06-01']);

        $result = $this->repo->paginate(['direction' => 'INVALID']);

        expect($result->items()[0]->id)->toBe($newer->id);
    });

    it('perPage = 1 → 1 item', function () {
        Trip::factory()->count(5)->create();

        $result = $this->repo->paginate([], 1);

        expect($result->perPage())->toBe(1)
            ->and($result->items())->toHaveCount(1);
    });
});

describe('Edge Cases — paginateForMitra', function () {
    it('hanya mengembalikan trip milik mitra', function () {
        $mitraA = User::factory()->mitra()->create();
        $mitraB = User::factory()->mitra()->create();

        Trip::factory()->count(2)->create(['mitra_id' => $mitraA->id]);
        Trip::factory()->count(3)->create(['mitra_id' => $mitraB->id]);

        $result = $this->repo->paginateForMitra($mitraA);

        expect($result->total())->toBe(2);
    });

    it('mempertahankan filter lain saat dipanggil via paginateForMitra', function () {
        $mitra = User::factory()->mitra()->create();

        Trip::factory()->active()->count(2)->create(['mitra_id' => $mitra->id]);
        Trip::factory()->scheduled()->count(3)->create(['mitra_id' => $mitra->id]);

        $result = $this->repo->paginateForMitra($mitra, ['status' => 'active']);

        expect($result->total())->toBe(2);
    });
});
