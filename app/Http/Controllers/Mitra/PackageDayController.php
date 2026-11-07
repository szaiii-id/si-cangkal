<?php

namespace App\Http\Controllers\Mitra;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mitra\StorePackageDayRequest;
use App\Http\Requests\Mitra\UpdatePackageDayRequest;
use App\Models\Package;
use App\Models\PackageDay;
use App\Services\Package\PackageDayService;
use Illuminate\Http\RedirectResponse;

/**
 * Controller itinerary (hari) paket wisata untuk mitra.
 *
 * Perubahan dari versi lama:
 * - `abort_unless($package->mitra_id === auth()->id(), 403)` dan
 *   `abort_unless($day->package->mitra_id === auth()->id(), 403)`
 *   diganti `$this->authorize(...)` via `PackagePolicy` (S-02/S-03).
 * - Validasi inline -> Form Request (sudah dipakai sebelumnya,
 *   dipertahankan).
 */
final class PackageDayController extends Controller
{
    public function __construct(
        private readonly PackageDayService $packageDayService,
    ) {}

    public function store(StorePackageDayRequest $request, Package $package): RedirectResponse
    {
        $this->authorize('manageDays', $package);

        $this->packageDayService->create($package, $request->validated());

        return redirect()
            ->route('mitra.packages.show', $package->id)
            ->with('status', 'Hari berhasil ditambahkan.');
    }

    public function update(UpdatePackageDayRequest $request, PackageDay $day): RedirectResponse
    {
        $package = $day->package;

        abort_if($package === null, 404);

        $this->authorize('manageDays', $package);

        $this->packageDayService->update($day, $request->validated());

        return redirect()
            ->route('mitra.packages.show', $day->package_id)
            ->with('status', 'Hari berhasil diperbarui.');
    }

    public function destroy(PackageDay $day): RedirectResponse
    {
        $package = $day->package;

        abort_if($package === null, 404);

        $this->authorize('manageDays', $package);

        $packageId = $day->package_id;

        $this->packageDayService->delete($day);

        return redirect()
            ->route('mitra.packages.show', $packageId)
            ->with('status', 'Hari berhasil dihapus.');
    }
}