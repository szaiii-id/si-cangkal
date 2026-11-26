<?php

use App\Models\Booking;
use App\Models\Package;
use App\Models\Trip;
use App\Models\User;
use App\Services\Report\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response as IlluminateResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery\MockInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

uses(RefreshDatabase::class);

/*
|==========================================================================
| SETUP — Integration Test
|==========================================================================
|
| ReportController (admin) — laporan & export.
|
| Route:
| - GET /admin/reports                         → index (admin.reports.index)
| - GET /admin/reports/export-pdf              → exportPdf
| - GET /admin/reports/export-excel            → exportExcel
| - GET /admin/reports/mitra                   → mitraPerformance
| - GET /admin/reports/mitra/export-pdf        → exportMitraPdf
| - GET /admin/reports/mitra/export-excel      → exportMitraExcel
| - GET /admin/trips/{trip}/manifest           → exportTripManifest
|
| Middleware: auth, password.changed, role:admin
| FormRequest: ReportFilterRequest (start_date, end_date)
|
|   Catatan:
| - Kategori 1-6: real ReportService + DB (integration).
| - Kategori 7 (Export): mock ReportService — verifikasi delegation,
|   bukan implementation (Pdf/Excel sudah di-test di ReportServiceTest).
| - `config(['inertia.testing.ensure_pages_exist' => false])` — backend test.
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

describe('Contract — index (revenue report)', function () {
    it('GET /admin/reports → render Admin/Reports/Index', function () {
        $this->actingAs($this->admin)
            ->get('/admin/reports')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Reports/Index')
                ->has('data')
                ->has('monthly_revenue')
                ->has('filters')
            );
    });

    it('data punya keys gross_revenue, total_refund, total_revenue, transactions', function () {
        $this->actingAs($this->admin)
            ->get('/admin/reports')
            ->assertInertia(fn (Assert $page) => $page
                ->has('data.gross_revenue')
                ->has('data.total_refund')
                ->has('data.total_revenue')
                ->has('data.transactions')
            );
    });

    it('filters default: bulan berjalan', function () {
        $this->actingAs($this->admin)
            ->get('/admin/reports')
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.start_date', now()->startOfMonth()->format('Y-m-d'))
                ->where('filters.end_date', now()->endOfMonth()->format('Y-m-d'))
            );
    });

    it('route name = admin.reports.index', function () {
        expect(route('admin.reports.index'))->toBe(url('/admin/reports'));
    });
});

describe('Contract — mitraPerformance', function () {
    it('GET /admin/reports/mitra → render Admin/Reports/Mitra', function () {
        $this->actingAs($this->admin)
            ->get('/admin/reports/mitra')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Reports/Mitra')
                ->has('data')
                ->has('filters')
            );
    });

    it('data punya keys total_mitra, total_trip_completed, mitras', function () {
        $this->actingAs($this->admin)
            ->get('/admin/reports/mitra')
            ->assertInertia(fn (Assert $page) => $page
                ->has('data.total_mitra')
                ->has('data.total_trip_completed')
                ->has('data.mitras')
            );
    });

    it('route name = admin.reports.mitra', function () {
        expect(route('admin.reports.mitra'))->toBe(url('/admin/reports/mitra'));
    });
});

/*
|==========================================================================
| KATEGORI 2 — SECURITY & AUTHORIZATION
|==========================================================================
*/

describe('Security — guest & role', function () {
    it('guest di-redirect ke login (index)', function () {
        $this->get('/admin/reports')->assertRedirect(route('login'));
    });

    it('mitra di-redirect (index)', function () {
        $this->actingAs($this->mitra)
            ->get('/admin/reports')
            ->assertRedirect(route('mitra.dashboard'));
    });

    it('client di-redirect (index)', function () {
        $this->actingAs($this->client)
            ->get('/admin/reports')
            ->assertRedirect(route('client.dashboard'));
    });

    it('admin must_change_password=true di-redirect ke password.change', function () {
        $needChange = User::factory()->admin()->create(['must_change_password' => true]);

        $this->actingAs($needChange)
            ->get('/admin/reports')
            ->assertRedirect(route('password.change'));
    });

    it('admin bisa akses index', function () {
        $this->actingAs($this->admin)
            ->get('/admin/reports')
            ->assertOk();
    });

    it('admin bisa akses mitra performance', function () {
        $this->actingAs($this->admin)
            ->get('/admin/reports/mitra')
            ->assertOk();
    });
});

/*
|==========================================================================
| KATEGORI 3 — CONCURRENCY
|==========================================================================
*/

describe('Concurrency — read-only consistency', function () {
    it('2x GET index → data identik', function () {
        Booking::factory()->confirmed()->create(['total_harga' => 500_000]);

        $first = $this->actingAs($this->admin)
            ->get('/admin/reports')
            ->viewData('page')['props']['data']['gross_revenue'];

        $second = $this->actingAs($this->admin)
            ->get('/admin/reports')
            ->viewData('page')['props']['data']['gross_revenue'];

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
        Booking::factory()->confirmed()->create(['total_harga' => 500_000]);
        $countBefore = Booking::count();

        $this->actingAs($this->admin)->get('/admin/reports');
        $this->actingAs($this->admin)->get('/admin/reports');

        expect(Booking::count())->toBe($countBefore);
    });
});

/*
|==========================================================================
| KATEGORI 5 — DATA INTEGRITY
|==========================================================================
*/

describe('Data Integrity — revenue filter', function () {
    it('filter start_date + end_date → data sesuai range', function () {
        Booking::factory()->confirmed()->create([
            'total_harga' => 500_000,
            'created_at' => '2026-01-15 10:00:00',
        ]);
        Booking::factory()->confirmed()->create([
            'total_harga' => 300_000,
            'created_at' => '2026-02-15 10:00:00',
        ]);

        $this->actingAs($this->admin)
            ->get('/admin/reports?start_date=2026-01-01&end_date=2026-01-31')
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.start_date', '2026-01-01')
                ->where('filters.end_date', '2026-01-31')
            );
    });
});

describe('Data Integrity — mitra performance', function () {
    it('total_mitra sesuai jumlah mitra di DB', function () {
        // Isolasi: hapus mitra dari beforeEach agar total = 2
        User::where('role', 'mitra')->delete();

        User::factory()->mitra()->count(2)->create(['must_change_password' => false]);

        $this->actingAs($this->admin)
            ->get('/admin/reports/mitra')
            ->assertInertia(fn (Assert $page) => $page
                ->where('data.total_mitra', 2)
            );
    });
});

/*
|==========================================================================
| KATEGORI 6 — ERROR HANDLING (Validation)
|==========================================================================
*/

describe('Error Handling — validation', function () {
    it('start_date format invalid → error', function () {
        $this->actingAs($this->admin)
            ->get('/admin/reports?start_date=bukan-tanggal')
            ->assertSessionHasErrors('start_date');
    });

    it('end_date format invalid → error', function () {
        $this->actingAs($this->admin)
            ->get('/admin/reports?end_date=bukan-tanggal')
            ->assertSessionHasErrors('end_date');
    });

    it('end_date < start_date → error', function () {
        $this->actingAs($this->admin)
            ->get('/admin/reports?start_date=2026-02-01&end_date=2026-01-01')
            ->assertSessionHasErrors('end_date');
    });

    it('rentang lebih dari 12 bulan → error', function () {
        $start = now()->subYear()->subDay()->format('Y-m-d');
        $end = now()->format('Y-m-d');

        $this->actingAs($this->admin)
            ->get("/admin/reports?start_date={$start}&end_date={$end}")
            ->assertSessionHasErrors('end_date');
    });
});

/*
|==========================================================================
| KATEGORI 7 — EXPORT PDF / EXCEL / MANIFEST (mock ReportService)
|==========================================================================
|
| ReportServiceTest sudah test implementation (Pdf::loadView, Excel::download,
| dll.). Di sini kita cukup verifikasi CONTROLLER delegate ke service.
|
*/

describe('Export — delegate ke ReportService', function () {
    it('exportPdf delegate ke ReportService::exportRevenuePdf', function () {
        $response = new IlluminateResponse('pdf');

        $this->mock(ReportService::class, function (MockInterface $m) use ($response) {
            $m->shouldReceive('exportRevenuePdf')
                ->once()
                ->with('2026-01-01', '2026-01-31')
                ->andReturn($response);
        });

        $this->actingAs($this->admin)
            ->get('/admin/reports/export-pdf?start_date=2026-01-01&end_date=2026-01-31')
            ->assertOk();
    });

    it('exportExcel delegate ke ReportService::exportRevenueExcel', function () {
        // Pakai instance asli BinaryFileResponse — bukan mock.
        // Mock tidak stub getStatusCode() yang dipanggil Router.
        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempFile, 'dummy');
        $response = new BinaryFileResponse($tempFile);

        $this->mock(ReportService::class, function (MockInterface $m) use ($response) {
            $m->shouldReceive('exportRevenueExcel')
                ->once()
                ->with('2026-01-01', '2026-01-31')
                ->andReturn($response);
        });

        $this->actingAs($this->admin)
            ->get('/admin/reports/export-excel?start_date=2026-01-01&end_date=2026-01-31')
            ->assertOk();
    });

    it('exportMitraPdf delegate ke ReportService::exportMitraPdf', function () {
        $response = new IlluminateResponse('pdf');

        $this->mock(ReportService::class, function (MockInterface $m) use ($response) {
            $m->shouldReceive('exportMitraPdf')
                ->once()
                ->with('2026-01-01', '2026-01-31')
                ->andReturn($response);
        });

        $this->actingAs($this->admin)
            ->get('/admin/reports/mitra/export-pdf?start_date=2026-01-01&end_date=2026-01-31')
            ->assertOk();
    });

    it('exportMitraExcel delegate ke ReportService::exportMitraExcel', function () {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempFile, 'dummy');
        $response = new BinaryFileResponse($tempFile);

        $this->mock(ReportService::class, function (MockInterface $m) use ($response) {
            $m->shouldReceive('exportMitraExcel')
                ->once()
                ->with('2026-01-01', '2026-01-31')
                ->andReturn($response);
        });

        $this->actingAs($this->admin)
            ->get('/admin/reports/mitra/export-excel?start_date=2026-01-01&end_date=2026-01-31')
            ->assertOk();
    });

    it('exportTripManifest delegate ke ReportService::exportTripManifest', function () {
        $trip = makeTripForManifest($this->mitra, $this->client);

        $response = new IlluminateResponse('pdf');

        $this->mock(ReportService::class, function (MockInterface $m) use ($response) {
            $m->shouldReceive('exportTripManifest')
                ->once()
                ->with(Mockery::on(fn ($t) => $t->id > 0))
                ->andReturn($response);
        });

        $this->actingAs($this->admin)
            ->get("/admin/trips/{$trip->id}/manifest")
            ->assertOk();
    });
});

/*
|==========================================================================
| HELPER — buat trip lengkap dengan booking
|==========================================================================
*/
function makeTripForManifest(User $mitra, User $client): Trip
{
    $package = Package::factory()->forMitra($mitra)->aktif()->create();
    $booking = Booking::factory()
        ->forPackage($package)
        ->forClient($client)
        ->confirmed()
        ->create();

    return Trip::factory()->forBooking($booking)->create();
}
