<?php

namespace App\Http\Controllers\Mitra;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mitra\StorePackageRequest;
use App\Http\Requests\Mitra\UpdatePackageRequest;
use App\Models\Package;
use App\Services\Package\PackageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Controller paket wisata untuk mitra.
 *
 * Perubahan dari versi lama:
 * - Semua `abort_unless($package->mitra_id === auth()->id(), 403)`
 *   diganti `$this->authorize(...)` via `PackagePolicy` (S-02/S-03).
 * - `update()` memakai `PackageService::updateWithRelations()` —
 *   logika sinkronisasi JSON days/facilities dipindah dari controller
 *   (SOC/NF-03). Controller turun dari ~70 baris menjadi ~15 baris.
 * - `store()` memakai `StorePackageRequest` (sudah dipakai sebelumnya).
 * - `update()` memakai `UpdatePackageRequest` (sudah ada).
 * - `index()` memanggil `$this->authorize('viewAny', Package::class)`
 *   untuk konsistensi.
 */
final class PackageController extends Controller
{
    public function __construct(
        private readonly PackageService $packageService,
    ) {}

    public function index(Request $request): InertiaResponse
    {
        $this->authorize('viewAny', Package::class);

        $filters = [
            'search' => $request->input('search'),
            'status' => $request->input('status'),
            'mitra_id' => $request->user()->id,
        ];

        $perPage = (int) $request->input('per_page', 15);

        return Inertia::render('Mitra/Packages/Index', [
            'packages' => $this->packageService->paginate($filters, $perPage),
            'filters' => $filters,
            'statusCounts' => $this->packageService->countByStatus(),
        ]);
    }

    public function create(): InertiaResponse
    {
        $this->authorize('viewAny', Package::class);

        return Inertia::render('Mitra/Packages/Create');
    }

    public function store(StorePackageRequest $request): RedirectResponse
    {
        $package = $this->packageService->create(
            $request->validated(),
            $request->user(),
        );

        return redirect()
            ->route('mitra.packages.edit', $package->id)
            ->with('status', 'Paket dasar berhasil dibuat. Silakan lengkapi Itinerary dan Fasilitas di bawah ini.');
    }

    public function show(Package $package): InertiaResponse
    {
        $this->authorize('view', $package);

        $package->load(['days', 'facilities']);

        return Inertia::render('Mitra/Packages/Show', [
            'package' => $package,
        ]);
    }

    public function edit(Package $package): InertiaResponse
    {
        $this->authorize('update', $package);

        $package->load(['days', 'facilities']);

        return Inertia::render('Mitra/Packages/Edit', [
            'package' => $package,
        ]);
    }

    public function update(UpdatePackageRequest $request, Package $package): RedirectResponse
    {
        $this->authorize('update', $package);

        try {
            $this->packageService->updateWithRelations($package, $request->validated());

            return redirect()
                ->route('mitra.packages.show', $package->id)
                ->with('status', 'Paket, Rencana Perjalanan, dan Fasilitas berhasil diperbarui!');
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (\Throwable $e) {
            return back()
                ->withErrors(['error' => 'Gagal memperbarui paket: '.$e->getMessage()])
                ->withInput();
        }
    }

    public function destroy(Package $package): RedirectResponse
    {
        $this->authorize('delete', $package);

        try {
            $this->packageService->delete($package);

            return redirect()
                ->route('mitra.packages.index')
                ->with('status', 'Paket berhasil dihapus.');
        } catch (ValidationException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function publish(Package $package): RedirectResponse
    {
        $this->authorize('publish', $package);

        $this->packageService->publish($package);

        return redirect()
            ->route('mitra.packages.show', $package->id)
            ->with('status', 'Paket berhasil dipublish.');
    }

    public function archive(Package $package): RedirectResponse
    {
        $this->authorize('archive', $package);

        $this->packageService->archive($package);

        return redirect()
            ->route('mitra.packages.show', $package->id)
            ->with('status', 'Paket berhasil diarsipkan.');
    }

    public function restore(Package $package): RedirectResponse
    {
        $this->authorize('restore', $package);

        $this->packageService->restore($package);

        return redirect()
            ->route('mitra.packages.show', $package->id)
            ->with('status', 'Paket berhasil direstore.');
    }
}