<?php

use App\Enums\RefundStatus;
use App\Models\Booking;
use App\Models\Refund;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/*
|==========================================================================
| SETUP — Integration Test
|==========================================================================
|
| RefundController (admin) — read-only daftar refund.
|
| Route: GET /admin/refunds (admin.refunds.index)
|
| Controller response (RefundResource::collection) — shape WRAPPED:
|   refunds: {
|     data: [...],              // RefundResource items
|     links: { first, last, prev, next },
|     meta: {
|       current_page, per_page, last_page, total, from, to, path
|     }
|   }
|
| Catatan:
| - `config(['inertia.testing.ensure_pages_exist' => false])` di beforeEach.
| - FormRequest `RefundSearchRequest` validasi input.
|
*/

beforeEach(function () {
    if (method_exists($this, 'withoutVite')) {
        $this->withoutVite();
    }

    config(['inertia.testing.ensure_pages_exist' => false]);

    $this->admin = User::factory()->admin()->create([
        'must_change_password' => false,
    ]);
    $this->mitra = User::factory()->mitra()->create([
        'must_change_password' => false,
    ]);
    $this->client = User::factory()->client()->create([
        'must_change_password' => false,
    ]);
});

/*
|==========================================================================
| KATEGORI 1 — CONTRACT / API SCHEMA
|==========================================================================
*/

describe('Contract — index', function () {
    it('GET /admin/refunds → render Admin/Refunds/Index', function () {
        $this->actingAs($this->admin)
            ->get('/admin/refunds')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Refunds/Index')
                ->has('refunds')
                ->has('filters')
                ->has('statusCounts')
            );
    });

    it('refunds adalah Resource paginator (data, links, meta)', function () {
        makeRefund($this->mitra, $this->client);

        $this->actingAs($this->admin)
            ->get('/admin/refunds')
            ->assertInertia(fn (Assert $page) => $page
                ->has('refunds.data')
                ->has('refunds.links')
                ->has('refunds.meta')
                ->has('refunds.meta.current_page')
                ->has('refunds.meta.per_page')
                ->has('refunds.meta.last_page')
                ->has('refunds.meta.total')
            );
    });

    it('refunds.data item punya shape Resource: id, status, amount + field turunan', function () {
        $refund = makeRefund($this->mitra, $this->client);

        $this->actingAs($this->admin)
            ->get('/admin/refunds')
            ->assertInertia(fn (Assert $page) => $page
                ->has('refunds.data.0.id')
                ->has('refunds.data.0.status')
                ->has('refunds.data.0.amount')
                // field turunan dari RefundResource
                ->has('refunds.data.0.amountRupiah')
                ->has('refunds.data.0.statusLabel')
                ->has('refunds.data.0.reasonLabel')
                ->has('refunds.data.0.isFinal')
                ->has('refunds.data.0.currency')
                ->where('refunds.data.0.id', $refund->id)
                ->where('refunds.data.0.status', $refund->status->value)
            );
    });

    it('refunds.data item punya relasi booking bila di-eager-load', function () {
        makeRefund($this->mitra, $this->client);

        $this->actingAs($this->admin)
            ->get('/admin/refunds')
            ->assertInertia(fn (Assert $page) => $page
                ->has('refunds.data.0.booking')
                ->has('refunds.data.0.booking.id')
                ->has('refunds.data.0.booking.kode')
            );
    });

    it('filters default: search=null, status=null', function () {
        $this->actingAs($this->admin)
            ->get('/admin/refunds')
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.search', null)
                ->where('filters.status', null)
            );
    });

    it('filters mengirim search + status dari query', function () {
        $this->actingAs($this->admin)
            ->get('/admin/refunds?search=budi&status=PENDING')
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.search', 'budi')
                ->where('filters.status', 'PENDING')
            );
    });

    it('statusCounts punya key semua status Refund', function () {
        $this->actingAs($this->admin)
            ->get('/admin/refunds')
            ->assertInertia(fn (Assert $page) => $page
                ->has('statusCounts.'.RefundStatus::PENDING->value)
                ->has('statusCounts.'.RefundStatus::SUCCEEDED->value)
                ->has('statusCounts.'.RefundStatus::FAILED->value)
                ->has('statusCounts.'.RefundStatus::CANCELLED->value)
            );
    });

    it('route name = admin.refunds.index', function () {
        expect(route('admin.refunds.index'))->toBe(url('/admin/refunds'));
    });
});

/*
|==========================================================================
| KATEGORI 2 — SECURITY & AUTHORIZATION
|==========================================================================
*/

describe('Security — guest & role', function () {
    it('guest di-redirect ke login', function () {
        $this->get('/admin/refunds')->assertRedirect(route('login'));
    });

    it('mitra di-redirect ke mitra.dashboard', function () {
        $this->actingAs($this->mitra)
            ->get('/admin/refunds')
            ->assertRedirect(route('mitra.dashboard'));
    });

    it('client di-redirect ke client.dashboard', function () {
        $this->actingAs($this->client)
            ->get('/admin/refunds')
            ->assertRedirect(route('client.dashboard'));
    });

    it('admin must_change_password=true di-redirect ke password.change', function () {
        $needChange = User::factory()->admin()->create(['must_change_password' => true]);

        $this->actingAs($needChange)
            ->get('/admin/refunds')
            ->assertRedirect(route('password.change'));
    });

    it('admin must_change_password=false bisa akses', function () {
        $this->actingAs($this->admin)
            ->get('/admin/refunds')
            ->assertOk();
    });
});

/*
|==========================================================================
| KATEGORI 3 — CONCURRENCY
|==========================================================================
*/

describe('Concurrency — read-only consistency', function () {
    it('2x GET dengan query sama → total identik', function () {
        makeRefund($this->mitra, $this->client, ['status' => RefundStatus::PENDING]);
        makeRefund($this->mitra, $this->client, ['status' => RefundStatus::SUCCEEDED]);

        $first = $this->actingAs($this->admin)
            ->get('/admin/refunds')
            ->viewData('page')['props']['refunds']['meta']['total'];

        $second = $this->actingAs($this->admin)
            ->get('/admin/refunds')
            ->viewData('page')['props']['refunds']['meta']['total'];

        expect($first)->toBe($second);
    });
});

/*
|==========================================================================
| KATEGORI 4 — IDEMPOTENCY
|==========================================================================
*/

describe('Idempotency', function () {
    it('GET berulang tidak mengubah data', function () {
        makeRefund($this->mitra, $this->client);
        $countBefore = Refund::count();

        $this->actingAs($this->admin)->get('/admin/refunds');
        $this->actingAs($this->admin)->get('/admin/refunds');

        expect(Refund::count())->toBe($countBefore);
    });

    it('search yang sama → urutan & isi konsisten', function () {
        makeRefund($this->mitra, $this->client);
        makeRefund($this->mitra, $this->client);

        $r1 = $this->actingAs($this->admin)
            ->get('/admin/refunds?search=&per_page=15')
            ->viewData('page')['props']['refunds']['data'];
        $r2 = $this->actingAs($this->admin)
            ->get('/admin/refunds?search=&per_page=15')
            ->viewData('page')['props']['refunds']['data'];

        expect(array_column($r1, 'id'))->toBe(array_column($r2, 'id'));
    });
});

/*
|==========================================================================
| KATEGORI 5 — DATA INTEGRITY
|==========================================================================
*/

describe('Data Integrity — filter status', function () {
    it('filter status=PENDING → hanya PENDING', function () {
        makeRefund($this->mitra, $this->client, ['status' => RefundStatus::PENDING]);
        makeRefund($this->mitra, $this->client, ['status' => RefundStatus::SUCCEEDED]);
        makeRefund($this->mitra, $this->client, ['status' => RefundStatus::FAILED]);

        $this->actingAs($this->admin)
            ->get('/admin/refunds?status=PENDING')
            ->assertInertia(fn (Assert $page) => $page
                ->where('refunds.meta.total', 1)
                ->where('refunds.data.0.status', RefundStatus::PENDING->value)
                ->where('refunds.data.0.statusLabel', 'Sedang Diproses')
            );
    });

    it('filter status=SUCCEEDED → hanya SUCCEEDED', function () {
        makeRefund($this->mitra, $this->client, ['status' => RefundStatus::PENDING]);
        makeRefund($this->mitra, $this->client, ['status' => RefundStatus::SUCCEEDED]);

        $this->actingAs($this->admin)
            ->get('/admin/refunds?status=SUCCEEDED')
            ->assertInertia(fn (Assert $page) => $page
                ->where('refunds.meta.total', 1)
                ->where('refunds.data.0.status', RefundStatus::SUCCEEDED->value)
            );
    });
});

describe('Data Integrity — search', function () {
    it('search by booking.kode', function () {
        $booking = Booking::factory()->create(['kode' => 'BKG-SEARCH-001']);
        makeRefund($this->mitra, $this->client, ['booking_id' => $booking->id]);
        makeRefund($this->mitra, $this->client);

        $this->actingAs($this->admin)
            ->get('/admin/refunds?search=BKG-SEARCH-001')
            ->assertInertia(fn (Assert $page) => $page
                ->where('refunds.meta.total', 1)
            );
    });

    it('search by mitra.name', function () {
        $mitra = User::factory()->mitra()->create([
            'name' => 'Budi Mitra',
            'must_change_password' => false,
        ]);
        makeRefund($mitra, $this->client);
        makeRefund($this->mitra, $this->client);

        $this->actingAs($this->admin)
            ->get('/admin/refunds?search=Budi Mitra')
            ->assertInertia(fn (Assert $page) => $page
                ->where('refunds.meta.total', 1)
            );
    });

    it('search by booking.client.name', function () {
        $client = User::factory()->client()->create([
            'name' => 'Budi Client',
            'must_change_password' => false,
        ]);
        $booking = Booking::factory()->forClient($client)->create();
        makeRefund($this->mitra, $client, ['booking_id' => $booking->id]);
        makeRefund($this->mitra, $this->client);

        $this->actingAs($this->admin)
            ->get('/admin/refunds?search=Budi Client')
            ->assertInertia(fn (Assert $page) => $page
                ->where('refunds.meta.total', 1)
            );
    });

    it('search string kosong → skip filter, return semua refund', function () {
        makeRefund($this->mitra, $this->client);
        makeRefund($this->mitra, $this->client);

        $totalRefund = Refund::count();

        $this->actingAs($this->admin)
            ->get('/admin/refunds?search=')
            ->assertInertia(fn (Assert $page) => $page
                ->where('refunds.meta.total', $totalRefund)
            );
    });
});

describe('Data Integrity — pagination', function () {
    it('per_page=5 → 5 item per halaman', function () {
        for ($i = 0; $i < 10; $i++) {
            makeRefund($this->mitra, $this->client);
        }

        $this->actingAs($this->admin)
            ->get('/admin/refunds?per_page=5')
            ->assertInertia(fn (Assert $page) => $page
                ->where('refunds.meta.per_page', 5)
                ->has('refunds.data', 5)
            );
    });

    it('per_page=100 (boundary max) → lolos', function () {
        makeRefund($this->mitra, $this->client);

        $this->actingAs($this->admin)
            ->get('/admin/refunds?per_page=100')
            ->assertInertia(fn (Assert $page) => $page
                ->where('refunds.meta.per_page', 100)
            );
    });

    it('page=2 → data halaman 2', function () {
        for ($i = 0; $i < 10; $i++) {
            makeRefund($this->mitra, $this->client);
        }

        $this->actingAs($this->admin)
            ->get('/admin/refunds?per_page=5&page=2')
            ->assertInertia(fn (Assert $page) => $page
                ->where('refunds.meta.current_page', 2)
                ->has('refunds.data', 5)
            );
    });
});

/*
|==========================================================================
| KATEGORI 6 — ERROR HANDLING (FormRequest Validation)
|==========================================================================
*/

describe('Error Handling — validation', function () {
    it('status=ngawur → validation error', function () {
        $this->actingAs($this->admin)
            ->get('/admin/refunds?status=ngawur')
            ->assertSessionHasErrors('status');
    });

    it('per_page < 5 → validation error', function () {
        $this->actingAs($this->admin)
            ->get('/admin/refunds?per_page=4')
            ->assertSessionHasErrors('per_page');
    });

    it('per_page > 100 → validation error', function () {
        $this->actingAs($this->admin)
            ->get('/admin/refunds?per_page=101')
            ->assertSessionHasErrors('per_page');
    });

    it('per_page bukan integer → validation error', function () {
        $this->actingAs($this->admin)
            ->get('/admin/refunds?per_page=abc')
            ->assertSessionHasErrors('per_page');
    });

    it('search > 255 karakter → validation error', function () {
        $long = str_repeat('a', 256);

        $this->actingAs($this->admin)
            ->get('/admin/refunds?search='.$long)
            ->assertSessionHasErrors('search');
    });

    it('page < 1 → validation error', function () {
        $this->actingAs($this->admin)
            ->get('/admin/refunds?page=0')
            ->assertSessionHasErrors('page');
    });
});

/*
|==========================================================================
| KATEGORI 7 — EDGE CASES & BVA
|==========================================================================
*/

describe('Edge Cases — empty DB', function () {
    it('DB tanpa refund → total 0, data kosong', function () {
        $this->actingAs($this->admin)
            ->get('/admin/refunds')
            ->assertInertia(fn (Assert $page) => $page
                ->where('refunds.meta.total', 0)
                ->has('refunds.data', 0)
            );
    });
});

describe('Edge Cases — BVA per_page', function () {
    it('per_page=5 (min boundary) → lolos', function () {
        makeRefund($this->mitra, $this->client);

        $this->actingAs($this->admin)
            ->get('/admin/refunds?per_page=5')
            ->assertOk()
            ->assertSessionHasNoErrors();
    });

    it('per_page=4 (min - 1) → error', function () {
        $this->actingAs($this->admin)
            ->get('/admin/refunds?per_page=4')
            ->assertSessionHasErrors('per_page');
    });

    it('per_page=100 (max boundary) → lolos', function () {
        $this->actingAs($this->admin)
            ->get('/admin/refunds?per_page=100')
            ->assertOk()
            ->assertSessionHasNoErrors();
    });

    it('per_page=101 (max + 1) → error', function () {
        $this->actingAs($this->admin)
            ->get('/admin/refunds?per_page=101')
            ->assertSessionHasErrors('per_page');
    });
});

describe('Edge Cases — SQL wildcard injection', function () {
    it('input % TIDAK berfungsi sebagai wildcard (literal match)', function () {
        $mitra = User::factory()->mitra()->create([
            'name' => 'Promo 100% Hemat',
            'must_change_password' => false,
        ]);
        makeRefund($mitra, $this->client);

        $mitra2 = User::factory()->mitra()->create([
            'name' => 'Promo 100 Ribu',
            'must_change_password' => false,
        ]);
        makeRefund($mitra2, $this->client);

        $this->actingAs($this->admin)
            ->get('/admin/refunds?search='.urlencode('100%'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('refunds.meta.total', 1)
            );
    });

    it('SQL injection classik aman (parameter binding)', function () {
        makeRefund($this->mitra, $this->client);

        $this->actingAs($this->admin)
            ->get('/admin/refunds?search='.urlencode("' OR 1=1--"))
            ->assertInertia(fn (Assert $page) => $page
                ->where('refunds.meta.total', 0)
            );
    });
});

describe('Edge Cases — ordering', function () {
    it('default order created_at DESC (terbaru dulu)', function () {
        Refund::where('id', '>', 0)->delete();

        $older = makeRefund($this->mitra, $this->client, [
            'created_at' => now()->subDays(2),
        ]);
        $newer = makeRefund($this->mitra, $this->client, [
            'created_at' => now()->subDay(),
        ]);

        $this->actingAs($this->admin)
            ->get('/admin/refunds?per_page=5')
            ->assertInertia(fn (Assert $page) => $page
                ->where('refunds.data.0.id', $newer->id)
                ->where('refunds.data.1.id', $older->id)
            );
    });
});

/*
|==========================================================================
| HELPER — buat refund lengkap dengan booking
|==========================================================================
*/
function makeRefund(User $mitra, User $client, array $overrides = []): Refund
{
    $booking = isset($overrides['booking_id'])
        ? Booking::find($overrides['booking_id'])
        : Booking::factory()
            ->forClient($client)
            ->create(['mitra_id' => $mitra->id]);

    return Refund::factory()->create(array_merge([
        'booking_id' => $booking->id,
        'mitra_id' => $mitra->id,
        'status' => RefundStatus::PENDING->value,
    ], $overrides));
}
