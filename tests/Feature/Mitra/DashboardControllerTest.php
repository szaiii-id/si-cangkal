<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\MitraBankAccount;
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
| DashboardController (mitra) — read-only statistik & recent bookings.
|
| Route: GET /mitra/dashboard (mitra.dashboard)
| Middleware: auth, password.changed, role:mitra, mitra.complete
|
| Statistik:
| - packages (draft, aktif, arsip, total)
| - trips (scheduled, active, completed, total)
| - bookings (total, pending, confirmed)
| - total_revenue (SUM confirmed + completed)
| - recent_bookings (5 terbaru)
|
|   Catatan:
| - `config(['inertia.testing.ensure_pages_exist' => false])` — backend test.
| - Semua query di-scope ke `mitra_id` (current user).
| - Mitra WAJIB punya bank account (middleware `mitra.complete`).
| - Test fokus CONTROLLER (statistik, scope, response shape).
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
| HELPER — buat booking untuk mitra
|==========================================================================
*/
function makeBookingForMitraDashboard(User $mitra, User $client, array $overrides = []): Booking
{
    $package = Package::factory()->forMitra($mitra)->aktif()->create();

    return Booking::factory()
        ->forPackage($package)
        ->forClient($client)
        ->create(array_merge([
            'mitra_id' => $mitra->id,
            'status' => BookingStatus::PENDING_PAYMENT,
            'total_harga' => 500_000,
        ], $overrides));
}

/*
|==========================================================================
| KATEGORI 1 — CONTRACT / API SCHEMA
|==========================================================================
*/

describe('Contract — index', function () {
    it('GET /mitra/dashboard → render Mitra/Dashboard', function () {
        $this->actingAs($this->mitra)
            ->get('/mitra/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Mitra/Dashboard')
                ->has('stats')
                ->has('recent_bookings')
            );
    });

    it('stats punya keys packages, trips, bookings, total_revenue', function () {
        $this->actingAs($this->mitra)
            ->get('/mitra/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->has('stats.packages')
                ->has('stats.trips')
                ->has('stats.bookings')
                ->has('stats.total_revenue')
            );
    });

    it('stats.packages punya keys draft, aktif, arsip, total', function () {
        $this->actingAs($this->mitra)
            ->get('/mitra/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->has('stats.packages.draft')
                ->has('stats.packages.aktif')
                ->has('stats.packages.arsip')
                ->has('stats.packages.total')
            );
    });

    it('stats.trips punya keys scheduled, active, completed, total', function () {
        $this->actingAs($this->mitra)
            ->get('/mitra/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->has('stats.trips.scheduled')
                ->has('stats.trips.active')
                ->has('stats.trips.completed')
                ->has('stats.trips.total')
            );
    });

    it('stats.bookings punya keys total, pending, confirmed', function () {
        $this->actingAs($this->mitra)
            ->get('/mitra/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->has('stats.bookings.total')
                ->has('stats.bookings.pending')
                ->has('stats.bookings.confirmed')
            );
    });

    it('recent_bookings adalah array', function () {
        $this->actingAs($this->mitra)
            ->get('/mitra/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->has('recent_bookings')
            );
    });

    it('route name = mitra.dashboard', function () {
        expect(route('mitra.dashboard'))->toBe(url('/mitra/dashboard'));
    });
});

/*
|==========================================================================
| KATEGORI 2 — SECURITY & AUTHORIZATION
|==========================================================================
*/

describe('Security — guest & role', function () {
    it('guest di-redirect ke login', function () {
        $this->get('/mitra/dashboard')->assertRedirect(route('login'));
    });

    it('admin di-redirect ke admin.dashboard', function () {
        $admin = User::factory()->admin()->create(['must_change_password' => false]);

        $this->actingAs($admin)
            ->get('/mitra/dashboard')
            ->assertRedirect(route('admin.dashboard'));
    });

    it('client di-redirect ke client.dashboard', function () {
        $this->actingAs($this->client)
            ->get('/mitra/dashboard')
            ->assertRedirect(route('client.dashboard'));
    });

    it('mitra must_change_password=true di-redirect ke password.change', function () {
        $needChange = User::factory()->mitra()->create(['must_change_password' => true]);

        $this->actingAs($needChange)
            ->get('/mitra/dashboard')
            ->assertRedirect(route('password.change'));
    });

    it('mitra tanpa bank account di-redirect ke onboarding', function () {
        $noAccount = User::factory()->mitra()->create(['must_change_password' => false]);

        $this->actingAs($noAccount)
            ->get('/mitra/dashboard')
            ->assertRedirect(route('mitra.onboarding.index'));
    });
});

/*
|==========================================================================
| KATEGORI 3 — CONCURRENCY
|==========================================================================
*/

describe('Concurrency — read-only consistency', function () {
    it('2x GET → stats identik', function () {
        $first = $this->actingAs($this->mitra)
            ->get('/mitra/dashboard')
            ->viewData('page')['props']['stats'];

        $second = $this->actingAs($this->mitra)
            ->get('/mitra/dashboard')
            ->viewData('page')['props']['stats'];

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
        makeBookingForMitraDashboard($this->mitra, $this->client);
        $countBefore = Booking::count();

        $this->actingAs($this->mitra)->get('/mitra/dashboard');
        $this->actingAs($this->mitra)->get('/mitra/dashboard');

        expect(Booking::count())->toBe($countBefore);
    });
});

/*
|==========================================================================
| KATEGORI 5 — DATA INTEGRITY (STATISTIK)
|==========================================================================
*/

describe('Data Integrity — stats.packages', function () {
    it('menghitung package per status untuk mitra sendiri', function () {
        Package::factory()->forMitra($this->mitra)->draft()->count(2)->create();
        Package::factory()->forMitra($this->mitra)->aktif()->count(3)->create();
        Package::factory()->forMitra($this->mitra)->arsip()->count(1)->create();

        // Package mitra lain (tidak dihitung)
        Package::factory()->forMitra($this->mitra2)->aktif()->count(5)->create();

        $this->actingAs($this->mitra)
            ->get('/mitra/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.packages.draft', 2)
                ->where('stats.packages.aktif', 3)
                ->where('stats.packages.arsip', 1)
                ->where('stats.packages.total', 6)
            );
    });

    it('DB kosong → stats.packages semua 0', function () {
        $this->actingAs($this->mitra)
            ->get('/mitra/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.packages.draft', 0)
                ->where('stats.packages.aktif', 0)
                ->where('stats.packages.arsip', 0)
                ->where('stats.packages.total', 0)
            );
    });
});

describe('Data Integrity — stats.trips', function () {
    it('menghitung trip per status untuk mitra sendiri', function () {
        $package = Package::factory()->forMitra($this->mitra)->aktif()->create();

        Trip::factory()->forPackage($package)->scheduled()->count(2)->create();
        Trip::factory()->forPackage($package)->active()->count(1)->create();
        Trip::factory()->forPackage($package)->completed()->count(3)->create();

        $this->actingAs($this->mitra)
            ->get('/mitra/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.trips.scheduled', 2)
                ->where('stats.trips.active', 1)
                ->where('stats.trips.completed', 3)
                ->where('stats.trips.total', 6)
            );
    });
});

describe('Data Integrity — stats.bookings', function () {
    it('menghitung booking per status untuk mitra sendiri', function () {
        makeBookingForMitraDashboard($this->mitra, $this->client, ['status' => BookingStatus::PENDING_PAYMENT]);
        makeBookingForMitraDashboard($this->mitra, $this->client, ['status' => BookingStatus::PENDING_PAYMENT]);
        makeBookingForMitraDashboard($this->mitra, $this->client, ['status' => BookingStatus::PAYMENT_VERIFIED]);
        makeBookingForMitraDashboard($this->mitra, $this->client, ['status' => BookingStatus::CONFIRMED]);

        $this->actingAs($this->mitra)
            ->get('/mitra/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.bookings.total', 4)
                ->where('stats.bookings.pending', 3)  // 2 pending_payment + 1 payment_verified
                ->where('stats.bookings.confirmed', 1)
            );
    });
});

describe('Data Integrity — total_revenue', function () {
    it('revenue = SUM(confirmed + completed)', function () {
        makeBookingForMitraDashboard($this->mitra, $this->client, [
            'status' => BookingStatus::CONFIRMED,
            'total_harga' => 1_000_000,
        ]);
        makeBookingForMitraDashboard($this->mitra, $this->client, [
            'status' => BookingStatus::COMPLETED,
            'total_harga' => 500_000,
        ]);

        // Tidak dihitung (pending)
        makeBookingForMitraDashboard($this->mitra, $this->client, [
            'status' => BookingStatus::PENDING_PAYMENT,
            'total_harga' => 2_000_000,
        ]);

        $this->actingAs($this->mitra)
            ->get('/mitra/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.total_revenue', 1_500_000)
            );
    });

    it('revenue 0 jika tidak ada confirmed/completed', function () {
        makeBookingForMitraDashboard($this->mitra, $this->client, [
            'status' => BookingStatus::PENDING_PAYMENT,
            'total_harga' => 1_000_000,
        ]);

        $this->actingAs($this->mitra)
            ->get('/mitra/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.total_revenue', 0)
            );
    });

    it('revenue mitra lain TIDAK dihitung', function () {
        makeBookingForMitraDashboard($this->mitra2, $this->client, [
            'status' => BookingStatus::CONFIRMED,
            'total_harga' => 5_000_000,
        ]);

        $this->actingAs($this->mitra)
            ->get('/mitra/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.total_revenue', 0)
            );
    });
});

describe('Data Integrity — recent_bookings', function () {
    it('maksimal 5 booking terbaru', function () {
        for ($i = 0; $i < 10; $i++) {
            makeBookingForMitraDashboard($this->mitra, $this->client);
        }

        $this->actingAs($this->mitra)
            ->get('/mitra/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->has('recent_bookings', 5)
            );
    });

    it('recent_bookings item punya shape: id, kode, client, paket, total, status, tanggal', function () {
        $client = User::factory()->client()->create([
            'name' => 'Budi Client',
            'must_change_password' => false,
        ]);
        $package = Package::factory()->forMitra($this->mitra)->aktif()->create(['nama' => 'Paket Test']);

        Booking::factory()
            ->forPackage($package)
            ->forClient($client)
            ->create([
                'mitra_id' => $this->mitra->id,
                'kode' => 'BKG-TEST-001',
                'status' => BookingStatus::CONFIRMED,
                'total_harga' => 500_000,
            ]);

        $this->actingAs($this->mitra)
            ->get('/mitra/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->has('recent_bookings.0.id')
                ->has('recent_bookings.0.kode')
                ->has('recent_bookings.0.client')
                ->has('recent_bookings.0.paket')
                ->has('recent_bookings.0.total')
                ->has('recent_bookings.0.status')
                ->has('recent_bookings.0.tanggal')
                ->where('recent_bookings.0.kode', 'BKG-TEST-001')
                ->where('recent_bookings.0.client', 'Budi Client')
                ->where('recent_bookings.0.paket', 'Paket Test')
            );
    });

    it('recent_bookings hanya milik mitra sendiri', function () {
        makeBookingForMitraDashboard($this->mitra, $this->client, ['kode' => 'BKG-SENDIRI']);
        makeBookingForMitraDashboard($this->mitra2, $this->client, ['kode' => 'BKG-MITRA2']);

        $this->actingAs($this->mitra)
            ->get('/mitra/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->where('recent_bookings.0.kode', 'BKG-SENDIRI')
            );
    });

    it('recent_bookings diurutkan created_at DESC', function () {
        $older = makeBookingForMitraDashboard($this->mitra, $this->client, [
            'kode' => 'BKG-OLDER',
            'created_at' => now()->subDays(2),
        ]);
        $newer = makeBookingForMitraDashboard($this->mitra, $this->client, [
            'kode' => 'BKG-NEWER',
            'created_at' => now()->subDay(),
        ]);

        $this->actingAs($this->mitra)
            ->get('/mitra/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->where('recent_bookings.0.kode', 'BKG-NEWER')
            );
    });
});

/*
|==========================================================================
| KATEGORI 6 — ERROR HANDLING
|==========================================================================
*/

describe('Error Handling — empty DB', function () {
    it('DB kosong → stats 0, recent_bookings kosong', function () {
        $this->actingAs($this->mitra)
            ->get('/mitra/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.packages.total', 0)
                ->where('stats.trips.total', 0)
                ->where('stats.bookings.total', 0)
                ->where('stats.total_revenue', 0)
                ->has('recent_bookings', 0)
            );
    });
});

/*
|==========================================================================
| KATEGORI 7 — EDGE CASES
|==========================================================================
*/

describe('Edge Cases — scope isolation', function () {
    it('mitra2 punya data, mitra1 tidak terpengaruh', function () {
        Package::factory()->forMitra($this->mitra2)->aktif()->count(5)->create();
        makeBookingForMitraDashboard($this->mitra2, $this->client, [
            'status' => BookingStatus::CONFIRMED,
            'total_harga' => 5_000_000,
        ]);

        $this->actingAs($this->mitra)
            ->get('/mitra/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.packages.total', 0)
                ->where('stats.bookings.total', 0)
                ->where('stats.total_revenue', 0)
            );
    });
});
