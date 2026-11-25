<?php

namespace App\Http\Controllers\Mitra;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mitra\StoreOnboardingRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

final class OnboardingController extends Controller
{
    public function index(Request $request): InertiaResponse|RedirectResponse
    {
        $mitra = $request->user();

        if ($mitra->bankAccounts()->count() > 0) {
            return redirect()->route('mitra.dashboard');
        }

        return Inertia::render('Mitra/Onboarding/Index', [
            'bankAccounts' => $mitra->bankAccounts,
        ]);
    }

    public function store(StoreOnboardingRequest $request): RedirectResponse
    {
        $mitra = $request->user();
        $validated = $request->validated();

        $mitra->profile()->updateOrCreate(
            ['user_id' => $mitra->id],
            [
                'tipe' => 'mitra',
                'nama_penyedia_usaha' => $validated['nama_penyedia_usaha'],
                'telepon' => $validated['no_hp'],
                'deskripsi' => $validated['deskripsi'] ?? null,
            ],
        );

        $mitra->bankAccounts()->delete();

        foreach ($validated['accounts'] as $index => $account) {
            $mitra->bankAccounts()->create([
                'tipe' => $account['tipe'],
                'nama_penyedia' => $account['nama_penyedia'],
                'nomor_rekening' => $account['nomor_rekening'],
                'nama_pemilik' => $account['nama_pemilik'],
                'is_primary' => $index === 0,
            ]);
        }

        return redirect()
            ->route('mitra.dashboard')
            ->with('status', 'Data rekening berhasil disimpan. Selamat datang!');
    }
}
