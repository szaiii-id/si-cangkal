<?php

namespace App\Http\Controllers\Mitra;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mitra\UpdateBankAccountRequest;
use App\Http\Requests\Mitra\UpdateProfileRequest;
use App\Services\User\ProfileService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ProfileController extends Controller
{
    public function __construct(
        protected ProfileService $profileService,
    ) {}

    /**
     * Tampilkan halaman edit profile mitra.
     */
    public function edit(): InertiaResponse
    {
        // ✅ Eager load profile dan bankAccounts
        $user = auth()->user()->load(['profile', 'bankAccounts']);

        return Inertia::render('Mitra/Profile', [
            'profile' => $user->profile,
            'bankAccounts' => $user->bankAccounts, // Kirim ke FE untuk ditampilkan
        ]);
    }

    /**
     * Proses update profile mitra (termasuk upload logo).
     */
    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $this->profileService->updateProfile(
            user: $request->user(),
            data: $request->validated(),
        );

        return redirect()
            ->route('mitra.profile.edit')
            ->with('status', 'Profile berhasil diperbarui.');
    }

    /**
     * Proses update rekening bank mitra (memerlukan verifikasi password).
     */
    public function updateBankAccounts(UpdateBankAccountRequest $request): RedirectResponse
    {
        // Panggil service, bukan query langsung di controller
        $this->profileService->updateBankAccounts(
            mitra: $request->user(),
            accounts: $request->validated('accounts'),
        );

        return redirect()
            ->route('mitra.profile.edit')
            ->with('status', 'Data rekening berhasil diperbarui.');
    }
}