<?php

use App\Enums\PackageKategori;
use App\Enums\PackageStatus;
use App\Models\Booking;
use App\Models\Package;
use App\Models\PackageDay;
use App\Models\PackageFacility;
use App\Models\Profile;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/*
|==========================================================================
| SETUP — Integration Test
|==========================================================================
|
| CatalogController (client) — katalog paket aktif + detail.
|
| Route (prefix /client):
| - GET /catalog                  → index
| - GET /catalog/{package:slug}   → show
|
| Middleware: auth, password.changed, role:client
| Policy: TIDAK ADA (public catalog untuk client)
|
| Catatan:
| - `config(['inertia.testing.ensure_pages_exist' => false])` — backend test.
| - Controller TIDAK pakai `$this->authorize(...)` — akses bebas untuk client.
| - `show` guard: `abort_unless($package->isAktif(), 404)`.
| - Route pakai `{package:slug}` — binding by slug, bukan ID.
|
*/

beforeEach(function () {
    if (method_exists($this, 'withoutVite')) {
        $this->withoutVite();
    }

    config(['inertia.testing.ensure_pages_exist' => false]);

    $this->client = User::factory()->client()->create([
        'must_change_password' => false,
    ]);

    $this->mitra = User::factory()->mitra()->create([
        'name' => 'Mitra Travel',
        'must_change_password' => false,
    ]);

    // Profile mitra (untuk show)
    Profile::factory()->create([
        'user_id' => $this->mitra->id,
        'tipe' => 'mitra',
        'nama_penyedia_usaha' => 'PT Travel Jaya',
        'telepon' => '08123456789',
        'alamat' => 'Jl. Test',
    ]);
});

/*
|==========================================================================
| HELPER — buat paket
|==========================================================================
*/
function makePackageForCatalog(User $mitra, array $overrides = []): Package
{
    return Package::factory()->forMitra($mitra)->create(array_merge([
        'status' => PackageStatus::AKTIF->value,
        'nama' => 'Paket Test '.uniqid(),
    ], $overrides));
}

/*
|==========================================================================
| KATEGORI 1 — CONTRACT / API SCHEMA
|==========================================================================
*/

describe('Contract — index', function () {
    it('GET /client/catalog → render Client/Catalog/Index', function () {
        $this->actingAs($this->client)
            ->get('/client/catalog')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Client/Catalog/Index')
                ->has('packages')
                ->has('filters')
            );
    });

    it('filters default: search=null, status=aktif, kategori=null, sort=nama, direction=asc', function () {
        $this->actingAs($this->client)
            ->get('/client/catalog')
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.search', null)
                ->where('filters.status', 'aktif')
                ->where('filters.kategori', null)
                ->where('filters.sort', 'nama')
                ->where('filters.direction', 'asc')
            );
    });

    it('route name = client.catalog.index', function () {
        expect(route('client.catalog.index'))->toBe(url('/client/catalog'));
    });
});

describe('Contract — show', function () {
    it('GET /client/catalog/{slug} → render Client/Catalog/Show', function () {
        $package = makePackageForCatalog($this->mitra);

        $this->actingAs($this->client)
            ->get("/client/catalog/{$package->slug}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Client/Catalog/Show')
                ->has('package')
                ->where('package.id', $package->id)
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
        $this->get('/client/catalog')->assertRedirect(route('login'));
    });

    it('guest di-redirect ke login (show)', function () {
        $package = makePackageForCatalog($this->mitra);

        $this->get("/client/catalog/{$package->slug}")
            ->assertRedirect(route('login'));
    });

    it('admin di-redirect ke admin.dashboard (index)', function () {
        $admin = User::factory()->admin()->create(['must_change_password' => false]);

        $this->actingAs($admin)
            ->get('/client/catalog')
            ->assertRedirect(route('admin.dashboard'));
    });

    it('mitra di-redirect ke mitra.dashboard (index)', function () {
        $this->actingAs($this->mitra)
            ->get('/client/catalog')
            ->assertRedirect(route('mitra.dashboard'));
    });

    it('client must_change_password=true di-redirect ke password.change', function () {
        $needChange = User::factory()->client()->create(['must_change_password' => true]);

        $this->actingAs($needChange)
            ->get('/client/catalog')
            ->assertRedirect(route('password.change'));
    });
});

describe('Security — show guard (hanya paket aktif)', function () {
    it('show paket DRAFT → 404', function () {
        $package = makePackageForCatalog($this->mitra, [
            'status' => PackageStatus::DRAFT->value,
        ]);

        $this->actingAs($this->client)
            ->get("/client/catalog/{$package->slug}")
            ->assertNotFound();
    });

    it('show paket ARSIP → 404', function () {
        $package = makePackageForCatalog($this->mitra, [
            'status' => PackageStatus::ARSIP->value,
        ]);

        $this->actingAs($this->client)
            ->get("/client/catalog/{$package->slug}")
            ->assertNotFound();
    });

    it('show paket AKTIF → OK', function () {
        $package = makePackageForCatalog($this->mitra, [
            'status' => PackageStatus::AKTIF->value,
        ]);

        $this->actingAs($this->client)
            ->get("/client/catalog/{$package->slug}")
            ->assertOk();
    });
});

/*
|==========================================================================
| KATEGORI 3 — CONCURRENCY
|==========================================================================
*/

describe('Concurrency — read-only consistency', function () {
    it('2x GET index → total identik', function () {
        makePackageForCatalog($this->mitra);
        makePackageForCatalog($this->mitra);

        $first = $this->actingAs($this->client)
            ->get('/client/catalog')
            ->viewData('page')['props']['packages']['total'];

        $second = $this->actingAs($this->client)
            ->get('/client/catalog')
            ->viewData('page')['props']['packages']['total'];

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
        makePackageForCatalog($this->mitra);
        $countBefore = Package::count();

        $this->actingAs($this->client)->get('/client/catalog');
        $this->actingAs($this->client)->get('/client/catalog');

        expect(Package::count())->toBe($countBefore);
    });
});

/*
|==========================================================================
| KATEGORI 5 — DATA INTEGRITY
|==========================================================================
*/

describe('Data Integrity — index filters', function () {
    it('hanya menampilkan paket aktif', function () {
        makePackageForCatalog($this->mitra, ['nama' => 'Paket Aktif']);
        makePackageForCatalog($this->mitra, [
            'nama' => 'Paket Draft',
            'status' => PackageStatus::DRAFT->value,
        ]);
        makePackageForCatalog($this->mitra, [
            'nama' => 'Paket Arsip',
            'status' => PackageStatus::ARSIP->value,
        ]);

        $this->actingAs($this->client)
            ->get('/client/catalog')
            ->assertInertia(fn (Assert $page) => $page
                ->where('packages.total', 1)
                ->where('packages.data.0.nama', 'Paket Aktif')
            );
    });

    it('search by nama → return match', function () {
        makePackageForCatalog($this->mitra, ['nama' => 'Paket Bali Spesial']);
        makePackageForCatalog($this->mitra, ['nama' => 'Paket Lombok']);

        $this->actingAs($this->client)
            ->get('/client/catalog?search=Bali')
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.search', 'Bali')
            );
    });

    it('filter kategori=wisata_alam → hanya wisata_alam', function () {
        makePackageForCatalog($this->mitra, [
            'kategori' => PackageKategori::WISATA_ALAM->value,
        ]);
        makePackageForCatalog($this->mitra, [
            'kategori' => PackageKategori::BUDAYA->value,
        ]);

        $this->actingAs($this->client)
            ->get('/client/catalog?kategori=wisata_alam')
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.kategori', 'wisata_alam')
            );
    });
});

describe('Data Integrity — show (relasi)', function () {
    it('show load mitra + profile + days + facilities', function () {
        $package = makePackageForCatalog($this->mitra);
        PackageDay::factory()->create(['package_id' => $package->id, 'hari_ke' => 1]);
        PackageFacility::factory()->create(['package_id' => $package->id, 'tipe' => 'include']);

        $this->actingAs($this->client)
            ->get("/client/catalog/{$package->slug}")
            ->assertInertia(fn (Assert $page) => $page
                ->has('package.mitra')
                ->has('package.days')
                ->has('package.facilities')
                ->where('package.mitra.name', 'Mitra Travel')
            );
    });
});

/*
|==========================================================================
| KATEGORI 6 — ERROR HANDLING
|==========================================================================
*/

describe('Error Handling — show 404', function () {
    it('GET /client/catalog/{slug-tidak-ada} → 404', function () {
        $this->actingAs($this->client)
            ->get('/client/catalog/slug-tidak-ada')
            ->assertNotFound();
    });
});

/*
|==========================================================================
| KATEGORI 7 — EDGE CASES & BVA
|==========================================================================
*/

describe('Edge Cases — empty DB', function () {
    it('DB tanpa paket aktif → total 0', function () {
        $this->actingAs($this->client)
            ->get('/client/catalog')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('packages.total', 0)
            );
    });
});

describe('Edge Cases — per_page default 12', function () {
    it('default per_page = 12', function () {
        for ($i = 0; $i < 15; $i++) {
            makePackageForCatalog($this->mitra);
        }

        $this->actingAs($this->client)
            ->get('/client/catalog')
            ->assertInertia(fn (Assert $page) => $page
                ->where('packages.per_page', 12)
                ->has('packages.data', 12)
            );
    });
});

/*
|==========================================================================
| REVIEW — RINGKASAN & DAFTAR ULASAN
|==========================================================================
*/

describe('Review — katalog', function () {
    it('prop reviewStats dan reviews ada di Client/Catalog/Show', function () {
        $package = makePackageForCatalog($this->mitra);

        $this->actingAs($this->client)
            ->get("/client/catalog/{$package->slug}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Client/Catalog/Show')
                ->has('reviewStats')
                ->has('reviews')
                ->where('reviewStats.rata_rata', 0)
                ->where('reviewStats.jumlah', 0)
            );
    });

    it('rating agregat sesuai review yang dibuat factory', function () {
        $package = makePackageForCatalog($this->mitra);
        $booking1 = Booking::factory()->forPackage($package)->forClient($this->client)->confirmed()->create();
        $booking2 = Booking::factory()->forPackage($package)->forClient($this->client)->confirmed()->create();

        Review::factory()->forBooking($booking1)->rating(5)->create();
        Review::factory()->forBooking($booking2)->rating(4)->create();

        $this->actingAs($this->client)
            ->get("/client/catalog/{$package->slug}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('reviewStats.rata_rata', 4.5)
                ->where('reviewStats.jumlah', 2)
                ->has('reviews.data', 2)
                ->where('reviews.data.0.rating', 4)
                ->where('reviews.data.1.rating', 5)
            );
    });

    it('nama reviewer hanya nama depan (AC-10)', function () {
        $package = makePackageForCatalog($this->mitra);
        $reviewer = User::factory()->client()->create(['name' => 'Budi Santoso']);
        $booking = Booking::factory()->forPackage($package)->forClient($reviewer)->confirmed()->create();
        Review::factory()->forBooking($booking)->rating(5)->create();

        $this->actingAs($this->client)
            ->get("/client/catalog/{$package->slug}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('reviews.data.0.client.nama', 'Budi')
            );
    });
});
