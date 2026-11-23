<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Services\Package\PackageService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class CatalogController extends Controller
{
    public function __construct(
        protected PackageService $packageService,
    ) {}

    /**
     * Halaman katalog paket wisata aktif.
     */
    public function index(Request $request): InertiaResponse
    {
        $filters = [
            'search' => $request->input('search'),
            'status' => 'aktif', // hanya paket aktif
            'kategori' => $request->input('kategori'),
            'sort' => $request->input('sort', 'nama'),
            'direction' => $request->input('direction', 'asc'),
        ];

        $perPage = min(max((int) $request->input('per_page', 12), 1), 100);
        $packages = $this->packageService->paginate($filters, $perPage);

        return Inertia::render('Client/Catalog/Index', [
            'packages' => $packages,
            'filters' => $filters,
        ]);
    }

    /**
     * Detail paket untuk client.
     *
     * Hanya paket dengan status aktif yang bisa dilihat.
     * Kalau paket tidak aktif → 404.
     *
     * Eager-load `mitra.profile` agar FE bisa menampilkan
     * nama usaha (travel), telepon (untuk tombol WA), dan alamat.
     */
    public function show(Package $package): InertiaResponse
    {
        // Guard: hanya paket aktif yang bisa dilihat client
        abort_unless($package->isAktif(), 404);

        $package->load([
            'mitra:id,name,email',
            'mitra.profile',
            'days',
            'facilities',
        ]);

        return Inertia::render('Client/Catalog/Show', [
            'package' => $package,
        ]);
    }
}
