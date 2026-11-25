<?php

use App\Enums\RefundReason;
use App\Enums\RefundStatus;
use App\Models\Booking;
use App\Models\MitraBankAccount;
use App\Models\Package;
use App\Models\Refund;
use App\Models\User;
use App\Services\Refund\RefundApprovalService;
use App\Services\Refund\RefundQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery\MockInterface;

uses(RefreshDatabase::class);

/*
|==========================================================================
| SETUP — Integration Test
|==========================================================================
|
| RefundController (mitra) — MUTASI UANG (approve/reject refund).
|
| Route (prefix /mitra):
| - GET  /refunds                  → index
| - POST /refunds/{refund}/approve → approve
| - POST /refunds/{refund}/reject  → reject
|
| Middleware: auth, password.changed, role:mitra, mitra.complete
| Policy: RefundPolicy (viewAny, approve, reject)
| FormRequest: ListRefundRequest, ApproveRefundRequest, RejectRefundRequest
|
|   Catatan:
| - `config(['inertia.testing.ensure_pages_exist' => false])` — backend test.
| - `RefundApprovalService` di-mock (bukan final?) — fokus test CONTROLLER.
| - `RefundQueryService` pakai real (tidak final) — test integration.
| - Mitra WAJIB punya bank account (middleware `mitra.complete`).
| - Policy `approve`/`reject` cek ownership + status PENDING.
|
*/

beforeEach(function () {
    if (method_exists($this, 'withoutVite')) {
        $this->withoutVite();
    }

    config(['inertia.testing.ensure_pages_exist' => false]);

    $this->mitra = User::factory()->mitra()->create([
        'must_change_password' => false,
    ]);

    MitraBankAccount::create([
        'user_id' => $this->mitra->id,
        'tipe' => 'bank',
        'nama_penyedia' => 'BCA',
        'nomor_rekening' => '1234567890',
        'nama_pemilik' => 'Budi',
        'is_primary' => true,
    ]);

    $this->mitra2 = User::factory()->mitra()->create([
        'must_change_password' => false,
    ]);

    MitraBankAccount::create([
        'user_id' => $this->mitra2->id,
        'tipe' => 'bank',
        'nama_penyedia' => 'BRI',
        'nomor_rekening' => '9999999999',
        'nama_pemilik' => 'Ani',
        'is_primary' => true,
    ]);

    $this->client = User::factory()->client()->create([
        'must_change_password' => false,
    ]);
});

/*
|==========================================================================
| HELPER — buat refund
|==========================================================================
*/
function makeRefundForMitra(User $mitra, User $client, array $overrides = []): Refund
{
    $package = Package::factory()->forMitra($mitra)->aktif()->create();
    $booking = Booking::factory()
        ->forPackage($package)
        ->forClient($client)
        ->confirmed()
        ->create(['mitra_id' => $mitra->id]);

    return Refund::factory()->create(array_merge([
        'booking_id' => $booking->id,
        'mitra_id' => $mitra->id,
        'status' => RefundStatus::PENDING->value,
        'amount' => 500_000,
        'reason' => RefundReason::CANCELLATION->value,
        'initiated_by_mitra' => $mitra->id,
        'catatan' => null,
    ], $overrides));
}

/*
|==========================================================================
| KATEGORI 1 — CONTRACT / API SCHEMA
|==========================================================================
*/

describe('Contract — index', function () {
    it('GET /mitra/refunds → render Mitra/Refunds/Index', function () {
        $this->actingAs($this->mitra)
            ->get('/mitra/refunds')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Mitra/Refunds/Index')
                ->has('refunds')
                ->has('filters')
                ->has('statusCounts')
            );
    });

    it('filters default: search=null, status=null', function () {
        $this->actingAs($this->mitra)
            ->get('/mitra/refunds')
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.search', null)
                ->where('filters.status', null)
            );
    });

    it('hanya menampilkan refund milik mitra sendiri', function () {
        makeRefundForMitra($this->mitra, $this->client);
        makeRefundForMitra($this->mitra2, $this->client);

        $this->actingAs($this->mitra)
            ->get('/mitra/refunds')
            ->assertInertia(fn (Assert $page) => $page
                ->where('refunds.total', 1)
            );
    });

    it('route name = mitra.refunds.index', function () {
        expect(route('mitra.refunds.index'))->toBe(url('/mitra/refunds'));
    });
});

describe('Contract — approve redirect', function () {
    it('POST /mitra/refunds/{id}/approve valid → redirect ke refunds.index + status', function () {
        $refund = makeRefundForMitra($this->mitra, $this->client);

        $this->mock(RefundApprovalService::class, function (MockInterface $m) use ($refund) {
            $m->shouldReceive('approve')
                ->once()
                ->with(
                    Mockery::on(fn ($r) => $r->id === $refund->id),
                    Mockery::on(fn ($u) => $u->id === $this->mitra->id),
                    'Catatan approval',
                )
                ->andReturn($refund);
        });

        $response = $this->actingAs($this->mitra)
            ->post("/mitra/refunds/{$refund->id}/approve", ['catatan' => 'Catatan approval']);

        $response->assertRedirect(route('mitra.refunds.index'))
            ->assertSessionHas('status', 'Refund berhasil diproses secara manual.');
    });
});

describe('Contract — reject redirect', function () {
    it('POST /mitra/refunds/{id}/reject valid → redirect ke refunds.index + status', function () {
        $refund = makeRefundForMitra($this->mitra, $this->client);

        $this->mock(RefundApprovalService::class, function (MockInterface $m) use ($refund) {
            $m->shouldReceive('reject')
                ->once()
                ->with(
                    Mockery::on(fn ($r) => $r->id === $refund->id),
                    Mockery::on(fn ($u) => $u->id === $this->mitra->id),
                    'Data rekening salah',
                );
        });

        $response = $this->actingAs($this->mitra)
            ->post("/mitra/refunds/{$refund->id}/reject", ['alasan_penolakan' => 'Data rekening salah']);

        $response->assertRedirect(route('mitra.refunds.index'))
            ->assertSessionHas('status', 'Pengajuan refund berhasil ditolak.');
    });
});

/*
|==========================================================================
| KATEGORI 2 — SECURITY & AUTHORIZATION
|==========================================================================
*/

describe('Security — guest & role', function () {
    it('guest di-redirect ke login (index)', function () {
        $this->get('/mitra/refunds')->assertRedirect(route('login'));
    });

    it('admin di-redirect ke admin.dashboard (index)', function () {
        $admin = User::factory()->admin()->create(['must_change_password' => false]);

        $this->actingAs($admin)
            ->get('/mitra/refunds')
            ->assertRedirect(route('admin.dashboard'));
    });

    it('client di-redirect ke client.dashboard (index)', function () {
        $this->actingAs($this->client)
            ->get('/mitra/refunds')
            ->assertRedirect(route('client.dashboard'));
    });

    it('mitra must_change_password=true di-redirect ke password.change', function () {
        $needChange = User::factory()->mitra()->create(['must_change_password' => true]);

        $this->actingAs($needChange)
            ->get('/mitra/refunds')
            ->assertRedirect(route('password.change'));
    });
});

describe('Security — ownership (Policy)', function () {
    it('mitra TIDAK bisa approve refund mitra lain → 403', function () {
        $refund = makeRefundForMitra($this->mitra2, $this->client);

        $this->mock(RefundApprovalService::class, function (MockInterface $m) {
            $m->shouldNotReceive('approve');
        });

        $this->actingAs($this->mitra)
            ->post("/mitra/refunds/{$refund->id}/approve", ['catatan' => 'Catatan'])
            ->assertForbidden();
    });

    it('mitra TIDAK bisa reject refund mitra lain → 403', function () {
        $refund = makeRefundForMitra($this->mitra2, $this->client);

        $this->mock(RefundApprovalService::class, function (MockInterface $m) {
            $m->shouldNotReceive('reject');
        });

        $this->actingAs($this->mitra)
            ->post("/mitra/refunds/{$refund->id}/reject", ['alasan_penolakan' => 'Alasan valid'])
            ->assertForbidden();
    });
});

/*
|==========================================================================
| KATEGORI 3 — CONCURRENCY
|==========================================================================
*/

describe('Concurrency — approve 2x', function () {
    it('approve 2x → mock service dipanggil 2x (idempoten di service)', function () {
        $refund = makeRefundForMitra($this->mitra, $this->client);

        $this->mock(RefundApprovalService::class, function (MockInterface $m) use ($refund) {
            $m->shouldReceive('approve')->twice()->andReturn($refund);
        });

        $this->actingAs($this->mitra)
            ->post("/mitra/refunds/{$refund->id}/approve", ['catatan' => 'Catatan'])
            ->assertRedirect();

        $this->actingAs($this->mitra)
            ->post("/mitra/refunds/{$refund->id}/approve", ['catatan' => 'Catatan'])
            ->assertRedirect();
    });
});

/*
|==========================================================================
| KATEGORI 4 — IDEMPOTENCY
|==========================================================================
*/

describe('Idempotency', function () {
    it('approve 2x pada refund sama → mock dipanggil 2x', function () {
        $refund = makeRefundForMitra($this->mitra, $this->client);

        $this->mock(RefundApprovalService::class, function (MockInterface $m) use ($refund) {
            $m->shouldReceive('approve')->twice()->andReturn($refund);
        });

        $this->actingAs($this->mitra)->post("/mitra/refunds/{$refund->id}/approve", ['catatan' => 'Catatan']);
        $this->actingAs($this->mitra)->post("/mitra/refunds/{$refund->id}/approve", ['catatan' => 'Catatan']);
    });
});

/*
|==========================================================================
| KATEGORI 5 — DATA INTEGRITY & STATE TRANSITION
|==========================================================================
*/

describe('Data Integrity — approve', function () {
    it('approve → service dipanggil dengan refund + mitra + catatan', function () {
        $refund = makeRefundForMitra($this->mitra, $this->client);

        $this->mock(RefundApprovalService::class, function (MockInterface $m) use ($refund) {
            $m->shouldReceive('approve')
                ->once()
                ->with(
                    Mockery::on(fn ($r) => $r->id === $refund->id),
                    Mockery::on(fn ($u) => $u->id === $this->mitra->id),
                    'Catatan approval',
                )
                ->andReturn($refund);
        });

        $this->actingAs($this->mitra)
            ->post("/mitra/refunds/{$refund->id}/approve", ['catatan' => 'Catatan approval'])
            ->assertRedirect()
            ->assertSessionHas('status');
    });

    it('approve dengan catatan null → service dipanggil dengan null', function () {
        $refund = makeRefundForMitra($this->mitra, $this->client);

        $this->mock(RefundApprovalService::class, function (MockInterface $m) use ($refund) {
            $m->shouldReceive('approve')
                ->once()
                ->with(
                    Mockery::on(fn ($r) => $r->id === $refund->id),
                    Mockery::on(fn ($u) => $u->id === $this->mitra->id),
                    null,
                )
                ->andReturn($refund);
        });

        $this->actingAs($this->mitra)
            ->post("/mitra/refunds/{$refund->id}/approve", [])
            ->assertRedirect();
    });
});

describe('Data Integrity — reject', function () {
    it('reject → service dipanggil dengan refund + mitra + alasan', function () {
        $refund = makeRefundForMitra($this->mitra, $this->client);

        $this->mock(RefundApprovalService::class, function (MockInterface $m) use ($refund) {
            $m->shouldReceive('reject')
                ->once()
                ->with(
                    Mockery::on(fn ($r) => $r->id === $refund->id),
                    Mockery::on(fn ($u) => $u->id === $this->mitra->id),
                    'Data rekening salah',
                );
        });

        $this->actingAs($this->mitra)
            ->post("/mitra/refunds/{$refund->id}/reject", ['alasan_penolakan' => 'Data rekening salah'])
            ->assertRedirect()
            ->assertSessionHas('status');
    });
});

/*
|==========================================================================
| KATEGORI 6 — ERROR HANDLING (Validation)
|==========================================================================
*/

describe('Error Handling — approve validation', function () {
    it('catatan > 1000 karakter → error', function () {
        $refund = makeRefundForMitra($this->mitra, $this->client);

        $this->actingAs($this->mitra)
            ->post("/mitra/refunds/{$refund->id}/approve", ['catatan' => str_repeat('a', 1001)])
            ->assertSessionHasErrors('catatan');
    });

    it('catatan < 3 karakter → error', function () {
        $refund = makeRefundForMitra($this->mitra, $this->client);

        $this->actingAs($this->mitra)
            ->post("/mitra/refunds/{$refund->id}/approve", ['catatan' => 'ab'])
            ->assertSessionHasErrors('catatan');
    });
});

describe('Error Handling — reject validation', function () {
    it('alasan_penolakan kosong → error', function () {
        $refund = makeRefundForMitra($this->mitra, $this->client);

        $this->actingAs($this->mitra)
            ->post("/mitra/refunds/{$refund->id}/reject", ['alasan_penolakan' => ''])
            ->assertSessionHasErrors('alasan_penolakan');
    });

    it('alasan_penolakan < 5 karakter → error', function () {
        $refund = makeRefundForMitra($this->mitra, $this->client);

        $this->actingAs($this->mitra)
            ->post("/mitra/refunds/{$refund->id}/reject", ['alasan_penolakan' => 'abc'])
            ->assertSessionHasErrors('alasan_penolakan');
    });
});

describe('Error Handling — status bukan PENDING', function () {
    it('approve refund SUCCEEDED → error (FormRequest after)', function () {
        $refund = makeRefundForMitra($this->mitra, $this->client, [
            'status' => RefundStatus::SUCCEEDED->value,
        ]);

        $this->actingAs($this->mitra)
            ->post("/mitra/refunds/{$refund->id}/approve", ['catatan' => 'Catatan'])
            ->assertSessionHasErrors('catatan');
    });

    it('reject refund CANCELLED → error (FormRequest after)', function () {
        $refund = makeRefundForMitra($this->mitra, $this->client, [
            'status' => RefundStatus::CANCELLED->value,
        ]);

        $this->actingAs($this->mitra)
            ->post("/mitra/refunds/{$refund->id}/reject", ['alasan_penolakan' => 'Alasan valid'])
            ->assertSessionHasErrors('alasan_penolakan');
    });
});

describe('Error Handling — ValidationException from service', function () {
    it('service throw ValidationException → redirect back + errors', function () {
        $refund = makeRefundForMitra($this->mitra, $this->client);

        $this->mock(RefundApprovalService::class, function (MockInterface $m) {
            $m->shouldReceive('approve')
                ->once()
                ->andThrow(ValidationException::withMessages([
                    'status' => 'Status tidak valid.',
                ]));
        });

        $this->actingAs($this->mitra)
            ->from('/mitra/refunds')
            ->post("/mitra/refunds/{$refund->id}/approve", ['catatan' => 'Catatan'])
            ->assertRedirect()
            ->assertSessionHasErrors('status');
    });
});

/*
|==========================================================================
| KATEGORI 7 — EDGE CASES
|==========================================================================
*/

describe('Edge Cases — index filters', function () {
    it('filter status=PENDING → hanya PENDING', function () {
        makeRefundForMitra($this->mitra, $this->client, ['status' => RefundStatus::PENDING->value]);
        makeRefundForMitra($this->mitra, $this->client, ['status' => RefundStatus::SUCCEEDED->value]);

        $this->actingAs($this->mitra)
            ->get('/mitra/refunds?status=PENDING')
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.status', 'PENDING')
            );
    });

    it('search kode booking → return match', function () {
        $refund = makeRefundForMitra($this->mitra, $this->client);
        $kode = $refund->booking->kode;

        $this->actingAs($this->mitra)
            ->get('/mitra/refunds?search='.$kode)
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.search', $kode)
            );
    });
});
