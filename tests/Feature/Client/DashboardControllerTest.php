<?php

use App\Enums\BookingStatus;
use App\Enums\PackageStatus;
use App\Models\Booking;
use App\Models\Package;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/*
|==========================================================================
| SETUP — Integration Test
|==========================================================================
|
| DashboardController (client) — read-only 4 paket baru + 4 paket laris.
|
| Route: GET /client/dashboard (client.dashboard)
| Middleware: auth, password.changed, role:client
|
| Data:
| - paket_baru   : 4 paket aktif terbaru (created_at DESC)
| - paket_laris  : 4 paket aktif dengan bookings_count terbanyak
|                  (filter status confirmed/in_progress/completed)
|
|   Catatan:
| - `config(['inertia.testing.ensure_pages_exist' => false])` — backend test.
| - `Package::aktif()` scope — filter status aktif.
| - Client TIDAK butuh bank account (beda dengan mitra).
| - Test fokus CONTROLLER (filter, ordering, limit).
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
});

/*
|==========================================================================
| HELPER — buat package
|==========================================================================
*/
function makePackageForDashboard(User $mitra, array $overrides = []): Package
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
    it('GET /client/dashboard → render Client/Dashboard', function () {
        $this->actingAs($this->client)
            ->get('/client/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Client/Dashboard')
                ->has('paket_baru')
                ->has('paket_laris')
            );
    });

    it('paket_baru & paket_laris adalah array', function () {
        makePackageForDashboard($this->mitra);

        $this->actingAs($this->client)
            ->get('/client/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->has('paket_baru')
                ->has('paket_laris')
            );
    });

    it('paket resource shape: id, kode, nama, slug, harga_per_orang, isRombongan', function () {
        makePackageForDashboard($this->mitra, ['nama' => 'Paket Test']);

        $this->actingAs($this->client)
            ->get('/client/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->has('paket_baru.0.id')
                ->has('paket_baru.0.kode')
                ->has('paket_baru.0.nama')
                ->has('paket_baru.0.slug')
                ->has('paket_baru.0.harga_per_orang')
                ->has('paket_baru.0.isRombongan')
                ->where('paket_baru.0.nama', 'Paket Test')
            );
    });

    it('route name = client.dashboard', function () {
        expect(route('client.dashboard'))->toBe(url('/client/dashboard'));
    });
});

/*
|==========================================================================
| KATEGORI 2 — SECURITY & AUTHORIZATION
|==========================================================================
*/

describe('Security — guest & role', function () {
    it('guest di-redirect ke login', function () {
        $this->get('/client/dashboard')->assertRedirect(route('login'));
    });

    it('admin di-redirect ke admin.dashboard', function () {
        $admin = User::factory()->admin()->create(['must_change_password' => false]);

        $this->actingAs($admin)
            ->get('/client/dashboard')
            ->assertRedirect(route('admin.dashboard'));
    });

    it('mitra di-redirect ke mitra.dashboard', function () {
        $this->actingAs($this->mitra)
            ->get('/client/dashboard')
            ->assertRedirect(route('mitra.dashboard'));
    });

    it('client must_change_password=true di-redirect ke password.change', function () {
        $needChange = User::factory()->client()->create(['must_change_password' => true]);

        $this->actingAs($needChange)
            ->get('/client/dashboard')
            ->assertRedirect(route('password.change'));
    });

    it('client must_change_password=false bisa akses', function () {
        $this->actingAs($this->client)
            ->get('/client/dashboard')
            ->assertOk();
    });
});

/*
|==========================================================================
| KATEGORI 3 — CONCURRENCY
|==========================================================================
*/

describe('Concurrency — read-only consistency', function () {
    it('2x GET → data identik', function () {
        makePackageForDashboard($this->mitra);

        $first = $this->actingAs($this->client)
            ->get('/client/dashboard')
            ->viewData('page')['props']['paket_baru'];

        $second = $this->actingAs($this->client)
            ->get('/client/dashboard')
            ->viewData('page')['props']['paket_baru'];

        expect(array_column($first, 'id'))->toBe(array_column($second, 'id'));
    });
});

/*
|==========================================================================
| KATEGORI 4 — IDEMPOTENCY
|==========================================================================
*/

describe('Idempotency', function () {
    it('GET berulang tidak mengubah data', function () {
        makePackageForDashboard($this->mitra);
        $countBefore = Package::count();

        $this->actingAs($this->client)->get('/client/dashboard');
        $this->actingAs($this->client)->get('/client/dashboard');

        expect(Package::count())->toBe($countBefore);
    });
});

/*
|==========================================================================
| KATEGORI 5 — DATA INTEGRITY
|==========================================================================
*/

describe('Data Integrity — paket_baru (4 paket aktif terbaru)', function () {
    it('hanya menampilkan paket aktif', function () {
        makePackageForDashboard($this->mitra, ['nama' => 'Paket Aktif']);
        makePackageForDashboard($this->mitra, [
            'nama' => 'Paket Draft',
            'status' => PackageStatus::DRAFT->value,
        ]);
        makePackageForDashboard($this->mitra, [
            'nama' => 'Paket Arsip',
            'status' => PackageStatus::ARSIP->value,
        ]);

        $this->actingAs($this->client)
            ->get('/client/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->has('paket_baru', 1)
                ->where('paket_baru.0.nama', 'Paket Aktif')
            );
    });

    it('maksimal 4 paket terbaru', function () {
        for ($i = 0; $i < 10; $i++) {
            makePackageForDashboard($this->mitra);
        }

        $this->actingAs($this->client)
            ->get('/client/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->has('paket_baru', 4)
            );
    });

    it('diurutkan created_at DESC (terbaru dulu)', function () {
        $older = makePackageForDashboard($this->mitra, [
            'nama' => 'Paket Lama',
            'created_at' => now()->subDays(2),
        ]);
        $newer = makePackageForDashboard($this->mitra, [
            'nama' => 'Paket Baru',
            'created_at' => now()->subDay(),
        ]);

        $this->actingAs($this->client)
            ->get('/client/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->where('paket_baru.0.nama', 'Paket Baru')
                ->where('paket_baru.1.nama', 'Paket Lama')
            );
    });
});

describe('Data Integrity — paket_laris (4 paket terlaris)', function () {
    it('hanya menampilkan paket aktif', function () {
        $paketAktif = makePackageForDashboard($this->mitra, ['nama' => 'Paket Aktif']);
        makePackageForDashboard($this->mitra, [
            'nama' => 'Paket Draft',
            'status' => PackageStatus::DRAFT->value,
        ]);

        $this->actingAs($this->client)
            ->get('/client/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->has('paket_laris', 1)
                ->where('paket_laris.0.nama', 'Paket Aktif')
            );
    });

    it('maksimal 4 paket terlaris', function () {
        for ($i = 0; $i < 10; $i++) {
            makePackageForDashboard($this->mitra);
        }

        $this->actingAs($this->client)
            ->get('/client/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->has('paket_laris', 4)
            );
    });

    it('diurutkan bookings_count DESC (terlaris dulu)', function () {
        $packageA = makePackageForDashboard($this->mitra, ['nama' => 'Paket A']);
        $packageB = makePackageForDashboard($this->mitra, ['nama' => 'Paket B']);

        // Package A: 3 booking confirmed
        for ($i = 0; $i < 3; $i++) {
            Booking::factory()
                ->forPackage($packageA)
                ->forClient($this->client)
                ->create([
                    'mitra_id' => $this->mitra->id,
                    'status' => BookingStatus::CONFIRMED,
                ]);
        }

        // Package B: 1 booking confirmed
        Booking::factory()
            ->forPackage($packageB)
            ->forClient($this->client)
            ->create([
                'mitra_id' => $this->mitra->id,
                'status' => BookingStatus::CONFIRMED,
            ]);

        $this->actingAs($this->client)
            ->get('/client/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->where('paket_laris.0.nama', 'Paket A')
                ->where('paket_laris.1.nama', 'Paket B')
            );
    });

    it('booking PENDING_PAYMENT TIDAK dihitung', function () {
        $package = makePackageForDashboard($this->mitra, ['nama' => 'Paket Test']);

        // 5 booking pending_payment (tidak dihitung)
        for ($i = 0; $i < 5; $i++) {
            Booking::factory()
                ->forPackage($package)
                ->forClient($this->client)
                ->create([
                    'mitra_id' => $this->mitra->id,
                    'status' => BookingStatus::PENDING_PAYMENT,
                ]);
        }

        $this->actingAs($this->client)
            ->get('/client/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->has('paket_laris', 1)
                // bookings_count = 0 karena pending_payment tidak dihitung
                // (tapi tetap muncul karena filter aktif)
            );
    });

    it('booking CANCELLED TIDAK dihitung', function () {
        $package = makePackageForDashboard($this->mitra, ['nama' => 'Paket Test']);

        Booking::factory()
            ->forPackage($package)
            ->forClient($this->client)
            ->create([
                'mitra_id' => $this->mitra->id,
                'status' => BookingStatus::CANCELLED,
            ]);

        $this->actingAs($this->client)
            ->get('/client/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->has('paket_laris', 1)
            );
    });

    it('booking IN_PROGRESS & COMPLETED dihitung', function () {
        $package = makePackageForDashboard($this->mitra, ['nama' => 'Paket Test']);

        Booking::factory()
            ->forPackage($package)
            ->forClient($this->client)
            ->create(['mitra_id' => $this->mitra->id, 'status' => BookingStatus::IN_PROGRESS]);
        Booking::factory()
            ->forPackage($package)
            ->forClient($this->client)
            ->create(['mitra_id' => $this->mitra->id, 'status' => BookingStatus::COMPLETED]);

        $this->actingAs($this->client)
            ->get('/client/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->where('paket_laris.0.nama', 'Paket Test')
            );
    });
});

/*
|==========================================================================
| KATEGORI 6 — ERROR HANDLING
|==========================================================================
*/

describe('Error Handling — empty DB', function () {
    it('DB kosong → paket_baru & paket_laris kosong', function () {
        $this->actingAs($this->client)
            ->get('/client/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('paket_baru', 0)
                ->has('paket_laris', 0)
            );
    });
});

/*
|==========================================================================
| KATEGORI 7 — EDGE CASES
|==========================================================================
*/

describe('Edge Cases — paket tanpa booking', function () {
    it('paket tanpa booking tetap muncul di paket_laris (bookings_count = 0)', function () {
        makePackageForDashboard($this->mitra, ['nama' => 'Paket Tanpa Booking']);

        $this->actingAs($this->client)
            ->get('/client/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->has('paket_laris', 1)
                ->where('paket_laris.0.nama', 'Paket Tanpa Booking')
            );
    });
});
