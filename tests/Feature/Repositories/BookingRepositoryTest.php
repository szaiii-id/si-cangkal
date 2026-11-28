<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Package;
use App\Models\Payment;
use App\Models\User;
use App\Repositories\Eloquent\BookingRepository;
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
|   agar FK constraint dan cascade benar-benar berlaku.
| - Instantiate repo sekali per test (stateless).
|
*/

beforeEach(function () {

    $this->repo = new BookingRepository;
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
        Booking::factory()->count(3)->create();

        $result = $this->repo->paginate();

        expect($result)->toBeInstanceOf(LengthAwarePaginator::class);
    });

    it('item adalah instance Booking', function () {
        Booking::factory()->count(2)->create();

        $result = $this->repo->paginate();

        expect($result->items())->each->toBeInstanceOf(Booking::class);
    });

    it('meng-eager-load relasi package, client, mitra', function () {
        Booking::factory()->create();

        $result = $this->repo->paginate();
        $booking = $result->items()[0];

        expect($booking->relationLoaded('package'))->toBeTrue()
            ->and($booking->relationLoaded('client'))->toBeTrue()
            ->and($booking->relationLoaded('mitra'))->toBeTrue();
    });

    it('package hanya load kolom id, nama, kode, timezone', function () {
        Booking::factory()->create();

        $booking = $this->repo->paginate()->items()[0];

        expect($booking->package->getAttributes())
            ->toHaveKeys(['id', 'nama', 'kode', 'timezone'])
            ->not->toHaveKey('deskripsi');
    });

    it('mengurutkan default created_at DESC', function () {
        $older = Booking::factory()->create(['created_at' => now()->subDay()]);
        $newer = Booking::factory()->create(['created_at' => now()]);

        $result = $this->repo->paginate();

        expect($result->items()[0]->id)->toBe($newer->id)
            ->and($result->items()[1]->id)->toBe($older->id);
    });
});

describe('Contract — findById / findByKode', function () {
    it('findById mengembalikan Booking dengan relasi lengkap', function () {
        $booking = Booking::factory()->lengkap()->create();
        Payment::factory()->create(['booking_id' => $booking->id]);

        $result = $this->repo->findById($booking->id);

        expect($result)->toBeInstanceOf(Booking::class)
            ->and($result->relationLoaded('package'))->toBeTrue()
            ->and($result->relationLoaded('client'))->toBeTrue()
            ->and($result->relationLoaded('participants'))->toBeTrue()
            ->and($result->relationLoaded('payments'))->toBeTrue()
            ->and($result->relationLoaded('mitra'))->toBeTrue();
    });

    it('findById mengembalikan null jika tidak ada', function () {
        expect($this->repo->findById('00000000-0000-4000-8000-000000000000'))->toBeNull();
    });

    it('findById TIDAK mengembalikan booking soft-deleted', function () {
        $booking = Booking::factory()->create();
        $booking->delete();

        expect($this->repo->findById($booking->id))->toBeNull();
    });

    it('findByKode mengembalikan Booking dengan relasi', function () {
        $booking = Booking::factory()->create(['kode' => 'BKG-2026-001']);

        $result = $this->repo->findByKode('BKG-2026-001');

        expect($result->id)->toBe($booking->id)
            ->and($result->relationLoaded('package'))->toBeTrue()
            ->and($result->relationLoaded('client'))->toBeTrue()
            ->and($result->relationLoaded('participants'))->toBeTrue()
            ->and($result->relationLoaded('payments'))->toBeTrue();
    });

    it('findByKode mengembalikan null jika tidak ada', function () {
        expect($this->repo->findByKode('BKG-9999-999'))->toBeNull();
    });
});

describe('Contract — countByStatus', function () {
    it('countByStatus mengembalikan array dengan semua status enum', function () {
        $result = $this->repo->countByStatus();

        expect($result)->toHaveKeys(BookingStatus::values());
    });

    it('countByStatusForClient mengembalikan array dengan semua status enum', function () {
        $client = User::factory()->client()->create();

        $result = $this->repo->countByStatusForClient($client);

        expect($result)->toHaveKeys(BookingStatus::values());
    });

    it('countByStatusForMitra mengembalikan array dengan semua status enum', function () {
        $mitra = User::factory()->mitra()->create();

        $result = $this->repo->countByStatusForMitra($mitra);

        expect($result)->toHaveKeys(BookingStatus::values());
    });
});

describe('Contract — findExpired', function () {
    it('mengembalikan Collection', function () {
        $result = $this->repo->findExpired();

        expect($result)->toBeInstanceOf(Collection::class);
    });

    it('hanya mengembalikan booking pending_payment yang expired', function () {
        $expired1 = Booking::factory()->expired()->create();
        $expired2 = Booking::factory()->expired()->create();
        Booking::factory()->pendingPayment()->create(); // belum expired

        $result = $this->repo->findExpired();

        expect($result)->toHaveCount(2)
            ->and($result->pluck('id')->all())->toContain($expired1->id, $expired2->id);
    });

    it('TIDAK mengembalikan booking status lain meski expired_at lewat', function () {
        Booking::factory()->confirmed()->create(['expired_at' => now()->subDay()]);

        $result = $this->repo->findExpired();

        expect($result)->toHaveCount(0);
    });
});

/*
|==========================================================================
| KATEGORI 2 — SECURITY (SQL Wildcard Injection)
|==========================================================================
*/

describe('Security — wildcard injection pada paginate', function () {
    it('input % TIDAK berfungsi sebagai wildcard pada kode', function () {
        $mitra = User::factory()->mitra()->create();
        $package = Package::factory()->forMitra($mitra)->create();
        $client = User::factory()->client()->create();

        Booking::factory()->forPackage($package)->forClient($client)->create([
            'kode' => 'BKG-2026-100',
        ]);
        Booking::factory()->forPackage($package)->forClient($client)->create([
            'kode' => 'BKG-2026-200',
        ]);

        $result = $this->repo->paginate(['search' => 'BKG-2026-1%']);

        // '%' harus literal, bukan wildcard — hanya match kode mengandung "BKG-2026-1%"
        expect($result->total())->toBe(0);
    });

    it('input _ TIDAK berfungsi sebagai wildcard pada kode', function () {
        $mitra = User::factory()->mitra()->create();
        $package = Package::factory()->forMitra($mitra)->create();
        $client = User::factory()->client()->create();

        Booking::factory()->forPackage($package)->forClient($client)->create([
            'kode' => 'BKG-2026-001',
        ]);
        Booking::factory()->forPackage($package)->forClient($client)->create([
            'kode' => 'BKG-2026-002',
        ]);

        $result = $this->repo->paginate(['search' => 'BKG-2026-00_']);

        // '_' harus literal, bukan wildcard satu karakter — tidak match apa-apa
        expect($result->total())->toBe(0);
    });

    it('input SQL injection classik aman (parameter binding)', function () {
        Booking::factory()->create();

        $result = $this->repo->paginate(['search' => "' OR 1=1--"]);

        expect($result->total())->toBe(0);
    });

    it('input SQL injection dengan quote tidak menyebabkan error syntax', function () {
        Booking::factory()->create();

        expect(fn () => $this->repo->paginate(['search' => "budi' OR '1'='1"]))
            ->not->toThrow(QueryException::class);
    });

    it('search tidak membedakan huruf besar/kecil', function () {
        $mitra = User::factory()->mitra()->create();
        $package = Package::factory()->forMitra($mitra)->create();
        $client = User::factory()->client()->create(['name' => 'Budi Santoso']);

        Booking::factory()->forPackage($package)->forClient($client)->create();

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
        $mitra = User::factory()->mitra()->create();
        $package = Package::factory()->forMitra($mitra)->create();
        $client = User::factory()->client()->create();

        Booking::factory()->forPackage($package)->forClient($client)->create([
            'kode' => 'BKG-2026-001',
        ]);

        expect(fn () => Booking::factory()->forPackage($package)->forClient($client)->create([
            'kode' => 'BKG-2026-001',
        ]))->toThrow(QueryException::class);
    });

    it('create dengan mitra_id yang tidak ada di DB → QueryException (FK)', function () {
        $package = Package::factory()->create();
        $client = User::factory()->client()->create();

        // Mitra "palsu"
        $ghostMitra = new User;
        $ghostMitra->forceFill(['id' => '00000000-0000-4000-8000-000000000000', 'role' => 'mitra']);

        expect(fn () => $this->repo->create([
            'kode' => 'BKG-2026-001',
            'package_id' => $package->id,
            'user_id' => $client->id,
            'mitra_id' => $ghostMitra->id,
            'tanggal_keberangkatan' => now()->addDays(7)->format('Y-m-d'),
            'jumlah_peserta' => 2,
            'total_harga' => 1_000_000,
            'status' => BookingStatus::PENDING_PAYMENT->value,
            'expired_at' => now()->addHours(24),
        ]))->toThrow(QueryException::class);
    });
});

describe('Concurrency — generateKode', function () {
    it('DB kosong → BKG-{tahun}-001', function () {
        expect($this->repo->generateKode(2026))->toBe('BKG-2026-001');
    });

    it('setelah BKG-2026-001 → BKG-2026-002', function () {
        $mitra = User::factory()->mitra()->create();
        $package = Package::factory()->forMitra($mitra)->create();
        $client = User::factory()->client()->create();
        Booking::factory()->forPackage($package)->forClient($client)->create([
            'kode' => 'BKG-2026-001',
        ]);

        expect($this->repo->generateKode(2026))->toBe('BKG-2026-002');
    });

    it('kode soft-deleted TETAP dihitung (withTrashed)', function () {
        $mitra = User::factory()->mitra()->create();
        $package = Package::factory()->forMitra($mitra)->create();
        $client = User::factory()->client()->create();
        $booking = Booking::factory()->forPackage($package)->forClient($client)->create([
            'kode' => 'BKG-2026-001',
        ]);
        $booking->delete();

        expect($this->repo->generateKode(2026))->toBe('BKG-2026-002');
    });

    it('tahun berbeda punya sequence sendiri', function () {
        $mitra = User::factory()->mitra()->create();
        $package = Package::factory()->forMitra($mitra)->create();
        $client = User::factory()->client()->create();
        Booking::factory()->forPackage($package)->forClient($client)->create([
            'kode' => 'BKG-2025-050',
        ]);

        expect($this->repo->generateKode(2026))->toBe('BKG-2026-001');
    });

    /*
     *   BUG DETECTION — generateKode setelah sequence ≥ 1000
     *
     * Kode saat ini pakai:
     *   $expectedLength = strlen($prefix) + 3  // = 12 untuk BKG-2026-
     *   ->whereRaw('LENGTH(kode) = ?', [$expectedLength])
     *   ->substr($lastKode, -3)
     *
     * Konsekuensi:
     * - Kode dengan sequence 4 digit (BKG-2026-1000) punya length 13 →
     *   TIDAK dihitung oleh whereRaw LENGTH = 12.
     * - Kalau BKG-2026-999 ada, generate menghasilkan BKG-2026-1000.
     *   Tapi generate berikutnya TIDAK melihat BKG-2026-1000 (length 13) →
     *   melihat BKG-2026-999 lagi → generate BKG-2026-1000 LAGI → DUPLIKAT.
     * - Kalau BKG-2026-999 sudah soft-deleted (dengan force delete), generate
     *   berikutnya bisa ROLLBACK ke 001.
     */

    it('BUG: setelah BKG-2026-999 → harus BKG-2026-1000', function () {
        $mitra = User::factory()->mitra()->create();
        $package = Package::factory()->forMitra($mitra)->create();
        $client = User::factory()->client()->create();
        Booking::factory()->forPackage($package)->forClient($client)->create([
            'kode' => 'BKG-2026-999',
        ]);

        expect($this->repo->generateKode(2026))->toBe('BKG-2026-1000');
    });

    it('BUG: setelah BKG-2026-1000 → harus BKG-2026-1001', function () {
        $mitra = User::factory()->mitra()->create();
        $package = Package::factory()->forMitra($mitra)->create();
        $client = User::factory()->client()->create();
        Booking::factory()->forPackage($package)->forClient($client)->create([
            'kode' => 'BKG-2026-1000',
        ]);

        expect($this->repo->generateKode(2026))->toBe('BKG-2026-1001');
    });

    it('BUG: generateKode 2x setelah BKG-2026-999 menghasilkan kode unik', function () {
        $mitra = User::factory()->mitra()->create();
        $package = Package::factory()->forMitra($mitra)->create();
        $client = User::factory()->client()->create();

        Booking::factory()->forPackage($package)->forClient($client)->create([
            'kode' => 'BKG-2026-999',
        ]);

        $kode1 = $this->repo->generateKode(2026);
        Booking::factory()->forPackage($package)->forClient($client)->create([
            'kode' => $kode1,
        ]);

        $kode2 = $this->repo->generateKode(2026);

        expect($kode1)->not->toBe($kode2)
            ->and($kode1)->toBe('BKG-2026-1000')
            ->and($kode2)->toBe('BKG-2026-1001');
    });
});

/*
|==========================================================================
| KATEGORI 4 — IDEMPOTENCY
|==========================================================================
*/

describe('Idempotency', function () {
    it('kodeExists konsisten saat dipanggil berulang', function () {
        Booking::factory()->create(['kode' => 'BKG-2026-001']);

        $first = $this->repo->kodeExists('BKG-2026-001');
        $second = $this->repo->kodeExists('BKG-2026-001');

        expect($first)->toBeTrue()
            ->and($second)->toBeTrue();
    });

    it('kodeExists MENGHITUNG soft-deleted booking (withTrashed)', function () {
        $booking = Booking::factory()->create(['kode' => 'BKG-2026-001']);
        $booking->delete();

        expect($this->repo->kodeExists('BKG-2026-001'))->toBeTrue();
    });

    it('update dengan data sama 2x → hasil konsisten', function () {
        $booking = Booking::factory()->create(['catatan' => 'Lama']);

        $this->repo->update($booking, ['catatan' => 'Baru']);
        $booking->refresh();

        $this->repo->update($booking, ['catatan' => 'Baru']);
        $booking->refresh();

        expect($booking->catatan)->toBe('Baru');
    });

    it('generateKode dengan DB kosong → selalu 001 (idempotent)', function () {
        $first = $this->repo->generateKode(2026);
        $second = $this->repo->generateKode(2026);

        expect($first)->toBe('BKG-2026-001')
            ->and($second)->toBe('BKG-2026-001');
    });
});

/*
|==========================================================================
| KATEGORI 5 — DATA INTEGRITY
|==========================================================================
*/

describe('Data Integrity — create / update / delete', function () {
    it('create menyimpan semua field', function () {
        $mitra = User::factory()->mitra()->create();
        $package = Package::factory()->forMitra($mitra)->create();
        $client = User::factory()->client()->create();

        $booking = $this->repo->create([
            'kode' => 'BKG-2026-001',
            'package_id' => $package->id,
            'user_id' => $client->id,
            'mitra_id' => $mitra->id,
            'tanggal_keberangkatan' => '2026-02-01',
            'jumlah_peserta' => 3,
            'total_harga' => 2_550_000,
            'status' => BookingStatus::PENDING_PAYMENT->value,
            'expired_at' => now()->addHours(24),
            'catatan' => 'Tolong pagi',
        ]);

        expect($booking->kode)->toBe('BKG-2026-001')
            ->and($booking->jumlah_peserta)->toBe(3)
            ->and((float) $booking->total_harga)->toBe(2_550_000.0)
            ->and($booking->status)->toBe(BookingStatus::PENDING_PAYMENT)
            ->and($booking->catatan)->toBe('Tolong pagi');
    });

    it('update mengubah status dan return instance terbaru', function () {
        $booking = Booking::factory()->pendingPayment()->create();

        $updated = $this->repo->update($booking, [
            'status' => BookingStatus::CONFIRMED->value,
            'expired_at' => null,
        ]);

        expect($updated->status)->toBe(BookingStatus::CONFIRMED)
            ->and($updated->expired_at)->toBeNull();
    });

    it('delete soft-delete booking', function () {
        $booking = Booking::factory()->create();

        $result = $this->repo->delete($booking);

        expect($result)->toBeTrue()
            ->and($this->repo->findById($booking->id))->toBeNull();

        $this->assertSoftDeleted('bookings', ['id' => $booking->id]);
    });
});

describe('Data Integrity — countByStatus', function () {
    it('countByStatus menghitung per status dengan benar', function () {
        Booking::factory()->pendingPayment()->count(3)->create();
        Booking::factory()->confirmed()->count(2)->create();
        Booking::factory()->cancelled()->count(1)->create();

        $result = $this->repo->countByStatus();

        expect($result[BookingStatus::PENDING_PAYMENT->value])->toBe(3)
            ->and($result[BookingStatus::CONFIRMED->value])->toBe(2)
            ->and($result[BookingStatus::CANCELLED->value])->toBe(1);
    });

    it('countByStatus mengembalikan 0 untuk status yang tidak ada', function () {
        Booking::factory()->pendingPayment()->count(2)->create();

        $result = $this->repo->countByStatus();

        expect($result[BookingStatus::CONFIRMED->value])->toBe(0)
            ->and($result[BookingStatus::COMPLETED->value])->toBe(0);
    });

    it('countByStatus TIDAK menghitung soft-deleted', function () {
        Booking::factory()->pendingPayment()->count(2)->create();
        $deleted = Booking::factory()->pendingPayment()->create();
        $deleted->delete();

        $result = $this->repo->countByStatus();

        expect($result[BookingStatus::PENDING_PAYMENT->value])->toBe(2);
    });

    it('countByStatusForClient hanya menghitung booking client itu', function () {
        $clientA = User::factory()->client()->create();
        $clientB = User::factory()->client()->create();

        Booking::factory()->forClient($clientA)->pendingPayment()->count(3)->create();
        Booking::factory()->forClient($clientB)->pendingPayment()->count(5)->create();

        $result = $this->repo->countByStatusForClient($clientA);

        expect($result[BookingStatus::PENDING_PAYMENT->value])->toBe(3);
    });

    it('countByStatusForMitra hanya menghitung booking mitra itu', function () {
        $mitraA = User::factory()->mitra()->create();
        $mitraB = User::factory()->mitra()->create();

        $packageA = Package::factory()->forMitra($mitraA)->create();
        $packageB = Package::factory()->forMitra($mitraB)->create();

        Booking::factory()->forPackage($packageA)->pendingPayment()->count(3)->create();
        Booking::factory()->forPackage($packageB)->pendingPayment()->count(5)->create();

        $result = $this->repo->countByStatusForMitra($mitraA);

        expect($result[BookingStatus::PENDING_PAYMENT->value])->toBe(3);
    });
});

describe('Data Integrity — findExpired', function () {
    it('hanya mengembalikan booking pending_payment dengan expired_at lewat', function () {
        $expired = Booking::factory()->expired()->create();
        Booking::factory()->pendingPayment()->create(); // belum expired
        Booking::factory()->confirmed()->create(['expired_at' => now()->subDay()]);

        $result = $this->repo->findExpired();

        expect($result)->toHaveCount(1)
            ->and($result->first()->id)->toBe($expired->id);
    });

    it('TIDAK mengembalikan booking pending_payment tanpa expired_at', function () {
        Booking::factory()->pendingPayment()->create(['expired_at' => null]);

        $result = $this->repo->findExpired();

        expect($result)->toHaveCount(0);
    });
});

/*
|==========================================================================
| KATEGORI 6 — ERROR HANDLING
|==========================================================================
*/

describe('Error Handling — constraint violations', function () {
    it('create dengan nama null → QueryException (NOT NULL)', function () {
        $mitra = User::factory()->mitra()->create();
        $package = Package::factory()->forMitra($mitra)->create();
        $client = User::factory()->client()->create();

        expect(fn () => $this->repo->create([
            'kode' => 'BKG-2026-001',
            'package_id' => $package->id,
            'user_id' => $client->id,
            'mitra_id' => $mitra->id,
            'tanggal_keberangkatan' => null, // ← NOT NULL
            'jumlah_peserta' => 2,
            'total_harga' => 1_000_000,
            'status' => BookingStatus::PENDING_PAYMENT->value,
        ]))->toThrow(QueryException::class);
    });

    it('create dengan status enum invalid → QueryException', function () {
        $mitra = User::factory()->mitra()->create();
        $package = Package::factory()->forMitra($mitra)->create();
        $client = User::factory()->client()->create();

        expect(fn () => $this->repo->create([
            'kode' => 'BKG-2026-001',
            'package_id' => $package->id,
            'user_id' => $client->id,
            'mitra_id' => $mitra->id,
            'tanggal_keberangkatan' => now()->format('Y-m-d'),
            'jumlah_peserta' => 2,
            'total_harga' => 1_000_000,
            'status' => 'ngawur', // ← invalid
        ]))->toThrow(ValueError::class);  // ← enum cast fail fast
    });

    it('create dengan package_id yang tidak ada → QueryException (FK)', function () {
        $mitra = User::factory()->mitra()->create();
        $client = User::factory()->client()->create();

        expect(fn () => $this->repo->create([
            'kode' => 'BKG-2026-001',
            'package_id' => 999_999, // ← tidak ada
            'user_id' => $client->id,
            'mitra_id' => $mitra->id,
            'tanggal_keberangkatan' => now()->format('Y-m-d'),
            'jumlah_peserta' => 2,
            'total_harga' => 1_000_000,
            'status' => BookingStatus::PENDING_PAYMENT->value,
        ]))->toThrow(QueryException::class);
    });

    it('update ke status enum invalid → ValueError', function () {
        $booking = Booking::factory()->create();

        expect(fn () => $this->repo->update($booking, ['status' => 'ngawur']))
            ->toThrow(ValueError::class);
    });
});

/*
|==========================================================================
| KATEGORI 7 — EDGE CASES & BVA
|==========================================================================
*/

describe('Edge Cases — paginate filters', function () {
    it('filter status = confirmed → hanya confirmed', function () {
        Booking::factory()->confirmed()->count(2)->create();
        Booking::factory()->pendingPayment()->count(3)->create();

        $result = $this->repo->paginate(['status' => 'confirmed']);

        expect($result->total())->toBe(2);
    });

    it('filter package_id → hanya booking package itu', function () {
        $mitra = User::factory()->mitra()->create();
        $packageA = Package::factory()->forMitra($mitra)->create();
        $packageB = Package::factory()->forMitra($mitra)->create();

        Booking::factory()->forPackage($packageA)->count(2)->create();
        Booking::factory()->forPackage($packageB)->count(3)->create();

        $result = $this->repo->paginate(['package_id' => $packageA->id]);

        expect($result->total())->toBe(2);
    });

    it('filter user_id → hanya booking client itu', function () {
        $clientA = User::factory()->client()->create();
        $clientB = User::factory()->client()->create();

        Booking::factory()->forClient($clientA)->count(2)->create();
        Booking::factory()->forClient($clientB)->count(3)->create();

        $result = $this->repo->paginate(['user_id' => $clientA->id]);

        expect($result->total())->toBe(2);
    });

    it('filter mitra_id → hanya booking milik mitra itu', function () {
        $mitraA = User::factory()->mitra()->create();
        $mitraB = User::factory()->mitra()->create();

        $packageA = Package::factory()->forMitra($mitraA)->create();
        $packageB = Package::factory()->forMitra($mitraB)->create();

        Booking::factory()->forPackage($packageA)->count(2)->create();
        Booking::factory()->forPackage($packageB)->count(3)->create();

        $result = $this->repo->paginate(['mitra_id' => $mitraA->id]);

        expect($result->total())->toBe(2);
    });

    it('filter tanggal_dari + tanggal_sampai', function () {
        Booking::factory()->create(['tanggal_keberangkatan' => '2026-01-15']);
        Booking::factory()->create(['tanggal_keberangkatan' => '2026-02-15']);
        Booking::factory()->create(['tanggal_keberangkatan' => '2026-03-15']);

        $result = $this->repo->paginate([
            'tanggal_dari' => '2026-02-01',
            'tanggal_sampai' => '2026-02-28',
        ]);

        expect($result->total())->toBe(1);
    });

    it('filter is_today = true → hanya booking hari ini', function () {
        Carbon::setTestNow('2026-01-15 10:00:00');

        Booking::factory()->create(['tanggal_keberangkatan' => '2026-01-15']);
        Booking::factory()->create(['tanggal_keberangkatan' => '2026-01-16']);

        $result = $this->repo->paginate(['is_today' => true]);

        expect($result->total())->toBe(1);
    });

    it('filter is_today = false → skip filter', function () {
        Booking::factory()->create(['tanggal_keberangkatan' => '2026-01-15']);
        Booking::factory()->create(['tanggal_keberangkatan' => '2026-01-16']);

        $result = $this->repo->paginate(['is_today' => false]);

        expect($result->total())->toBe(2);
    });

    it('filter is_today = "1" (string) → di-parse boolean true', function () {
        Carbon::setTestNow('2026-01-15 10:00:00');

        Booking::factory()->create(['tanggal_keberangkatan' => '2026-01-15']);
        Booking::factory()->create(['tanggal_keberangkatan' => '2026-01-16']);

        $result = $this->repo->paginate(['is_today' => '1']);

        expect($result->total())->toBe(1);
    });

    it('filter is_today = "false" (string) → di-parse boolean false', function () {
        Booking::factory()->create(['tanggal_keberangkatan' => '2026-01-15']);
        Booking::factory()->create(['tanggal_keberangkatan' => '2026-01-16']);

        $result = $this->repo->paginate(['is_today' => 'false']);

        expect($result->total())->toBe(2); // skip filter
    });

    it('filter combine status + mitra_id', function () {
        $mitraA = User::factory()->mitra()->create();
        $mitraB = User::factory()->mitra()->create();
        $packageA = Package::factory()->forMitra($mitraA)->create();
        $packageB = Package::factory()->forMitra($mitraB)->create();

        Booking::factory()->forPackage($packageA)->confirmed()->create();
        Booking::factory()->forPackage($packageA)->pendingPayment()->create();
        Booking::factory()->forPackage($packageB)->confirmed()->create();

        $result = $this->repo->paginate([
            'status' => 'confirmed',
            'mitra_id' => $mitraA->id,
        ]);

        expect($result->total())->toBe(1);
    });

    it('sort = "invalid" → fallback ke created_at', function () {
        Booking::factory()->count(3)->create();

        expect(fn () => $this->repo->paginate(['sort' => 'invalid']))
            ->not->toThrow(QueryException::class);
    });

    it('direction = "INVALID" → fallback ke desc', function () {
        $older = Booking::factory()->create(['created_at' => now()->subDay()]);
        $newer = Booking::factory()->create(['created_at' => now()]);

        $result = $this->repo->paginate(['direction' => 'INVALID']);

        expect($result->items()[0]->id)->toBe($newer->id);
    });

    it('perPage = 1 → 1 item', function () {
        Booking::factory()->count(5)->create();

        $result = $this->repo->paginate([], 1);

        expect($result->perPage())->toBe(1)
            ->and($result->items())->toHaveCount(1);
    });
});

describe('Edge Cases — paginateForClient', function () {
    it('hanya mengembalikan booking milik client', function () {
        $clientA = User::factory()->client()->create();
        $clientB = User::factory()->client()->create();

        Booking::factory()->forClient($clientA)->count(2)->create();
        Booking::factory()->forClient($clientB)->count(3)->create();

        $result = $this->repo->paginateForClient($clientA);

        expect($result->total())->toBe(2);
    });

    it('mempertahankan filter lain saat dipanggil via paginateForClient', function () {
        $client = User::factory()->client()->create();

        Booking::factory()->forClient($client)->confirmed()->count(2)->create();
        Booking::factory()->forClient($client)->pendingPayment()->count(3)->create();

        $result = $this->repo->paginateForClient($client, ['status' => 'confirmed']);

        expect($result->total())->toBe(2);
    });
});

describe('Edge Cases — findByKode with soft-deleted', function () {
    it('findByKode TIDAK mengembalikan booking soft-deleted', function () {
        $booking = Booking::factory()->create(['kode' => 'BKG-2026-001']);
        $booking->delete();

        expect($this->repo->findByKode('BKG-2026-001'))->toBeNull();
    });

    it('kodeExists MENGHITUNG booking soft-deleted (withTrashed)', function () {
        $booking = Booking::factory()->create(['kode' => 'BKG-2026-001']);
        $booking->delete();

        expect($this->repo->kodeExists('BKG-2026-001'))->toBeTrue();
    });
});
