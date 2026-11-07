<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMitraProfileCompleted
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->isMitra()) {
            // Cek apakah mitra sudah punya minimal 1 rekening
            if ($user->bankAccounts()->count() === 0) {
                
                // Izinkan akses ke route onboarding & logout
                if ($request->routeIs('mitra.onboarding.*') || $request->routeIs('logout')) {
                    return $next($request);
                }

                // Redirect ke form onboarding rekening
                return redirect()
                    ->route('mitra.onboarding.index')
                    ->with('error', 'Lengkapi data rekening Anda terlebih dahulu untuk mengakses fitur ini.');
            }
        }

        return $next($request);
    }
}