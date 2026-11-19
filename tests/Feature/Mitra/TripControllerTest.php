<?php

use App\Enums\TripStatus;
use App\Models\Booking;
use App\Models\MitraBankAccount;
use App\Models\Package;
use App\Models\Trip;
use App\Models\User;
use App\Services\Report\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response as IlluminateResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery\MockInterface;

uses(RefreshDatabase::class);

/*
|==========================================================================
| SETUP — Integration Test
|==========================================================================
|
| TripController (mitra) — read-only monitoring trip + manifest PDF.
|
| Route (prefix /mitra):
| - GET /trips                  → index
| - GET /trips/{trip}           → show
| - GET /trips/{trip}/manifest  → manifest (PDF)
|
| Middleware: auth, password.changed, role:mitra, mitra.complete
| Policy: TripPolicy (viewAny, view)
|
| Catatan:
| - `config(['inertia.testing.ensure_pages_exist' => false])` — backend test.
| - `manifest` delegate ke `Admin\ReportController::exportTripManifest`
|   → mock concrete `ReportService` (tidak final, tanpa interface).
| - Mitra WAJIB punya bank account (middleware `mitra.complete`).
| - Test fokus CONTROLLER (policy, response shape, delegation).
|   `TripService` sudah di-test unit (TripServiceTest).
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
| HELPER — buat trip lengkap dengan booking & package
|==========================================================================
*/
function makeTripForMitra(User $mitra, User $client, array $overrides = []): Trip
{
    $package = Package::factory()->forMitra($mitra)->aktif()->create();
    $booking = Booking::factory()
        ->forPackage($package)
        ->forClient($client)
        ->confirmed()
        ->create(['mitra_id' => $mitra->id]);

    return Trip::factory()
        ->forBooking($booking)
        ->create(array_merge([
            'mitra_id' => $mitra->id,
            'status' => TripStatus::SCHEDULED->value,
        ], $overrides));
}

/*
|==========================================================================
| KATEGORI 1 — CONTRACT / API SCHEMA
|==========================================================================
*/

describe('Contract — index', function () {
    it('GET /mitra/trips → render Mitra/Trips/Index', function () {
        $this->actingAs($this->mitra)
            ->get('/mitra/trips')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Mitra/Trips/Index')
                ->has('trips')
                ->has('filters')
                ->has('statusCounts')
            );
    });

    it('filters default: search=null, status=null, sort=tanggal_mulai, direction=desc', function () {
        $this->actingAs($this->mitra)
            ->get('/mitra/trips')
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.search', null)
                ->where('filters.status', null)
                ->where('filters.sort', 'tanggal_mulai')
                ->where('filters.direction', 'desc')
            );
    });

    it('hanya menampilkan trip milik mitra sendiri', function () {
        makeTripForMitra($this->mitra, $this->client);
        makeTripForMitra($this->mitra2, $this->client);

        $this->actingAs($this->mitra)
            ->get('/mitra/trips')
            ->assertInertia(fn (Assert $page) => $page
                ->where('trips.total', 1)
            );
    });

    it('route name = mitra.trips.index', function () {
        expect(route('mitra.trips.index'))->toBe(url('/mitra/trips'));
    });
});

describe('Contract — show', function () {
    it('GET /mitra/trips/{id} → render Mitra/Trips/Show', function () {
        $trip = makeTripForMitra($this->mitra, $this->client);

        $this->actingAs($this->mitra)
            ->get("/mitra/trips/{$trip->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Mitra/Trips/Show')
                ->has('trip')
                ->where('trip.id', $trip->id)
            );
    });
});

/*
|==========================================================================
| KATEGORI 2 — SECURITY & AUTHORIZATION
|==========================================================================
*/

describe('Security — guest & role', function () {
    it('guest di-redirect ke login (index)', function () {
        $this->get('/mitra/trips')->assertRedirect(route('login'));
    });

    it('admin di-redirect ke admin.dashboard (index)', function () {
        $admin = User::factory()->admin()->create(['must_change_password' => false]);

        $this->actingAs($admin)
            ->get('/mitra/trips')
            ->assertRedirect(route('admin.dashboard'));
    });

    it('client di-redirect ke client.dashboard (index)', function () {
        $this->actingAs($this->client)
            ->get('/mitra/trips')
            ->assertRedirect(route('client.dashboard'));
    });

    it('mitra must_change_password=true di-redirect ke password.change', function () {
        $needChange = User::factory()->mitra()->create(['must_change_password' => true]);

        $this->actingAs($needChange)
            ->get('/mitra/trips')
            ->assertRedirect(route('password.change'));
    });
});

describe('Security — ownership (Policy view)', function () {
    it('mitra TIDAK bisa show trip mitra lain → 403', function () {
        $trip = makeTripForMitra($this->mitra2, $this->client);

        $this->actingAs($this->mitra)
            ->get("/mitra/trips/{$trip->id}")
            ->assertForbidden();
    });

    it('mitra TIDAK bisa akses manifest trip mitra lain → 403', function () {
        $trip = makeTripForMitra($this->mitra2, $this->client);

        $this->actingAs($this->mitra)
            ->get("/mitra/trips/{$trip->id}/manifest")
            ->assertForbidden();
    });
});

/*
|==========================================================================
| KATEGORI 3 — CONCURRENCY
|==========================================================================
*/

describe('Concurrency — read-only consistency', function () {
    it('2x GET index → total identik', function () {
        makeTripForMitra($this->mitra, $this->client);

        $first = $this->actingAs($this->mitra)
            ->get('/mitra/trips')
            ->viewData('page')['props']['trips']['total'];

        $second = $this->actingAs($this->mitra)
            ->get('/mitra/trips')
            ->viewData('page')['props']['trips']['total'];

        expect($first)->toBe($second);
    });
});

/*
|==========================================================================
| KATEGORI 4 — IDEMPOTENCY
|==========================================================================
*/

describe('Idempotency', function () {
    it('GET index berulang tidak mengubah data', function () {
        makeTripForMitra($this->mitra, $this->client);
        $countBefore = Trip::count();

        $this->actingAs($this->mitra)->get('/mitra/trips');
        $this->actingAs($this->mitra)->get('/mitra/trips');

        expect(Trip::count())->toBe($countBefore);
    });
});

/*
|==========================================================================
| KATEGORI 5 — DATA INTEGRITY
|==========================================================================
*/

describe('Data Integrity — index filters', function () {
    it('filter status=scheduled → hanya scheduled', function () {
        makeTripForMitra($this->mitra, $this->client, ['status' => TripStatus::SCHEDULED->value]);
        makeTripForMitra($this->mitra, $this->client, ['status' => TripStatus::ACTIVE->value]);

        $this->actingAs($this->mitra)
            ->get('/mitra/trips?status=scheduled')
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.status', 'scheduled')
            );
    });

    it('search kode trip → return match', function () {
        makeTripForMitra($this->mitra, $this->client, ['kode' => 'TRP-SEARCH-001']);

        $this->actingAs($this->mitra)
            ->get('/mitra/trips?search=TRP-SEARCH-001')
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.search', 'TRP-SEARCH-001')
            );
    });
});

describe('Data Integrity — manifest (delegate ke ReportService)', function () {
    it('manifest → ReportService::exportTripManifest dipanggil', function () {
        $trip = makeTripForMitra($this->mitra, $this->client);

        $response = new IlluminateResponse('pdf-content');

        // Mock concrete ReportService (tidak final, tanpa interface)
        $this->mock(ReportService::class, function (MockInterface $m) use ($response) {
            $m->shouldReceive('exportTripManifest')
                ->once()
                ->with(Mockery::type(Trip::class))
                ->andReturn($response);
        });

        $this->actingAs($this->mitra)
            ->get("/mitra/trips/{$trip->id}/manifest")
            ->assertOk();
    });
});

/*
|==========================================================================
| KATEGORI 6 — ERROR HANDLING
|==========================================================================
*/

describe('Error Handling — show 404', function () {
    it('GET /mitra/trips/999999 → 404', function () {
        $this->actingAs($this->mitra)
            ->get('/mitra/trips/999999')
            ->assertNotFound();
    });
});

/*
|==========================================================================
| KATEGORI 7 — EDGE CASES & BVA
|==========================================================================
*/

describe('Edge Cases — empty DB', function () {
    it('DB tanpa trip → index OK, total 0', function () {
        $this->actingAs($this->mitra)
            ->get('/mitra/trips')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('trips.total', 0)
            );
    });
});

describe('Edge Cases — per_page', function () {
    it('per_page=10 (default) → 10 item per halaman', function () {
        for ($i = 0; $i < 15; $i++) {
            makeTripForMitra($this->mitra, $this->client);
        }

        $this->actingAs($this->mitra)
            ->get('/mitra/trips')
            ->assertInertia(fn (Assert $page) => $page
                ->where('trips.per_page', 10)
                ->has('trips.data', 10)
            );
    });

    it('per_page=5 → 5 item per halaman', function () {
        for ($i = 0; $i < 10; $i++) {
            makeTripForMitra($this->mitra, $this->client);
        }

        $this->actingAs($this->mitra)
            ->get('/mitra/trips?per_page=5')
            ->assertInertia(fn (Assert $page) => $page
                ->where('trips.per_page', 5)
                ->has('trips.data', 5)
            );
    });
});
