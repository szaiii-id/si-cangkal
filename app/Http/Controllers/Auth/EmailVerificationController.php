<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\EmailVerificationRequest;
use App\Models\User;
use App\Services\Auth\AuthService;
use App\Services\Auth\EmailVerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Controller verifikasi email (PRD-AUTH-002).
 *
 * Sengaja dibuat tipis: seluruh logika bisnis didelegasikan ke
 * EmailVerificationService, sedangkan penentuan tujuan redirect
 * memakai AuthService (konsisten dengan alur login/registrasi).
 */
final class EmailVerificationController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly EmailVerificationService $verificationService,
    ) {}

    /**
     * Tampilkan halaman pemberitahuan verifikasi.
     */
    public function notice(Request $request): InertiaResponse|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect()
                ->to($this->authService->resolveDashboardUrl($user))
                ->with('status', 'Email Anda sudah terverifikasi.');
        }

        return Inertia::render('Auth/VerifyEmail', [
            'email' => $user->email,
        ]);
    }

    /**
     * Proses tautan verifikasi bertanda tangan.
     */
    public function verify(EmailVerificationRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect()
                ->to($this->authService->resolveDashboardUrl($user))
                ->with('status', 'Email Anda sudah terverifikasi.');
        }

        $this->verificationService->verify($user, (string) $request->route('hash'));

        return redirect()
            ->to($this->authService->resolveDashboardUrl($user))
            ->with('status', 'Email berhasil diverifikasi. Selamat datang!');
    }

    /**
     * Kirim ulang email verifikasi.
     */
    public function send(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect()
                ->to($this->authService->resolveDashboardUrl($user))
                ->with('status', 'Email Anda sudah terverifikasi.');
        }

        if (! $this->verificationService->send($user)) {
            return back()->with(
                'error',
                'Gagal mengirim email verifikasi. Silakan coba lagi beberapa saat lagi.',
            );
        }

        return back()->with(
            'status',
            'Tautan verifikasi baru telah dikirim ke '.$user->email.'.',
        );
    }
}
