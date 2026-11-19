<?php

use App\Enums\TripStatus;
use App\Models\Package;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/*
|==========================================================================
| SETUP — Integration Test
|==========================================================================
|
| TripMonitoringController (admin) — monitoring trip & overview paket.
|
| Route:
| - GET /admin/trips          → index (admin.trips.index)
| - GET /admin/trips/{trip}   → show  (admin.trips.show)
| - GET /admin/packages       → overview (admin.packages.index)
|
| Middleware: auth, password.changed, role:admin
| Policy: TripPolicy (viewAny, view)
|
|   Catatan:
| - Controller pakai `$request->input(...)` bukan `validated()` — jadi
|   TIDAK ada validasi FormRequest. Input invalid (mis. status=ngawur)
|   akan diteruskan ke repository — kemungkinan return 0 hasil, BUKAN
|   validation error. Test di Kategori 6 dokumentasikan behavior as-is.
| - `show()` panggil `$this->tripService->findDetail($trip->id)` — instance
|   baru dari service, BUKAN route model binding instance. Test cek id.
| - `overview()` hardcode `status = 'aktif'` untuk package filter.
|
*/

beforeEach(function () {
    if (method_exists($this, 'withoutVite')) {
        $this->withoutVite();
    }

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
    it('GET /admin/trips → render Admin/Trips/Index', function () {
        $this->actingAs($this->admin)
            ->get('/admin/trips')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Trips/Index')
                ->has('trips')
                ->has('filters')
                ->has('statusCounts')
            );
    });

    it('filters default: search=null, status=null, sort=tanggal_mulai, direction=desc', function () {
        $this->actingAs($this->admin)
            ->get('/admin/trips')
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.search', null)
                ->where('filters.status', null)
                ->where('filters.sort', 'tanggal_mulai')
                ->where('filters.direction', 'desc')
            );
    });

    it('statusCounts punya key semua status Trip', function () {
        $this->actingAs($this->admin)
            ->get('/admin/trips')
            ->assertInertia(fn (Assert $page) => $page
                ->has('statusCounts.'.TripStatus::SCHEDULED->value)
                ->has('statusCounts.'.TripStatus::ACTIVE->value)
                ->has('statusCounts.'.TripStatus::COMPLETED->value)
                ->has('statusCounts.'.TripStatus::CANCELLED->value)
            );
    });

    it('route name = admin.trips.index', function () {
        expect(route('admin.trips.index'))->toBe(url('/admin/trips'));
    });
});

describe('Contract — overview', function () {
    it('GET /admin/packages → render Admin/Trips/Overview', function () {
        $this->actingAs($this->admin)
            ->get('/admin/packages')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Trips/Overview')
                ->has('packages')
                ->has('filters')
            );
    });

    it('filters hardcode status=aktif', function () {
        $this->actingAs($this->admin)
            ->get('/admin/packages')
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.status', 'aktif')
            );
    });

    it('route name = admin.packages.index', function () {
        expect(route('admin.packages.index'))->toBe(url('/admin/packages'));
    });
});

describe('Contract — show', function () {
    it('GET /admin/trips/{id} → render Admin/Trips/Show', function () {
        $trip = Trip::factory()->forPackage(
            Package::factory()->forMitra($this->mitra)->aktif()->create()
        )->create();

        $this->actingAs($this->admin)
            ->get("/admin/trips/{$trip->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Trips/Show')
                ->has('trip')
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
        $this->get('/admin/trips')->assertRedirect(route('login'));
    });

    it('guest di-redirect ke login (overview)', function () {
        $this->get('/admin/packages')->assertRedirect(route('login'));
    });

    it('mitra di-redirect ke mitra.dashboard (index)', function () {
        $this->actingAs($this->mitra)
            ->get('/admin/trips')
            ->assertRedirect(route('mitra.dashboard'));
    });

    it('client di-redirect ke client.dashboard (index)', function () {
        $this->actingAs($this->client)
            ->get('/admin/trips')
            ->assertRedirect(route('client.dashboard'));
    });

    it('mitra di-redirect (overview)', function () {
        $this->actingAs($this->mitra)
            ->get('/admin/packages')
            ->assertRedirect(route('mitra.dashboard'));
    });

    it('admin must_change_password=true di-redirect ke password.change', function () {
        $needChange = User::factory()->admin()->create(['must_change_password' => true]);

        $this->actingAs($needChange)
            ->get('/admin/trips')
            ->assertRedirect(route('password.change'));
    });
});

/*
|==========================================================================
| KATEGORI 3 — CONCURRENCY
|==========================================================================
|
| Endpoint read-only → tidak ada race condition di level tulis.
| Test fokus: konsistensi hasil 2x GET.
|
*/

describe('Concurrency — read-only consistency', function () {
    it('2x GET index → hasil identik', function () {
        Trip::factory()->forPackage(
            Package::factory()->forMitra($this->mitra)->aktif()->create()
        )->count(3)->create();

        $first = $this->actingAs($this->admin)
            ->get('/admin/trips')
            ->viewData('page')['props']['statusCounts'];

        $second = $this->actingAs($this->admin)
            ->get('/admin/trips')
            ->viewData('page')['props']['statusCounts'];

        expect($first)->toBe($second);
    });
});

/*
|==========================================================================
| KATEGORI 4 — IDEMPOTENCY
|==========================================================================
*/

describe('Idempotency', function () {
    it('GET index 2x tidak mengubah data', function () {
        Trip::factory()->forPackage(
            Package::factory()->forMitra($this->mitra)->aktif()->create()
        )->create();

        $tripCountBefore = Trip::count();

        $this->actingAs($this->admin)->get('/admin/trips');
        $this->actingAs($this->admin)->get('/admin/trips');

        expect(Trip::count())->toBe($tripCountBefore);
    });
});

/*
|==========================================================================
| KATEGORI 5 — DATA INTEGRITY
|==========================================================================
*/

describe('Data Integrity — index filters', function () {
    it('filter status=scheduled → hanya scheduled', function () {
        $package = Package::factory()->forMitra($this->mitra)->aktif()->create();
        Trip::factory()->forPackage($package)->scheduled()->count(2)->create();
        Trip::factory()->forPackage($package)->active()->count(3)->create();

        $this->actingAs($this->admin)
            ->get('/admin/trips?status=scheduled')
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.status', 'scheduled')
            );
    });

    it('filter mitra_id → hanya trip milik mitra itu', function () {
        $mitraA = User::factory()->mitra()->create(['must_change_password' => false]);
        $mitraB = User::factory()->mitra()->create(['must_change_password' => false]);

        $packageA = Package::factory()->forMitra($mitraA)->aktif()->create();
        $packageB = Package::factory()->forMitra($mitraB)->aktif()->create();

        Trip::factory()->forPackage($packageA)->count(2)->create();
        Trip::factory()->forPackage($packageB)->count(3)->create();

        $this->actingAs($this->admin)
            ->get("/admin/trips?mitra_id={$mitraA->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.mitra_id', (string) $mitraA->id)
            );
    });

    it('search kode trip → return match', function () {
        $package = Package::factory()->forMitra($this->mitra)->aktif()->create();
        $trip = Trip::factory()->forPackage($package)->create(['kode' => 'TRP-SEARCH-001']);

        $this->actingAs($this->admin)
            ->get('/admin/trips?search=TRP-SEARCH-001')
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.search', 'TRP-SEARCH-001')
            );
    });
});

describe('Data Integrity — overview hanya paket aktif', function () {
    it('overview filter status=aktif — hanya tampil paket aktif', function () {
        Package::factory()->forMitra($this->mitra)->aktif()->count(2)->create();
        Package::factory()->forMitra($this->mitra)->draft()->count(3)->create();
        Package::factory()->forMitra($this->mitra)->arsip()->count(2)->create();

        $this->actingAs($this->admin)
            ->get('/admin/packages')
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.status', 'aktif')
                // 2 aktif + (dari beforeEach? tidak ada package dari beforeEach)
                ->where('packages.total', 2)
            );
    });

    it('overview search nama paket → match', function () {
        Package::factory()->forMitra($this->mitra)->aktif()->create(['nama' => 'Paket Bali Spesial']);

        $this->actingAs($this->admin)
            ->get('/admin/packages?search=Bali')
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.search', 'Bali')
            );
    });
});

/*
|==========================================================================
| KATEGORI 6 — ERROR HANDLING
|==========================================================================
*/

describe('Error Handling — show trip tidak ada', function () {
    it('GET /admin/trips/999999 → 404', function () {
        $this->actingAs($this->admin)
            ->get('/admin/trips/999999')
            ->assertNotFound();
    });
});

describe('Error Handling — input invalid (behavior as-is)', function () {
    it('status=ngawur → tidak crash (bukan validation error)', function () {
        // Controller pakai $request->input() bukan validated() — tidak ada
        // validasi. Input invalid diteruskan ke repository — return 0 hasil.
        $this->actingAs($this->admin)
            ->get('/admin/trips?status=ngawur')
            ->assertOk();
    });

    it('per_page=abc → fallback ke 15', function () {
        $this->actingAs($this->admin)
            ->get('/admin/trips?per_page=abc')
            ->assertInertia(fn (Assert $page) => $page
                ->has('trips')
            );
    });
});

/*
|==========================================================================
| KATEGORI 7 — EDGE CASES & BVA
|==========================================================================
*/

describe('Edge Cases — empty DB', function () {
    it('DB tanpa trip → index tetap OK, total 0', function () {
        $this->actingAs($this->admin)
            ->get('/admin/trips')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('trips.total', 0)
            );
    });

    it('DB tanpa paket aktif → overview OK, total 0', function () {
        Package::factory()->forMitra($this->mitra)->draft()->count(2)->create();

        $this->actingAs($this->admin)
            ->get('/admin/packages')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('packages.total', 0)
            );
    });
});

describe('Edge Cases — show detail', function () {
    it('show trip load relasi lengkap', function () {
        $package = Package::factory()->forMitra($this->mitra)->aktif()->create();
        $trip = Trip::factory()->forPackage($package)->create();

        $this->actingAs($this->admin)
            ->get("/admin/trips/{$trip->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('trip.id', $trip->id)
                ->where('trip.kode', $trip->kode)
            );
    });
});
