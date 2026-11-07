<?php

namespace App\Http\Controllers\Mitra;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mitra\StorePackageFacilityRequest;
use App\Http\Requests\Mitra\UpdatePackageFacilityRequest;
use App\Models\Package;
use App\Models\PackageFacility;
use App\Services\Package\PackageFacilityService;
use Illuminate\Http\RedirectResponse;

/**
 * Controller fasilitas include/exclude paket wisata untuk mitra.
 *
 * Perubahan dari versi lama:
 * - `abort_unless(...)` manual diganti `$this->authorize(...)`
 *   via `PackagePolicy` (S-02/S-03).
 * - Validasi inline -> Form Request (sudah dipakai sebelumnya,
 *   dipertahankan).
 */
final class PackageFacilityController extends Controller
{
    public function __construct(
        private readonly PackageFacilityService $packageFacilityService,
    ) {}

    public function store(StorePackageFacilityRequest $request, Package $package): RedirectResponse
    {
        $this->authorize('manageFacilities', $package);

        $this->packageFacilityService->create($package, $request->validated());

        return redirect()
            ->route('mitra.packages.show', $package->id)
            ->with('status', 'Fasilitas berhasil ditambahkan.');
    }

    public function update(UpdatePackageFacilityRequest $request, PackageFacility $facility): RedirectResponse
    {
        $package = $facility->package;

        abort_if($package === null, 404);

        $this->authorize('manageFacilities', $package);

        $this->packageFacilityService->update($facility, $request->validated());

        return redirect()
            ->route('mitra.packages.show', $facility->package_id)
            ->with('status', 'Fasilitas berhasil diperbarui.');
    }

    public function destroy(PackageFacility $facility): RedirectResponse
    {
        $package = $facility->package;

        abort_if($package === null, 404);

        $this->authorize('manageFacilities', $package);

        $packageId = $facility->package_id;

        $this->packageFacilityService->delete($facility);

        return redirect()
            ->route('mitra.packages.show', $packageId)
            ->with('status', 'Fasilitas berhasil dihapus.');
    }
}