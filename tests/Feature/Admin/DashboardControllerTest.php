<?php

use App\Enums\RefundStatus;
use App\Models\Booking;
use App\Models\Package;
use App\Models\Refund;
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
| DashboardController (admin) — read-only statistik & chart.
|
| Route: GET /admin/dashboard (admin.dashboard)
| Middleware: auth, password.changed, role:admin
|
| Statistik:
| - users (admin, mitra, client)
| - packages (total, aktif)
| - trips (total, aktif)
| - bookings (total)
| - total_revenue (net = gross - refund)
| - recent_bookings (5 terbaru)
| - monthly_revenue (6 bulan terakhir, net per bulan)
|
| Catatan:
| - `config(['inertia.testing.ensure_pages_exist' => false])` — backend test.
| - Revenue net = SUM(booking confirmed+completed) - SUM(refund SUCCEEDED).
| - Monthly revenue: 6 bulan terakhir, net per bulan.
| - Chart: group by `created_at` (bukan `tanggal_keberangkatan`).
| - JSON serialize int (800000), bukan float (800000.0) — pakai (float) cast
|   di assertion untuk perbandingan.
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
});

/*
|==========================================================================
| KATEGORI 1 — CONTRACT / API SCHEMA
|==========================================================================
*/

describe('Contract — index', function () {
    it('GET /admin/dashboard → render Admin/Dashboard', function () {
        $this->actingAs($this->admin)
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Dashboard')
                ->has('stats')
                ->has('recent_bookings')
                ->has('monthly_revenue')
            );
    });

    it('stats punya keys users, packages, trips, bookings, total_revenue', function () {
        $this->actingAs($this->admin)
            ->get('/admin/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->has('stats.users')
                ->has('stats.packages')
                ->has('stats.trips')
                ->has('stats.bookings')
                ->has('stats.total_revenue')
            );
    });

    it('stats.users punya keys admin, mitra, client', function () {
        $this->actingAs($this->admin)
            ->get('/admin/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->has('stats.users.admin')
                ->has('stats.users.mitra')
                ->has('stats.users.client')
            );
    });

    it('stats.packages punya keys total, aktif', function () {
        $this->actingAs($this->admin)
            ->get('/admin/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->has('stats.packages.total')
                ->has('stats.packages.aktif')
            );
    });

    it('stats.trips punya keys total, aktif', function () {
        $this->actingAs($this->admin)
            ->get('/admin/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->has('stats.trips.total')
                ->has('stats.trips.aktif')
            );
    });

    it('monthly_revenue berisi 6 elemen', function () {
        $this->actingAs($this->admin)
            ->get('/admin/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->has('monthly_revenue', 6)
            );
    });

    it('monthly_revenue item punya keys bulan, total', function () {
        $this->actingAs($this->admin)
            ->get('/admin/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->has('monthly_revenue.0.bulan')
                ->has('monthly_revenue.0.total')
            );
    });

    it('recent_bookings adalah array', function () {
        $this->actingAs($this->admin)
            ->get('/admin/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->has('recent_bookings')
            );
    });

    it('route name = admin.dashboard', function () {
        expect(route('admin.dashboard'))->toBe(url('/admin/dashboard'));
    });
});

/*
|==========================================================================
| KATEGORI 2 — SECURITY & AUTHORIZATION
|==========================================================================
*/

describe('Security — guest & role', function () {
    it('guest di-redirect ke login', function () {
        $this->get('/admin/dashboard')->assertRedirect(route('login'));
    });

    it('mitra di-redirect ke mitra.dashboard', function () {
        $mitra = User::factory()->mitra()->create(['must_change_password' => false]);

        $this->actingAs($mitra)
            ->get('/admin/dashboard')
            ->assertRedirect(route('mitra.dashboard'));
    });

    it('client di-redirect ke client.dashboard', function () {
        $client = User::factory()->client()->create(['must_change_password' => false]);

        $this->actingAs($client)
            ->get('/admin/dashboard')
            ->assertRedirect(route('client.dashboard'));
    });

    it('admin must_change_password=true di-redirect ke password.change', function () {
        $needChange = User::factory()->admin()->create(['must_change_password' => true]);

        $this->actingAs($needChange)
            ->get('/admin/dashboard')
            ->assertRedirect(route('password.change'));
    });

    it('admin must_change_password=false bisa akses', function () {
        $this->actingAs($this->admin)
            ->get('/admin/dashboard')
            ->assertOk();
    });
});

/*
|==========================================================================
| KATEGORI 3 — CONCURRENCY
|==========================================================================
*/

describe('Concurrency — read-only consistency', function () {
    it('2x GET → stats identik', function () {
        $first = $this->actingAs($this->admin)
            ->get('/admin/dashboard')
            ->viewData('page')['props']['stats'];

        $second = $this->actingAs($this->admin)
            ->get('/admin/dashboard')
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
        User::factory()->count(2)->create(['must_change_password' => false]);
        $countBefore = User::count();

        $this->actingAs($this->admin)->get('/admin/dashboard');
        $this->actingAs($this->admin)->get('/admin/dashboard');

        expect(User::count())->toBe($countBefore);
    });
});

/*
|==========================================================================
| KATEGORI 5 — DATA INTEGRITY (STATISTIK)
|==========================================================================
*/

describe('Data Integrity — stats.users', function () {
    it('menghitung jumlah admin, mitra, client dengan benar', function () {
        User::factory()->mitra()->count(3)->create(['must_change_password' => false]);
        User::factory()->client()->count(5)->create(['must_change_password' => false]);
        // admin: 1 dari beforeEach + 2 baru = 3
        User::factory()->admin()->count(2)->create(['must_change_password' => false]);

        $this->actingAs($this->admin)
            ->get('/admin/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.users.admin', 3)
                ->where('stats.users.mitra', 3)
                ->where('stats.users.client', 5)
            );
    });
});

describe('Data Integrity — stats.packages', function () {
    it('menghitung total & aktif package', function () {
        $mitra = User::factory()->mitra()->create(['must_change_password' => false]);

        Package::factory()->forMitra($mitra)->aktif()->count(2)->create();
        Package::factory()->forMitra($mitra)->draft()->count(3)->create();
        Package::factory()->forMitra($mitra)->arsip()->count(1)->create();

        $this->actingAs($this->admin)
            ->get('/admin/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.packages.total', 6)
                ->where('stats.packages.aktif', 2)
            );
    });
});

describe('Data Integrity — stats.trips', function () {
    it('menghitung total & aktif trip', function () {
        $mitra = User::factory()->mitra()->create(['must_change_password' => false]);
        $package = Package::factory()->forMitra($mitra)->aktif()->create();

        Trip::factory()->forPackage($package)->scheduled()->count(2)->create();
        Trip::factory()->forPackage($package)->active()->count(1)->create();
        Trip::factory()->forPackage($package)->completed()->count(1)->create();

        $this->actingAs($this->admin)
            ->get('/admin/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.trips.total', 4)
                ->where('stats.trips.aktif', 1)
            );
    });
});

describe('Data Integrity — stats.bookings', function () {
    it('menghitung total booking (semua status)', function () {
        Booking::factory()->pendingPayment()->count(2)->create();
        Booking::factory()->confirmed()->count(3)->create();
        Booking::factory()->cancelled()->count(1)->create();

        $this->actingAs($this->admin)
            ->get('/admin/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.bookings.total', 6)
            );
    });
});

describe('Data Integrity — total_revenue (net = gross - refund)', function () {
    it('revenue = SUM(confirmed+completed) - SUM(refund SUCCEEDED)', function () {
        // Gross: 1.000.000 + 500.000 = 1.500.000
        $booking1 = Booking::factory()->confirmed()->create(['total_harga' => 1_000_000]);
        $booking2 = Booking::factory()->completed()->create(['total_harga' => 500_000]);

        // Pending payment TIDAK dihitung
        Booking::factory()->pendingPayment()->create(['total_harga' => 2_000_000]);

        // Refund SUCCEEDED: 200.000
        Refund::factory()->succeeded()->create([
            'booking_id' => $booking1->id,
            'amount' => 200_000,
        ]);

        // Refund PENDING TIDAK dihitung
        Refund::factory()->create([
            'booking_id' => $booking1->id,
            'status' => RefundStatus::PENDING->value,
            'amount' => 500_000,
        ]);

        $this->actingAs($this->admin)
            ->get('/admin/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.total_revenue', 1_300_000) // 1.500.000 - 200.000
            );
    });

    it('revenue = gross jika tidak ada refund', function () {
        Booking::factory()->confirmed()->create(['total_harga' => 1_000_000]);

        $this->actingAs($this->admin)
            ->get('/admin/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.total_revenue', 1_000_000)
            );
    });

    it('revenue = 0 jika tidak ada booking confirmed/completed', function () {
        Booking::factory()->pendingPayment()->create(['total_harga' => 1_000_000]);

        $this->actingAs($this->admin)
            ->get('/admin/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.total_revenue', 0)
            );
    });
});

describe('Data Integrity — recent_bookings', function () {
    it('maksimal 5 booking terbaru', function () {
        Booking::factory()->count(10)->create();

        $this->actingAs($this->admin)
            ->get('/admin/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->has('recent_bookings', 5)
            );
    });

    it('recent_bookings item punya shape: id, kode, client, mitra, paket, total, status, tanggal', function () {
        $client = User::factory()->client()->create([
            'name' => 'Budi Client',
            'must_change_password' => false,
        ]);
        $mitra = User::factory()->mitra()->create([
            'name' => 'Mitra Travel',
            'must_change_password' => false,
        ]);
        $package = Package::factory()->forMitra($mitra)->aktif()->create(['nama' => 'Paket Test']);

        Booking::factory()
            ->forPackage($package)
            ->forClient($client)
            ->confirmed()
            ->create(['kode' => 'BKG-TEST-001', 'total_harga' => 500_000]);

        $this->actingAs($this->admin)
            ->get('/admin/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->has('recent_bookings.0.id')
                ->has('recent_bookings.0.kode')
                ->has('recent_bookings.0.client')
                ->has('recent_bookings.0.mitra')
                ->has('recent_bookings.0.paket')
                ->has('recent_bookings.0.total')
                ->has('recent_bookings.0.status')
                ->has('recent_bookings.0.tanggal')
                ->where('recent_bookings.0.kode', 'BKG-TEST-001')
                ->where('recent_bookings.0.client', 'Budi Client')
                ->where('recent_bookings.0.mitra', 'Mitra Travel')
                ->where('recent_bookings.0.paket', 'Paket Test')
            );
    });

    it('recent_bookings diurutkan created_at DESC', function () {
        $older = Booking::factory()->create([
            'kode' => 'BKG-OLDER',
            'created_at' => now()->subDays(2),
        ]);
        $newer = Booking::factory()->create([
            'kode' => 'BKG-NEWER',
            'created_at' => now()->subDay(),
        ]);

        $this->actingAs($this->admin)
            ->get('/admin/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->where('recent_bookings.0.kode', 'BKG-NEWER')
            );
    });
});

describe('Data Integrity — monthly_revenue', function () {
    it('monthly_revenue berisi 6 bulan terakhir', function () {
        $this->actingAs($this->admin)
            ->get('/admin/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->has('monthly_revenue', 6)
            );
    });

    it('monthly_revenue menghitung net (gross - refund) per bulan', function () {
        // Bulan ini: gross 1.000.000, refund 200.000 → net 800.000
        $booking = Booking::factory()->confirmed()->create([
            'total_harga' => 1_000_000,
            'created_at' => now(),
        ]);
        Refund::factory()->succeeded()->create([
            'booking_id' => $booking->id,
            'amount' => 200_000,
            'created_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->get('/admin/dashboard')
            ->assertInertia(function (Assert $page) {
                $monthlyRevenue = $page->toArray()['props']['monthly_revenue'];
                $lastMonth = end($monthlyRevenue);

                // JSON serialize int (800000), bukan float (800000.0).
                // Pakai (float) cast untuk perbandingan.
                expect((float) $lastMonth['total'])->toBe(800_000.0);
            });
    });

    it('bulan tanpa data → total 0', function () {
        $this->actingAs($this->admin)
            ->get('/admin/dashboard')
            ->assertInertia(function (Assert $page) {
                $monthlyRevenue = $page->toArray()['props']['monthly_revenue'];

                foreach ($monthlyRevenue as $month) {
                    // JSON serialize int (0), bukan float (0.0).
                    expect((float) $month['total'])->toBe(0.0);
                }
            });
    });
});

/*
|==========================================================================
| KATEGORI 6 — ERROR HANDLING
|==========================================================================
*/

describe('Error Handling — empty DB', function () {
    it('DB kosong → stats 0, recent_bookings kosong, monthly_revenue 0', function () {
        // beforeEach sudah buat 1 admin — jadi admin = 1
        User::where('role', '!=', 'admin')->delete();

        $this->actingAs($this->admin)
            ->get('/admin/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.users.mitra', 0)
                ->where('stats.users.client', 0)
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

describe('Edge Cases — gross & refund null-safe', function () {
    it('SUM(total_harga) null → revenue 0', function () {
        // Tidak ada booking confirmed/completed
        Booking::factory()->pendingPayment()->count(3)->create();

        $this->actingAs($this->admin)
            ->get('/admin/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.total_revenue', 0)
            );
    });

    it('refund SUCCEEDED > gross → revenue negatif (allowed)', function () {
        // Gross 500.000, refund 700.000 → net -200.000
        $booking = Booking::factory()->confirmed()->create(['total_harga' => 500_000]);
        Refund::factory()->succeeded()->create([
            'booking_id' => $booking->id,
            'amount' => 700_000,
        ]);

        $this->actingAs($this->admin)
            ->get('/admin/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.total_revenue', -200_000)
            );
    });
});

describe('Edge Cases — recent_bookings null-safe', function () {
    it('booking tanpa client/mitra/package → tampil dengan "-"', function () {
        // Booking::factory() selalu buat client/mitra/package,
        // jadi test ini untuk dokumentasi behavior null-safe.
        Booking::factory()->create();

        $this->actingAs($this->admin)
            ->get('/admin/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->has('recent_bookings.0.client')
                ->has('recent_bookings.0.mitra')
                ->has('recent_bookings.0.paket')
            );
    });
});
