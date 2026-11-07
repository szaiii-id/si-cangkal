<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReportFilterRequest;
use App\Models\Booking;
use App\Models\Trip;
use App\Services\Report\ReportService;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Controller laporan untuk admin.
 *
 * Tanggung jawab:
 * - Menampilkan halaman laporan (revenue, kinerja mitra).
 * - Menyediakan export PDF/Excel.
 * - Menyediakan manifest trip (PDF itinerary + daftar peserta).
 *
 * Otorisasi:
 * - Semua method lewat `$this->authorize(...)` (S-02/S-03).
 * - `exportTripManifest` didelegasikan ke `TripPolicy@downloadManifest`.
 *
 * Validasi:
 * - Filter periode divalidasi lewat `ReportFilterRequest`
 *   (`start_date`, `end_date`). Tanpa validasi, input aneh akan
 *   menyebabkan error 500 di `Carbon::parse()` pada service.
 */
final class ReportController extends Controller
{
    public function __construct(
        private readonly ReportService $reportService,
    ) {}

    // ==========================================
    // 1. LAPORAN PENDAPATAN
    // ==========================================

    /**
     * Halaman laporan pendapatan.
     */
    public function index(ReportFilterRequest $request): InertiaResponse
    {
        $this->authorize('viewAny', Booking::class);

        $period = $request->period();

        return Inertia::render('Admin/Reports/Index', [
            'data' => $this->reportService->getRevenueData(
                $period['start_date'],
                $period['end_date'],
            ),
            'monthly_revenue' => $this->reportService->getMonthlyRevenue(),
            'filters' => $period,
        ]);
    }

    /**
     * Export laporan pendapatan ke PDF.
     */
    public function exportPdf(ReportFilterRequest $request): HttpResponse
    {
        $this->authorize('viewAny', Booking::class);

        $period = $request->period();

        return $this->reportService->exportRevenuePdf(
            $period['start_date'],
            $period['end_date'],
        );
    }

    /**
     * Export laporan pendapatan ke Excel.
     */
    public function exportExcel(ReportFilterRequest $request): HttpResponse
    {
        $this->authorize('viewAny', Booking::class);

        $period = $request->period();

        return $this->reportService->exportRevenueExcel(
            $period['start_date'],
            $period['end_date'],
        );
    }

    // ==========================================
    // 2. LAPORAN KINERJA MITRA
    // ==========================================

    /**
     * Halaman laporan kinerja mitra.
     */
    public function mitraPerformance(ReportFilterRequest $request): InertiaResponse
    {
        $this->authorize('viewAny', Booking::class);

        $period = $request->period();

        return Inertia::render('Admin/Reports/Mitra', [
            'data' => $this->reportService->getMitraPerformanceData(
                $period['start_date'],
                $period['end_date'],
            ),
            'filters' => $period,
        ]);
    }

    /**
     * Export laporan kinerja mitra ke PDF.
     */
    public function exportMitraPdf(ReportFilterRequest $request): HttpResponse
    {
        $this->authorize('viewAny', Booking::class);

        $period = $request->period();

        return $this->reportService->exportMitraPdf(
            $period['start_date'],
            $period['end_date'],
        );
    }

    /**
     * Export laporan kinerja mitra ke Excel.
     */
    public function exportMitraExcel(ReportFilterRequest $request): HttpResponse
    {
        $this->authorize('viewAny', Booking::class);

        $period = $request->period();

        return $this->reportService->exportMitraExcel(
            $period['start_date'],
            $period['end_date'],
        );
    }

    // ==========================================
    // 3. MANIFEST TRIP
    // ==========================================

    /**
     * Export manifest trip (PDF itinerary + daftar peserta).
     *
     * Otorisasi:
     * - `TripPolicy@downloadManifest` — admin boleh semua trip,
     *   mitra hanya trip miliknya.
     */
    public function exportTripManifest(Trip $trip): HttpResponse
    {
        $this->authorize('downloadManifest', $trip);

        return $this->reportService->exportTripManifest($trip);
    }
}