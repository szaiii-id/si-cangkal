<?php

namespace App\Services\Report;

use App\Exports\MitraPerformanceExport;
use App\Exports\RevenueExport;
use App\Models\Trip;
use App\Repositories\Eloquent\ReportRepository;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Service pembuatan laporan (PDF / Excel).
 *
 * Perubahan dari versi lama:
 * - `exportTripManifest(Trip)` tidak lagi menerima `User` dan tidak
 *   melakukan otorisasi apa pun. Otorisasi dipindah ke
 *   `TripPolicy@downloadManifest` di controller (SOC, S-02/S-03).
 * - Eager-load `days.trip` ditambahkan (B-03) agar `TripDay` di view
 *   tidak memicu N+1 saat mengakses `jamMulai()`/`lokasi()` (bila
 *   view memakainya).
 */
class ReportService
{
    public function __construct(
        private readonly ReportRepository $reportRepository,
    ) {}

    // ==========================================
    // 1. PENDAPATAN
    // ==========================================

    /**
     * @return array<string, mixed>
     */
    public function getRevenueData(string $startDate, string $endDate): array
    {
        return $this->reportRepository->getRevenueData($startDate, $endDate);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getMonthlyRevenue(): array
    {
        return $this->reportRepository->getMonthlyRevenue();
    }

    public function exportRevenuePdf(string $startDate, string $endDate): HttpResponse
    {
        $data = $this->getRevenueData($startDate, $endDate);

        $pdf = Pdf::loadView('reports.revenue-pdf', [
            'data' => $data,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ])->setPaper('a4', 'landscape');

        return $pdf->stream('Laporan-Pendapatan-'.now()->format('Y-m-d').'.pdf');
    }

    public function exportRevenueExcel(string $startDate, string $endDate): HttpResponse
    {
        return Excel::download(
            new RevenueExport($startDate, $endDate),
            'Laporan-Pendapatan-'.now()->format('Y-m-d').'.xlsx',
        );
    }

    // ==========================================
    // 2. KINERJA MITRA
    // ==========================================

    /**
     * @return array<string, mixed>
     */
    public function getMitraPerformanceData(string $startDate, string $endDate): array
    {
        return $this->reportRepository->getMitraPerformanceData($startDate, $endDate);
    }

    public function exportMitraPdf(string $startDate, string $endDate): HttpResponse
    {
        $data = $this->getMitraPerformanceData($startDate, $endDate);

        $pdf = Pdf::loadView('reports.mitra-performance-pdf', [
            'data' => $data,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ])->setPaper('a4', 'portrait');

        return $pdf->stream('Laporan-Kinerja-Mitra-'.now()->format('Y-m-d').'.pdf');
    }

    public function exportMitraExcel(string $startDate, string $endDate): HttpResponse
    {
        return Excel::download(
            new MitraPerformanceExport($startDate, $endDate),
            'Laporan-Kinerja-Mitra-'.now()->format('Y-m-d').'.xlsx',
        );
    }

    // ==========================================
    // 3. MANIFEST TRIP
    // ==========================================

    /**
     * Ekspor manifest trip (PDF itinerary + daftar peserta).
     *
     * Otorisasi TIDAK dilakukan di sini. Controller wajib memanggil
     * `$this->authorize('downloadManifest', $trip)` sebelum method ini.
     *
     * `days.trip` di-eager-load karena view trip-manifest memakai
     * `$day->judul`, `$day->deskripsi` (kolom DB), bukan method
     * snapshot. Namun `days.trip` juga disertakan agar konsisten
     * dengan controller lain yang memakai `TripDayResource` (bila
     * view di masa depan berubah).
     */
    public function exportTripManifest(Trip $trip): HttpResponse
    {
        $trip->load([
            'booking.client',
            'booking.participants',
            'package',
            'mitra',
            'days.trip',
        ]);

        $pdf = Pdf::loadView('reports.trip-manifest', ['trip' => $trip])
            ->setPaper('a4', 'portrait');

        return $pdf->stream('Itinerary-dan-Peserta-'.$trip->kode.'.pdf');
    }
}
