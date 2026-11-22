<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Trip;
use App\Services\Package\PackageService;
use App\Services\Trip\TripService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

final class TripMonitoringController extends Controller
{
    public function __construct(
        private readonly TripService $tripService,
        private readonly PackageService $packageService,
    ) {}

    /**
     * Halaman Index Trip (Langkah 2 - Tabel Jadwal)
     */
    public function index(Request $request): InertiaResponse
    {
        $this->authorize('viewAny', Trip::class);

        $filters = [
            'search' => $request->input('search'),
            'status' => $request->input('status'),
            'package_id' => $request->input('package_id'),
            'mitra_id' => $request->input('mitra_id'),
            'tanggal_dari' => $request->input('tanggal_dari'),
            'tanggal_sampai' => $request->input('tanggal_sampai'),
            'sort' => $request->input('sort', 'tanggal_mulai'),
            'direction' => $request->input('direction', 'desc'),
        ];

        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);

        return Inertia::render('Admin/Trips/Index', [
            'trips' => $this->tripService->paginate($filters, $perPage),
            'filters' => $filters,
            'statusCounts' => $this->tripService->countByStatus(),
        ]);
    }

    /**
     * Halaman Overview (Langkah 1 - Daftar Paket)
     * Dipanggil melalui rute /admin/packages
     */
    public function overview(Request $request): InertiaResponse
    {
        $this->authorize('viewAny', Trip::class);

        $packageFilters = [
            'search' => $request->input('search'),
            'status' => 'aktif',
        ];

        return Inertia::render('Admin/Trips/Overview', [
            'packages' => $this->packageService->paginate($packageFilters, 15),
            'filters' => $packageFilters,
        ]);
    }

    public function show(Trip $trip): InertiaResponse
    {
        $this->authorize('view', $trip);
        $trip = $this->tripService->findDetail($trip->id);

        return Inertia::render('Admin/Trips/Show', [
            'trip' => $trip,
        ]);
    }
}
