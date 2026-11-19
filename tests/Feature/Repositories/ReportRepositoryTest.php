<?php

use App\Enums\RefundStatus;
use App\Models\Booking;
use App\Models\Package;
use App\Models\Refund;
use App\Models\Trip;
use App\Models\User;
use App\Repositories\Eloquent\ReportRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/*
|==========================================================================
| SETUP
|==========================================================================
|
| - RefreshDatabase: reset DB tiap test (SQLite :memory:)
| - PRAGMA foreign_keys = ON: SQLite default OFF → perlu diaktifkan
| - ReportRepository = read-only, no interface, no search
|
*/

beforeEach(function () {

    // Freeze time untuk test deterministik
    Carbon::setTestNow(Carbon::create(2026, 1, 15, 10, 0, 0, 'Asia/Makassar'));

    $this->repo = new ReportRepository;
});

afterEach(function () {
    Carbon::setTestNow();
});

/*
|==========================================================================
| KATEGORI 1 — CONTRACT / RETURN TYPE & SHAPE
|==========================================================================
*/

describe('Contract — getRevenueData', function () {
    it('mengembalikan array dengan keys yang diharapkan', function () {
        $result = $this->repo->getRevenueData('2026-01-01', '2026-01-31');

        expect($result)->toHaveKeys([
            'gross_revenue',
            'total_refund',
            'total_revenue',
            'transactions',
        ]);
    });

    it('gross_revenue, total_refund, total_revenue bertipe float', function () {
        $result = $this->repo->getRevenueData('2026-01-01', '2026-01-31');

        expect($result['gross_revenue'])->toBeFloat()
            ->and($result['total_refund'])->toBeFloat()
            ->and($result['total_revenue'])->toBeFloat();
    });

    it('transactions adalah Collection (bukan array native)', function () {
        // Catatan: `->get()->map()` di repo mengembalikan Eloquent Collection,
        // bukan array. Ini behavior normal, bukan bug.
        $result = $this->repo->getRevenueData('2026-01-01', '2026-01-31');

        expect($result['transactions'])->toBeInstanceOf(Collection::class);
    });
});

describe('Contract — getMitraPerformanceData', function () {
    it('mengembalikan array dengan keys yang diharapkan', function () {
        $result = $this->repo->getMitraPerformanceData('2026-01-01', '2026-01-31');

        expect($result)->toHaveKeys([
            'total_mitra',
            'total_trip_completed',
            'mitras',
        ]);
    });

    it('total_mitra dan total_trip_completed adalah integer', function () {
        $result = $this->repo->getMitraPerformanceData('2026-01-01', '2026-01-31');

        expect($result['total_mitra'])->toBeInt()
            ->and($result['total_trip_completed'])->toBeInt();
    });

    it('mitras adalah Collection (bukan array native)', function () {
        $result = $this->repo->getMitraPerformanceData('2026-01-01', '2026-01-31');

        expect($result['mitras'])->toBeInstanceOf(Collection::class);
    });
});

describe('Contract — getMonthlyRevenue', function () {
    it('mengembalikan array 6 elemen', function () {
        $result = $this->repo->getMonthlyRevenue();

        expect($result)->toHaveCount(6);
    });

    it('setiap elemen punya keys bulan dan total', function () {
        $result = $this->repo->getMonthlyRevenue();

        expect($result[0])->toHaveKeys(['bulan', 'total']);
    });

    it('bulan bertipe string, total bertipe float', function () {
        $result = $this->repo->getMonthlyRevenue();

        expect($result[0]['bulan'])->toBeString()
            ->and($result[0]['total'])->toBeFloat();
    });
});

/*
|==========================================================================
| KATEGORI 2 — DATA INTEGRITY: getRevenueData
|==========================================================================
*/

describe('Data Integrity — getRevenueData', function () {
    it('menghitung gross_revenue dari booking confirmed + completed', function () {
        Booking::factory()->confirmed()->create(['total_harga' => 500_000]);
        Booking::factory()->completed()->create(['total_harga' => 300_000]);
        Booking::factory()->pendingPayment()->create(['total_harga' => 1_000_000]); // tidak dihitung

        $result = $this->repo->getRevenueData('2026-01-01', '2026-01-31');

        expect($result['gross_revenue'])->toBe(800_000.0);
    });

    it('menghitung total_refund dari refund SUCCEEDED saja', function () {
        $booking = Booking::factory()->confirmed()->create(['total_harga' => 0]);
        Refund::factory()->succeeded()->create([
            'booking_id' => $booking->id,
            'amount' => 100_000,
        ]);
        Refund::factory()->create([  // PENDING → tidak dihitung
            'booking_id' => $booking->id,
            'status' => RefundStatus::PENDING->value,
            'amount' => 500_000,
        ]);

        $result = $this->repo->getRevenueData('2026-01-01', '2026-01-31');

        expect($result['total_refund'])->toBe(100_000.0);
    });

    it('total_revenue = gross - refund', function () {
        Booking::factory()->confirmed()->create(['total_harga' => 1_000_000]);
        // Booking terpisah dengan harga 0 untuk refund (supaya gross hanya 1 juta)
        $booking = Booking::factory()->confirmed()->create(['total_harga' => 0]);
        Refund::factory()->succeeded()->create([
            'booking_id' => $booking->id,
            'amount' => 200_000,
        ]);

        $result = $this->repo->getRevenueData('2026-01-01', '2026-01-31');

        // Gross = 1.000.000 + 0 = 1.000.000
        // Refund = 200.000
        // Net = 800.000
        expect($result['gross_revenue'])->toBe(1_000_000.0)
            ->and($result['total_refund'])->toBe(200_000.0)
            ->and($result['total_revenue'])->toBe(800_000.0);
    });

    it('transactions berisi data booking dengan keys yang diharapkan', function () {
        $client = User::factory()->client()->create(['name' => 'Budi Client']);
        $mitra = User::factory()->mitra()->create(['name' => 'Budi Mitra']);
        $package = Package::factory()->forMitra($mitra)->create(['nama' => 'Paket Test']);
        Booking::factory()->forPackage($package)->forClient($client)->confirmed()->create([
            'kode' => 'BKG-2026-001',
            'total_harga' => 500_000,
        ]);

        $result = $this->repo->getRevenueData('2026-01-01', '2026-01-31');

        expect($result['transactions'])->toHaveCount(1);
        $t = $result['transactions'][0];
        expect($t)->toHaveKeys(['kode', 'tanggal', 'client', 'mitra', 'paket', 'total', 'status'])
            ->and($t['kode'])->toBe('BKG-2026-001')
            ->and($t['client'])->toBe('Budi Client')
            ->and($t['mitra'])->toBe('Budi Mitra')
            ->and($t['paket'])->toBe('Paket Test');
    });

    it('filter by tanggal — hanya booking dalam range', function () {
        Booking::factory()->confirmed()->create([
            'total_harga' => 500_000,
            'created_at' => '2026-01-15 10:00:00',
        ]);
        Booking::factory()->confirmed()->create([
            'total_harga' => 300_000,
            'created_at' => '2026-02-15 10:00:00',  // luar range
        ]);

        $result = $this->repo->getRevenueData('2026-01-01', '2026-01-31');

        expect($result['gross_revenue'])->toBe(500_000.0);
    });

    it('range kosong (tidak ada data) → gross 0, refund 0, revenue 0', function () {
        $result = $this->repo->getRevenueData('2026-01-01', '2026-01-31');

        expect($result['gross_revenue'])->toBe(0.0)
            ->and($result['total_refund'])->toBe(0.0)
            ->and($result['total_revenue'])->toBe(0.0)
            ->and($result['transactions'])->toBeEmpty();
    });

    it('refund dengan booking yang sama tetap dihitung terpisah', function () {
        $booking = Booking::factory()->confirmed()->create([
            'total_harga' => 1_000_000,
        ]);
        Refund::factory()->succeeded()->create([
            'booking_id' => $booking->id,
            'amount' => 100_000,
        ]);
        Refund::factory()->succeeded()->create([
            'booking_id' => $booking->id,
            'amount' => 50_000,
        ]);

        $result = $this->repo->getRevenueData('2026-01-01', '2026-01-31');

        expect($result['total_refund'])->toBe(150_000.0)
            ->and($result['total_revenue'])->toBe(850_000.0);
    });
});

/*
|==========================================================================
| KATEGORI 3 — DATA INTEGRITY: getMitraPerformanceData
|==========================================================================
*/

describe('Data Integrity — getMitraPerformanceData', function () {
    it('total_mitra = jumlah mitra yang ada', function () {
        User::factory()->mitra()->count(3)->create();
        User::factory()->client()->count(2)->create();

        $result = $this->repo->getMitraPerformanceData('2026-01-01', '2026-01-31');

        expect($result['total_mitra'])->toBe(3);
    });

    it('total_trip_completed = jumlah trip completed dalam range', function () {
        $mitra = User::factory()->mitra()->create();

        // 2 trip completed dalam range
        Trip::factory()->completed()->count(2)->create([
            'mitra_id' => $mitra->id,
            'tanggal_mulai' => '2026-01-15',
        ]);
        // 1 trip scheduled (tidak dihitung)
        Trip::factory()->scheduled()->create([
            'mitra_id' => $mitra->id,
            'tanggal_mulai' => '2026-01-20',
        ]);

        $result = $this->repo->getMitraPerformanceData('2026-01-01', '2026-01-31');

        expect($result['total_trip_completed'])->toBe(2);
    });

    it('total_revenue per mitra dihitung dari booking confirmed + completed', function () {
        $mitra = User::factory()->mitra()->create();
        $package = Package::factory()->forMitra($mitra)->create();

        Booking::factory()->forPackage($package)->confirmed()->create(['total_harga' => 500_000]);
        Booking::factory()->forPackage($package)->completed()->create(['total_harga' => 300_000]);
        Booking::factory()->forPackage($package)->pendingPayment()->create(['total_harga' => 1_000_000]);

        $result = $this->repo->getMitraPerformanceData('2026-01-01', '2026-01-31');

        $mitraData = $result['mitras'][0];
        expect((float) $mitraData['total_revenue'])->toBe(800_000.0);
    });

    it('mitra diurutkan berdasarkan total_revenue DESC', function () {
        $mitraA = User::factory()->mitra()->create(['name' => 'A']);
        $mitraB = User::factory()->mitra()->create(['name' => 'B']);

        $packageA = Package::factory()->forMitra($mitraA)->create();
        $packageB = Package::factory()->forMitra($mitraB)->create();

        Booking::factory()->forPackage($packageA)->confirmed()->create(['total_harga' => 500_000]);
        Booking::factory()->forPackage($packageB)->confirmed()->create(['total_harga' => 1_000_000]);

        $result = $this->repo->getMitraPerformanceData('2026-01-01', '2026-01-31');

        expect($result['mitras'][0]['id'])->toBe($mitraB->id)  // revenue lebih tinggi
            ->and($result['mitras'][1]['id'])->toBe($mitraA->id);
    });

    it('mitra tanpa booking → total_revenue 0', function () {
        User::factory()->mitra()->create();

        $result = $this->repo->getMitraPerformanceData('2026-01-01', '2026-01-31');

        expect((float) $result['mitras'][0]['total_revenue'])->toBe(0.0);
    });

    it('mitra dengan total_trip = 0 tetap muncul', function () {
        User::factory()->mitra()->create();

        $result = $this->repo->getMitraPerformanceData('2026-01-01', '2026-01-31');

        expect($result['total_mitra'])->toBe(1)
            ->and($result['mitras'][0]['total_trip'])->toBe(0);
    });
});

/*
|==========================================================================
| KATEGORI 4 — DATA INTEGRITY: getMonthlyRevenue
|==========================================================================
*/

describe('Data Integrity — getMonthlyRevenue', function () {
    it('mengembalikan 6 bulan terakhir berurutan', function () {
        // Set locale English supaya translatedFormat('M') = 'Aug', bukan 'Agt'
        app()->setLocale('en');

        // Freeze: 2026-01-15
        // 6 bulan terakhir: Agustus 2025, September, Oktober, November, Desember, Januari 2026
        $result = $this->repo->getMonthlyRevenue();

        expect($result)->toHaveCount(6)
            ->and($result[0]['bulan'])->toBe('Aug')  // 5 bulan lalu
            ->and($result[5]['bulan'])->toBe('Jan'); // sekarang
    });

    it('menghitung net (gross - refund) per bulan', function () {
        app()->setLocale('en');

        // Booking 1 Jan 2026, gross 1 juta
        Booking::factory()->confirmed()->create([
            'total_harga' => 1_000_000,
            'created_at' => '2026-01-10 10:00:00',
        ]);
        // Booking terpisah harga 0 untuk refund (supaya gross hanya 1 juta)
        $booking = Booking::factory()->confirmed()->create([
            'total_harga' => 0,
            'created_at' => '2026-01-10 10:00:00',
        ]);
        Refund::factory()->succeeded()->create([
            'booking_id' => $booking->id,
            'amount' => 200_000,
            'created_at' => '2026-01-10 10:00:00',
        ]);

        $result = $this->repo->getMonthlyRevenue();

        $jan = collect($result)->firstWhere('bulan', 'Jan');
        expect($jan['total'])->toBe(800_000.0);  // 1jt - 200rb
    });

    it('bulan tanpa data → total 0', function () {
        $result = $this->repo->getMonthlyRevenue();

        foreach ($result as $month) {
            expect($month['total'])->toBe(0.0);
        }
    });
});

/*
|==========================================================================
| KATEGORI 5 — EDGE CASES
|==========================================================================
*/

describe('Edge Cases — getRevenueData', function () {
    it('range tanggal 1 hari', function () {
        Booking::factory()->confirmed()->create([
            'total_harga' => 500_000,
            'created_at' => '2026-01-15 10:00:00',
        ]);

        $result = $this->repo->getRevenueData('2026-01-15', '2026-01-15');

        expect($result['gross_revenue'])->toBe(500_000.0);
    });

    it('range tanggal terbalik (end < start) → 0', function () {
        Booking::factory()->confirmed()->create([
            'total_harga' => 500_000,
            'created_at' => '2026-01-15 10:00:00',
        ]);

        $result = $this->repo->getRevenueData('2026-12-31', '2026-01-01');

        expect($result['gross_revenue'])->toBe(0.0);
    });

    it('booking pada batas akhir (23:59:59) tetap dihitung', function () {
        Booking::factory()->confirmed()->create([
            'total_harga' => 500_000,
            'created_at' => '2026-01-31 23:59:59',
        ]);

        $result = $this->repo->getRevenueData('2026-01-01', '2026-01-31');

        expect($result['gross_revenue'])->toBe(500_000.0);
    });

    it('booking pada detik pertama (00:00:00) tetap dihitung', function () {
        Booking::factory()->confirmed()->create([
            'total_harga' => 500_000,
            'created_at' => '2026-01-01 00:00:00',
        ]);

        $result = $this->repo->getRevenueData('2026-01-01', '2026-01-31');

        expect($result['gross_revenue'])->toBe(500_000.0);
    });
});

describe('Edge Cases — getMitraPerformanceData', function () {
    it('tidak ada mitra → total_mitra 0, mitras kosong', function () {
        $result = $this->repo->getMitraPerformanceData('2026-01-01', '2026-01-31');

        expect($result['total_mitra'])->toBe(0)
            ->and($result['total_trip_completed'])->toBe(0)
            ->and($result['mitras'])->toBeEmpty();
    });

    it('mitra tanpa trip completed dalam range → total_trip_completed 0', function () {
        $mitra = User::factory()->mitra()->create();
        Trip::factory()->scheduled()->create([
            'mitra_id' => $mitra->id,
            'tanggal_mulai' => '2026-01-15',
        ]);

        $result = $this->repo->getMitraPerformanceData('2026-01-01', '2026-01-31');

        expect($result['total_trip_completed'])->toBe(0);
    });
});

/*
|==========================================================================
| KATEGORI 6 — TIMEZONE (Characterization Test)
|==========================================================================
|
|   CATATAN: getMonthlyRevenue pakai `strftime('%Y-%m', created_at)` di
| SQLite → group by UTC month. Booking 1 Feb 01:00 WITA (= 31 Jan 17:00 UTC)
| akan masuk Januari, bukan Februari.
|
| Test di bawah adalah CHARACTERIZATION TEST — memverifikasi perilaku
| saat ini (bug potensial). Jika di masa depan di-fix, test ini akan
| gagal → sinyal untuk update.
|
*/

describe('Timezone — getMonthlyRevenue (characterization)', function () {
    it('booking di batas bulan (31 Jan 23:59 UTC) masuk Januari', function () {
        app()->setLocale('en');

        Booking::factory()->confirmed()->create([
            'total_harga' => 500_000,
            'created_at' => '2026-01-31 23:59:59',
        ]);

        $result = $this->repo->getMonthlyRevenue();

        $jan = collect($result)->firstWhere('bulan', 'Jan');
        expect($jan['total'])->toBe(500_000.0);
    });

    it('booking 1 Feb 00:00 UTC masuk Februari', function () {
        app()->setLocale('en');

        // Freeze ke Feb 15 supaya bulan Feb ada di 6 bulan terakhir
        Carbon::setTestNow(Carbon::create(2026, 2, 15, 10, 0, 0, 'UTC'));

        Booking::factory()->confirmed()->create([
            'total_harga' => 500_000,
            'created_at' => '2026-02-01 00:00:00',
        ]);

        $result = $this->repo->getMonthlyRevenue();

        $feb = collect($result)->firstWhere('bulan', 'Feb');
        expect($feb['total'])->toBe(500_000.0);
    });

    it('booking 31 Jan 23:59 WITA (15:59 UTC) masuk Januari (UTC)', function () {
        app()->setLocale('en');

        // 31 Jan 23:59 WITA = 31 Jan 15:59 UTC → tetap Januari
        Booking::factory()->confirmed()->create([
            'total_harga' => 500_000,
            'created_at' => '2026-01-31 15:59:59',
        ]);

        $result = $this->repo->getMonthlyRevenue();

        $jan = collect($result)->firstWhere('bulan', 'Jan');
        expect($jan['total'])->toBe(500_000.0);
    });

    it('BUG POTENSIAL: booking 1 Feb 01:00 WITA (31 Jan 17:00 UTC) masuk Januari, bukan Februari', function () {
        app()->setLocale('en');

        // Freeze ke Feb 15 supaya bulan Feb ada di 6 bulan terakhir
        Carbon::setTestNow(Carbon::create(2026, 2, 15, 10, 0, 0, 'UTC'));

        // 1 Feb 01:00 WITA = 31 Jan 17:00 UTC
        // Karena group by UTC, ini masuk JANUARI (bukan Februari)
        Booking::factory()->confirmed()->create([
            'total_harga' => 500_000,
            'created_at' => '2026-01-31 17:00:00',  // = 1 Feb 01:00 WITA
        ]);

        $result = $this->repo->getMonthlyRevenue();

        $jan = collect($result)->firstWhere('bulan', 'Jan');
        $feb = collect($result)->firstWhere('bulan', 'Feb');

        // Karakterisasi: masuk Januari (bug potensial)
        // Jika di masa depan fix, test ini akan gagal → sinyal update
        expect($jan['total'])->toBe(500_000.0)
            ->and($feb['total'])->toBe(0.0);
    });
});
