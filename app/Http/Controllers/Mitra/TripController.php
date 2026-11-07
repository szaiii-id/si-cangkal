<?php

namespace App\Http\Controllers\Mitra;

use App\Http\Controllers\Controller;
use App\Models\Trip;
use App\Services\Trip\TripService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Controller trip untuk mitra.
 *
 * Perubahan dari versi lama:
 * - `abort_unless($trip->mitra_id === $request->user()->id, 403)`
 *   diganti `$this->authorize('view', $trip)` (S-02/S-03).
 * - `show()` menambahkan eager-load `days.trip` (B-03) — wajib
 *   agar `TripDayResource` tidak memicu N+1 saat memanggil
 *   `jamMulai()`, `isToday()`, dsb.
 * - `index()` memanggil `$this->authorize('viewAny', Trip::class)`
 *   untuk konsistensi (walaupun middleware role:mitra sudah
 *   menjaga, Policy adalah defense in depth).
 */
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

        $perPage = (int) $request->input('per_page', 10);

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
}