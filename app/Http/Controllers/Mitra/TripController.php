<?php

namespace App\Http\Controllers\Mitra;

use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Controller;
use App\Models\Trip;
use App\Services\Trip\TripService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

final class TripController extends Controller
{
    public function __construct(
        private readonly TripService $tripService,
    ) {}

    public function index(Request $request): InertiaResponse
    {
        $this->authorize('viewAny', Trip::class);

        $mitra = $request->user();

        $filters = [
            'search' => $request->input('search'),
            'status' => $request->input('status'),
            'sort' => $request->input('sort', 'tanggal_mulai'),
            'direction' => $request->input('direction', 'desc'),
        ];

        $perPage = min(max((int) $request->input('per_page', 10), 1), 100);

        return Inertia::render('Mitra/Trips/Index', [
            'trips' => $this->tripService->paginateForMitra($mitra, $filters, $perPage),
            'filters' => $filters,
            'statusCounts' => $this->tripService->countByStatusForMitra($mitra),
        ]);
    }

    public function show(Trip $trip): InertiaResponse
    {
        $this->authorize('view', $trip);

        $trip->load([
            'package:id,nama,kode,slug,timezone,cover_image',
            'booking.client:id,name,email',
            'days.trip',
        ]);

        return Inertia::render('Mitra/Trips/Show', [
            'trip' => $trip,
        ]);
    }

    /**
     * Cetak manifest trip (PDF).
     * Menggunakan fungsi export dari ReportController Admin
     * agar tidak perlu duplikasi logika PDF.
     */
    public function manifest(
        Trip $trip,
        ReportController $reportController
    ): HttpResponse {
        $this->authorize('view', $trip);

        return $reportController->exportTripManifest($trip);
    }
}
