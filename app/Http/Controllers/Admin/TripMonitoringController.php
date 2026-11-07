<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Trip;
use App\Services\Trip\TripService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Controller monitoring trip untuk admin (read-only).
 *
 * Perubahan dari versi lama:
 * - Query inline -> `TripService::paginate()` (SOC).
 * - Otorisasi eksplisit via `TripPolicy` (S-02/S-03).
 * - `show()` memakai `TripService::findDetail()` (eager-load lengkap
 *   termasuk `days.trip` untuk menghindari N+1 di `TripDayResource`).
 */
final class TripMonitoringController extends Controller
{
    public function __construct(
        private readonly TripService $tripService,
    ) {}

    public function index(Request $request): InertiaResponse
    {
        $this->authorize('viewAny', Trip::class);

        $filters = [
            'search' => $request->input('search'),
            'status' => $request->input('status'),
            'mitra_id' => $request->input('mitra_id'),
            'tanggal_dari' => $request->input('tanggal_dari'),
            'tanggal_sampai' => $request->input('tanggal_sampai'),
            'sort' => $request->input('sort', 'tanggal_mulai'),
            'direction' => $request->input('direction', 'desc'),
        ];

        $perPage = (int) $request->input('per_page', 15);

        return Inertia::render('Admin/Trips/Index', [
            'trips' => $this->tripService->paginate($filters, $perPage),
            'filters' => $filters,
            'statusCounts' => $this->tripService->countByStatus(),
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