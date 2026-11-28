<?php

namespace App\Http\Controllers\Mitra;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mitra\StorePackageRequest;
use App\Http\Requests\Mitra\UpdatePackageRequest;
use App\Models\Package;
use App\Services\Package\PackageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Throwable;

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

        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);

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
            ->route('mitra.packages.complete-phase2', $package->id)
            ->with('status', 'Paket dasar berhasil dibuat. Silakan lengkapi Itinerary dan Fasilitas.');
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

    public function completePhase2(Package $package): InertiaResponse
    {
        $this->authorize('update', $package);

        $package->load(['days', 'facilities']);

        return Inertia::render('Mitra/Packages/CompletePhase2', [
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
                ->with('status', 'Paket, Rencana Perjalanan, dan Fasilitas berhasil diperbarui.');
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (Throwable $e) {
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

    /**
     * Ikuti redirect tautan pendek Google Maps supaya frontend bisa membaca
     * koordinat dari URL akhir. Tanpa API key dan tanpa panggilan geocoding.
     */
    public function resolveLocation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'url' => ['required', 'string', 'url', 'max:2048'],
        ]);

        $url = $validated['url'];
        $allowed = [
            'maps.app.goo.gl',
            'goo.gl',
            'google.com',
            'www.google.com',
            'maps.google.com',
        ];

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        abort_unless(in_array($host, $allowed, true), 422, 'Tautan bukan Google Maps.');

        $finalUrl = Cache::remember(
            'google-maps:'.hash('sha256', $url),
            now()->addDay(),
            function () use ($url, $allowed): ?string {
                try {
                    $response = Http::timeout(8)
                        ->withOptions(['allow_redirects' => true])
                        ->get($url);

                    if (! $response->successful()) {
                        return null;
                    }

                    $effective = (string) $response->effectiveUri();
                    $effectiveHost = strtolower((string) parse_url($effective, PHP_URL_HOST));

                    return in_array($effectiveHost, $allowed, true) ? $effective : null;
                } catch (Throwable) {
                    return null;
                }
            },
        );

        return response()->json([
            'url' => $finalUrl,
            'message' => $finalUrl === null
                ? 'Tautan tidak dapat dibaca. Coba salin ulang dari Google Maps.'
                : null,
        ]);
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
