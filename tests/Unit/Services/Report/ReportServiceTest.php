<?php

use App\Exports\MitraPerformanceExport;
use App\Exports\RevenueExport;
use App\Models\Trip;
use App\Repositories\Eloquent\ReportRepository;
use App\Services\Report\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfService;
use Illuminate\Http\Response as IlluminateResponse;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/*
|==========================================================================
| SETUP — Unit Test (NO DB)
|==========================================================================
*/

beforeEach(function () {
    $this->reportRepo = Mockery::mock(ReportRepository::class);
    $this->service = new ReportService($this->reportRepo);

    Log::shouldReceive('info')->byDefault()->andReturnNull();
    Log::shouldReceive('warning')->byDefault()->andReturnNull();
    Log::shouldReceive('error')->byDefault()->andReturnNull();
});

afterEach(function () {
    Mockery::close();
});

/*
|==========================================================================
| KATEGORI 1 — PASSTHROUGH
|==========================================================================
*/

describe('Passthrough — getRevenueData', function () {
    it('meneruskan startDate + endDate ke repository', function () {
        $expected = [
            'gross_revenue' => 1_000_000,
            'total_refund' => 200_000,
            'total_revenue' => 800_000,
            'transactions' => [],
        ];

        $this->reportRepo->shouldReceive('getRevenueData')
            ->once()
            ->with('2026-01-01', '2026-01-31')
            ->andReturn($expected);

        expect($this->service->getRevenueData('2026-01-01', '2026-01-31'))->toBe($expected);
    });
});

describe('Passthrough — getMonthlyRevenue', function () {
    it('meneruskan ke repository', function () {
        $expected = [
            ['bulan' => 'Jan', 'total' => 1_000_000],
            ['bulan' => 'Feb', 'total' => 2_000_000],
        ];

        $this->reportRepo->shouldReceive('getMonthlyRevenue')->once()->andReturn($expected);

        expect($this->service->getMonthlyRevenue())->toBe($expected);
    });
});

describe('Passthrough — getMitraPerformanceData', function () {
    it('meneruskan startDate + endDate ke repository', function () {
        $expected = [
            'total_mitra' => 10,
            'total_trip_completed' => 50,
            'mitras' => [],
        ];

        $this->reportRepo->shouldReceive('getMitraPerformanceData')
            ->once()
            ->with('2026-01-01', '2026-01-31')
            ->andReturn($expected);

        expect($this->service->getMitraPerformanceData('2026-01-01', '2026-01-31'))->toBe($expected);
    });
});

/*
|==========================================================================
| KATEGORI 2 — EXPORT PDF
|==========================================================================
|
|   Catatan: `Barryvdh\DomPDF\PDF::stream()` return type =
| Illuminate\Http\Response (bukan Symfony). Test pakai Illuminate.
|
*/

describe('exportRevenuePdf', function () {
    it('loadView + setPaper a4 landscape + stream', function () {
        $data = ['total_revenue' => 1_000_000];

        $this->reportRepo->shouldReceive('getRevenueData')->once()->andReturn($data);

        $response = new IlluminateResponse('pdf-content');

        $pdfMock = Mockery::mock(PdfService::class);
        $pdfMock->shouldReceive('setPaper')->once()->with('a4', 'landscape')->andReturnSelf();
        $pdfMock->shouldReceive('stream')
            ->once()
            ->with(Mockery::on(fn ($name) => str_starts_with($name, 'Laporan-Pendapatan-')))
            ->andReturn($response);

        Pdf::shouldReceive('loadView')
            ->once()
            ->with('reports.revenue-pdf', Mockery::on(fn ($d) => $d['data'] === $data))
            ->andReturn($pdfMock);

        expect($this->service->exportRevenuePdf('2026-01-01', '2026-01-31'))->toBe($response);
    });
});

describe('exportMitraPdf', function () {
    it('loadView mitra-performance-pdf + setPaper a4 portrait', function () {
        $data = ['total_mitra' => 10];

        $this->reportRepo->shouldReceive('getMitraPerformanceData')->once()->andReturn($data);

        $response = new IlluminateResponse('pdf-content');

        $pdfMock = Mockery::mock(PdfService::class);
        $pdfMock->shouldReceive('setPaper')->once()->with('a4', 'portrait')->andReturnSelf();
        $pdfMock->shouldReceive('stream')
            ->once()
            ->with(Mockery::on(fn ($name) => str_starts_with($name, 'Laporan-Kinerja-Mitra-')))
            ->andReturn($response);

        Pdf::shouldReceive('loadView')
            ->once()
            ->with('reports.mitra-performance-pdf', Mockery::type('array'))
            ->andReturn($pdfMock);

        expect($this->service->exportMitraPdf('2026-01-01', '2026-01-31'))->toBe($response);
    });
});

/*
|==========================================================================
| KATEGORI 3 — EXPORT EXCEL
|==========================================================================
*/

describe('exportRevenueExcel', function () {
    it('Excel::download dengan RevenueExport', function () {
        $response = Mockery::mock(BinaryFileResponse::class);

        Excel::shouldReceive('download')
            ->once()
            ->with(
                Mockery::type(RevenueExport::class),
                Mockery::on(fn ($name) => str_starts_with($name, 'Laporan-Pendapatan-')
                    && str_ends_with($name, '.xlsx')),
            )
            ->andReturn($response);

        expect($this->service->exportRevenueExcel('2026-01-01', '2026-01-31'))->toBe($response);
    });
});

describe('exportMitraExcel', function () {
    it('Excel::download dengan MitraPerformanceExport', function () {
        $response = Mockery::mock(BinaryFileResponse::class);

        Excel::shouldReceive('download')
            ->once()
            ->with(
                Mockery::type(MitraPerformanceExport::class),
                Mockery::on(fn ($name) => str_starts_with($name, 'Laporan-Kinerja-Mitra-')),
            )
            ->andReturn($response);

        expect($this->service->exportMitraExcel('2026-01-01', '2026-01-31'))->toBe($response);
    });
});

/*
|==========================================================================
| KATEGORI 4 — EXPORT TRIP MANIFEST
|==========================================================================
*/

describe('exportTripManifest', function () {
    it('$trip->load relasi + Pdf::loadView + stream dengan kode trip', function () {
        $trip = Mockery::mock(Trip::class)->makePartial();
        $trip->forceFill(['id' => 1, 'kode' => 'TRP-2026-001']);

        $trip->shouldReceive('load')
            ->once()
            ->with([
                'booking.client',
                'booking.participants',
                'package',
                'mitra',
                'days.trip',
            ])
            ->andReturnSelf();

        $response = new IlluminateResponse('pdf-content');

        $pdfMock = Mockery::mock(PdfService::class);
        $pdfMock->shouldReceive('setPaper')->once()->with('a4', 'portrait')->andReturnSelf();
        $pdfMock->shouldReceive('stream')
            ->once()
            ->with('Itinerary-dan-Peserta-TRP-2026-001.pdf')
            ->andReturn($response);

        Pdf::shouldReceive('loadView')
            ->once()
            ->with('reports.trip-manifest', Mockery::on(fn ($d) => $d['trip'] === $trip))
            ->andReturn($pdfMock);

        expect($this->service->exportTripManifest($trip))->toBe($response);
    });

    it('tidak ada otorisasi di service (SOC)', function () {
        $trip = Mockery::mock(Trip::class)->makePartial();
        $trip->forceFill(['id' => 1, 'kode' => 'TRP-2026-001']);
        $trip->shouldReceive('load')->andReturnSelf();

        $response = new IlluminateResponse('pdf');

        $pdfMock = Mockery::mock(PdfService::class);
        $pdfMock->shouldReceive('setPaper')->andReturnSelf();
        $pdfMock->shouldReceive('stream')->andReturn($response);

        Pdf::shouldReceive('loadView')->andReturn($pdfMock);

        expect($this->service->exportTripManifest($trip))->toBe($response);
    });
});
