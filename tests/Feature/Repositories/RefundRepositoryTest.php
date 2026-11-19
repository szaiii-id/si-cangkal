<?php

use App\Enums\RefundStatus;
use App\Models\Booking;
use App\Models\Refund;
use App\Models\User;
use App\Repositories\Eloquent\RefundRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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
| - Refund TIDAK pakai SoftDeletes (hard delete)
|
*/

beforeEach(function () {

    $this->repo = new RefundRepository;
});

/*
|==========================================================================
| KATEGORI 1 — CONTRACT / RETURN TYPE & SHAPE
|==========================================================================
*/

describe('Contract — paginateForAdmin', function () {
    it('mengembalikan LengthAwarePaginator', function () {
        Refund::factory()->count(3)->create();

        $result = $this->repo->paginateForAdmin();

        expect($result)->toBeInstanceOf(LengthAwarePaginator::class);
    });

    it('item adalah instance Refund', function () {
        Refund::factory()->count(2)->create();

        $result = $this->repo->paginateForAdmin();

        expect($result->items())->each->toBeInstanceOf(Refund::class);
    });

    it('meng-eager-load relasi booking, booking.client, mitra, mitraInitiator', function () {
        Refund::factory()->create();

        $result = $this->repo->paginateForAdmin();
        $refund = $result->items()[0];

        expect($refund->relationLoaded('booking'))->toBeTrue()
            ->and($refund->relationLoaded('mitra'))->toBeTrue()
            ->and($refund->relationLoaded('mitraInitiator'))->toBeTrue()
            ->and($refund->booking->relationLoaded('client'))->toBeTrue();
    });

    it('mengurutkan default created_at DESC', function () {
        $older = Refund::factory()->create(['created_at' => now()->subDay()]);
        $newer = Refund::factory()->create(['created_at' => now()]);

        $result = $this->repo->paginateForAdmin();

        expect($result->items()[0]->id)->toBe($newer->id)
            ->and($result->items()[1]->id)->toBe($older->id);
    });
});

describe('Contract — paginateForMitra', function () {
    it('mengembalikan LengthAwarePaginator', function () {
        $mitra = User::factory()->mitra()->create();
        Refund::factory()->count(2)->create(['mitra_id' => $mitra->id]);

        $result = $this->repo->paginateForMitra($mitra);

        expect($result)->toBeInstanceOf(LengthAwarePaginator::class);
    });

    it('hanya mengembalikan refund milik mitra', function () {
        $mitraA = User::factory()->mitra()->create();
        $mitraB = User::factory()->mitra()->create();

        Refund::factory()->count(2)->create(['mitra_id' => $mitraA->id]);
        Refund::factory()->count(3)->create(['mitra_id' => $mitraB->id]);

        $result = $this->repo->paginateForMitra($mitraA);

        expect($result->total())->toBe(2);
    });
});

describe('Contract — findDetailById', function () {
    it('mengembalikan Refund dengan relasi lengkap', function () {
        $refund = Refund::factory()->create();

        $result = $this->repo->findDetailById($refund->id);

        expect($result)->toBeInstanceOf(Refund::class)
            ->and($result->relationLoaded('booking'))->toBeTrue()
            ->and($result->relationLoaded('mitra'))->toBeTrue()
            ->and($result->relationLoaded('mitraInitiator'))->toBeTrue()
            ->and($result->booking->relationLoaded('client'))->toBeTrue();
    });

    it('mengembalikan null jika tidak ada', function () {
        expect($this->repo->findDetailById(999_999))->toBeNull();
    });
});

describe('Contract — countByStatus', function () {
    it('countByStatus mengembalikan semua status enum', function () {
        $result = $this->repo->countByStatus();

        expect($result)->toHaveKeys(RefundStatus::values());
    });

    it('countByStatusForMitra mengembalikan semua status enum', function () {
        $mitra = User::factory()->mitra()->create();

        $result = $this->repo->countByStatusForMitra($mitra);

        expect($result)->toHaveKeys(RefundStatus::values());
    });
});

/*
|==========================================================================
| KATEGORI 2 — SECURITY (SQL Wildcard Injection)
|==========================================================================
*/

describe('Security — wildcard injection pada paginateForAdmin', function () {
    it('input _ TIDAK berfungsi sebagai wildcard pada client.name', function () {
        // Client name dengan SPASI (bukan underscore)
        $client = User::factory()->client()->create(['name' => 'Budi Santoso']);
        $booking = Booking::factory()->forClient($client)->create();
        Refund::factory()->create(['booking_id' => $booking->id]);

        // Search "Budi_"
        // - Tanpa escape: `_` = wildcard 1 karakter → match "Budi " → 1 (GAGAL)
        // - Dengan escape BENAR: `_` literal → tidak match "Budi " → 0 (LULUS)
        $result = $this->repo->paginateForAdmin(['search' => 'Budi_']);

        expect($result->total())->toBe(0);
    });

    it('input _ TIDAK berfungsi sebagai wildcard pada mitra.name', function () {
        // Mitra name dengan SPASI (bukan underscore)
        $mitra = User::factory()->mitra()->create(['name' => 'Budi Santoso']);
        Refund::factory()->create(['mitra_id' => $mitra->id]);

        // Search "Budi_"
        // - Tanpa escape: `_` = wildcard → match "Budi " → 1 (GAGAL)
        // - Dengan escape BENAR: `_` literal → tidak match → 0 (LULUS)
        $result = $this->repo->paginateForAdmin(['search' => 'Budi_']);

        expect($result->total())->toBe(0);
    });

    it('input % TIDAK berfungsi sebagai wildcard pada booking.kode', function () {
        $booking = Booking::factory()->create(['kode' => 'BKG-2026-100']);
        Refund::factory()->create(['booking_id' => $booking->id]);

        $result = $this->repo->paginateForAdmin(['search' => 'BKG-2026-1%']);

        expect($result->total())->toBe(0);
    });

    it('input SQL injection classik aman (parameter binding)', function () {
        Refund::factory()->create();

        $result = $this->repo->paginateForAdmin(['search' => "' OR 1=1--"]);

        expect($result->total())->toBe(0);
    });

    it('input SQL injection dengan quote tidak menyebabkan error syntax', function () {
        Refund::factory()->create();

        expect(fn () => $this->repo->paginateForAdmin(['search' => "budi' OR '1'='1"]))
            ->not->toThrow(QueryException::class);
    });

    it('search tidak membedakan huruf besar/kecil', function () {
        $mitra = User::factory()->mitra()->create(['name' => 'Budi Santoso']);
        Refund::factory()->create(['mitra_id' => $mitra->id]);

        $result = $this->repo->paginateForAdmin(['search' => 'BUDI']);

        expect($result->total())->toBe(1);
    });
});

/*
|==========================================================================
| KATEGORI 3 — CONCURRENCY (FK Constraints)
|==========================================================================
|
| Catatan: refunds.booking_id TIDAK unique → multiple refund per booking
| diizinkan. Yang penting: FK cascade/restrict.
|
*/

describe('Concurrency — FK constraints', function () {
    it('create refund dengan booking_id yang tidak ada → QueryException (FK)', function () {
        expect(fn () => Refund::factory()->create(['booking_id' => 999_999]))
            ->toThrow(QueryException::class);
    });

    it('create refund dengan mitra_id yang tidak ada → QueryException (FK)', function () {
        expect(fn () => Refund::factory()->create(['mitra_id' => 999_999]))
            ->toThrow(QueryException::class);
    });

    it('booking di-HARD-DELETE → refund ikut terhapus (cascade)', function () {
        $booking = Booking::factory()->create();
        $refund = Refund::factory()->create(['booking_id' => $booking->id]);

        // Booking pakai SoftDeletes → delete() = soft delete (tidak trigger cascade).
        // Untuk trigger cascade FK, harus forceDelete().
        $booking->forceDelete();

        $this->assertDatabaseMissing('refunds', ['id' => $refund->id]);
    });

    it('booking di-SOFT-DELETE → refund TETAP ada (audit trail)', function () {
        $booking = Booking::factory()->create();
        $refund = Refund::factory()->create(['booking_id' => $booking->id]);

        // Soft delete booking — desain bisnis: refund tetap ada untuk audit.
        $booking->delete();

        $this->assertDatabaseHas('refunds', ['id' => $refund->id]);
    });

    it('mitra dihapus → refund TETAP ada (mitra_id restrict → error)', function () {
        $mitra = User::factory()->mitra()->create();
        Refund::factory()->create(['mitra_id' => $mitra->id]);

        // mitra_id restrictOnDelete → mitra tidak bisa dihapus kalau ada refund
        expect(fn () => $mitra->delete())
            ->toThrow(QueryException::class);
    });
});

/*
|==========================================================================
| KATEGORI 4 — IDEMPOTENCY
|==========================================================================
*/

describe('Idempotency', function () {
    it('countByStatus konsisten saat dipanggil berulang', function () {
        Refund::factory()->count(3)->create();

        $first = $this->repo->countByStatus();
        $second = $this->repo->countByStatus();

        expect($first)->toBe($second);
    });

    it('findDetailById konsisten saat dipanggil berulang', function () {
        $refund = Refund::factory()->create();

        $first = $this->repo->findDetailById($refund->id);
        $second = $this->repo->findDetailById($refund->id);

        expect($first->id)->toBe($second->id);
    });
});

/*
|==========================================================================
| KATEGORI 5 — DATA INTEGRITY
|==========================================================================
*/

describe('Data Integrity — countByStatus', function () {
    it('countByStatus menghitung per status dengan benar', function () {
        Refund::factory()->count(3)->create(['status' => RefundStatus::PENDING->value]);
        Refund::factory()->succeeded()->count(2)->create();
        Refund::factory()->failed()->count(1)->create();

        $result = $this->repo->countByStatus();

        expect($result[RefundStatus::PENDING->value])->toBe(3)
            ->and($result[RefundStatus::SUCCEEDED->value])->toBe(2)
            ->and($result[RefundStatus::FAILED->value])->toBe(1)
            ->and($result[RefundStatus::CANCELLED->value])->toBe(0);
    });

    it('countByStatusForMitra hanya menghitung refund mitra itu', function () {
        $mitraA = User::factory()->mitra()->create();
        $mitraB = User::factory()->mitra()->create();

        Refund::factory()->count(3)->create([
            'mitra_id' => $mitraA->id,
            'status' => RefundStatus::PENDING->value,
        ]);
        Refund::factory()->count(5)->create([
            'mitra_id' => $mitraB->id,
            'status' => RefundStatus::PENDING->value,
        ]);

        $result = $this->repo->countByStatusForMitra($mitraA);

        expect($result[RefundStatus::PENDING->value])->toBe(3);
    });

    it('countByStatus mengembalikan 0 untuk status yang tidak ada', function () {
        Refund::factory()->count(2)->create(['status' => RefundStatus::PENDING->value]);

        $result = $this->repo->countByStatus();

        expect($result[RefundStatus::SUCCEEDED->value])->toBe(0)
            ->and($result[RefundStatus::FAILED->value])->toBe(0)
            ->and($result[RefundStatus::CANCELLED->value])->toBe(0);
    });
});

describe('Data Integrity — findDetailById null-safe', function () {
    it('findDetailById tidak error jika mitra null', function () {
        $refund = Refund::factory()->create(['mitra_id' => null]);

        $result = $this->repo->findDetailById($refund->id);

        expect($result)->toBeInstanceOf(Refund::class)
            ->and($result->mitra)->toBeNull();
    });

    it('findDetailById tidak error jika mitraInitiator null', function () {
        $refund = Refund::factory()->create(['initiated_by_mitra' => null]);

        $result = $this->repo->findDetailById($refund->id);

        expect($result)->toBeInstanceOf(Refund::class)
            ->and($result->mitraInitiator)->toBeNull();
    });

    it('findDetailById tidak error jika mitra DAN mitraInitiator null', function () {
        $refund = Refund::factory()->create([
            'mitra_id' => null,
            'initiated_by_mitra' => null,
        ]);

        $result = $this->repo->findDetailById($refund->id);

        expect($result)->toBeInstanceOf(Refund::class)
            ->and($result->mitra)->toBeNull()
            ->and($result->mitraInitiator)->toBeNull();
    });
});

describe('Data Integrity — mitraInitiator nullOnDelete', function () {
    it('mitraInitiator dihapus → initiated_by_mitra jadi null (nullOnDelete)', function () {
        $initiator = User::factory()->mitra()->create();
        $refund = Refund::factory()->create(['initiated_by_mitra' => $initiator->id]);

        $initiator->delete();

        $refund->refresh();
        expect($refund->initiated_by_mitra)->toBeNull();
    });
});

/*
|==========================================================================
| KATEGORI 6 — ERROR HANDLING
|==========================================================================
*/

describe('Error Handling — constraint violations', function () {
    it('create dengan amount null → QueryException (NOT NULL)', function () {
        $booking = Booking::factory()->create();

        expect(fn () => Refund::factory()->create([
            'booking_id' => $booking->id,
            'amount' => null,
        ]))->toThrow(QueryException::class);
    });

    it('create dengan status enum invalid → ValueError (enum cast)', function () {
        $booking = Booking::factory()->create();

        expect(fn () => Refund::factory()->create([
            'booking_id' => $booking->id,
            'status' => 'ngawur',
        ]))->toThrow(ValueError::class);
    });

    it('create dengan reason enum invalid → ValueError', function () {
        $booking = Booking::factory()->create();

        expect(fn () => Refund::factory()->create([
            'booking_id' => $booking->id,
            'reason' => 'ngawur',
        ]))->toThrow(ValueError::class);
    });
});

/*
|==========================================================================
| KATEGORI 7 — EDGE CASES & BVA
|==========================================================================
*/

describe('Edge Cases — paginateForAdmin filters', function () {
    it('filter status = PENDING → hanya PENDING', function () {
        Refund::factory()->count(2)->create(['status' => RefundStatus::PENDING->value]);
        Refund::factory()->succeeded()->count(3)->create();

        $result = $this->repo->paginateForAdmin(['status' => 'PENDING']);

        expect($result->total())->toBe(2);
    });

    it('filter status = SUCCEEDED → hanya SUCCEEDED', function () {
        Refund::factory()->count(2)->create(['status' => RefundStatus::PENDING->value]);
        Refund::factory()->succeeded()->count(3)->create();

        $result = $this->repo->paginateForAdmin(['status' => 'SUCCEEDED']);

        expect($result->total())->toBe(3);
    });

    it('search by booking.kode', function () {
        $booking = Booking::factory()->create(['kode' => 'BKG-2026-001']);
        Refund::factory()->create(['booking_id' => $booking->id]);
        Refund::factory()->create(); // lain

        $result = $this->repo->paginateForAdmin(['search' => 'BKG-2026-001']);

        expect($result->total())->toBe(1);
    });

    it('search by mitra.name', function () {
        $mitra = User::factory()->mitra()->create(['name' => 'Budi Mitra']);
        Refund::factory()->create(['mitra_id' => $mitra->id]);
        Refund::factory()->create(); // lain

        $result = $this->repo->paginateForAdmin(['search' => 'Budi Mitra']);

        expect($result->total())->toBe(1);
    });

    it('search by booking.client.name', function () {
        $client = User::factory()->client()->create(['name' => 'Budi Client']);
        $booking = Booking::factory()->forClient($client)->create();
        Refund::factory()->create(['booking_id' => $booking->id]);
        Refund::factory()->create(); // lain

        $result = $this->repo->paginateForAdmin(['search' => 'Budi Client']);

        expect($result->total())->toBe(1);
    });

    it('search kosong string → skip filter', function () {
        Refund::factory()->count(3)->create();

        $result = $this->repo->paginateForAdmin(['search' => '']);

        expect($result->total())->toBe(3);
    });

    it('perPage = 1 → 1 item', function () {
        Refund::factory()->count(5)->create();

        $result = $this->repo->paginateForAdmin([], 1);

        expect($result->perPage())->toBe(1)
            ->and($result->items())->toHaveCount(1);
    });

    it('filter combine status + search', function () {
        $mitraA = User::factory()->mitra()->create(['name' => 'Budi']);
        Refund::factory()->create([
            'mitra_id' => $mitraA->id,
            'status' => RefundStatus::PENDING->value,
        ]);
        Refund::factory()->succeeded()->create(['mitra_id' => $mitraA->id]);

        $result = $this->repo->paginateForAdmin([
            'search' => 'Budi',
            'status' => 'PENDING',
        ]);

        expect($result->total())->toBe(1);
    });
});

describe('Edge Cases — paginateForMitra', function () {
    it('mempertahankan filter status saat dipanggil via paginateForMitra', function () {
        $mitra = User::factory()->mitra()->create();
        Refund::factory()->count(2)->create([
            'mitra_id' => $mitra->id,
            'status' => RefundStatus::PENDING->value,
        ]);
        Refund::factory()->succeeded()->count(3)->create(['mitra_id' => $mitra->id]);

        $result = $this->repo->paginateForMitra($mitra, ['status' => 'PENDING']);

        expect($result->total())->toBe(2);
    });

    it('refund dengan mitra_id null TIDAK muncul di paginateForMitra', function () {
        $mitra = User::factory()->mitra()->create();
        Refund::factory()->create(['mitra_id' => null]);
        Refund::factory()->create(['mitra_id' => $mitra->id]);

        $result = $this->repo->paginateForMitra($mitra);

        expect($result->total())->toBe(1);
    });
});
