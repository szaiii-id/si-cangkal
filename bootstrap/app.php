<?php

use App\Http\Middleware\AuthThrottleResponse;
use App\Http\Middleware\CheckRole;
use App\Http\Middleware\EnsureMitraProfileCompleted;
use App\Http\Middleware\EnsurePasswordChanged;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\TrustProxies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            if (app()->environment('testing')) {
                Route::middleware('web')
                    ->group(base_path('routes/web-testing.php'));
            }
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(TrustProxies::class);

        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Testing-only: bypass CSRF untuk route /_test/*
        // Aman karena routes/web-testing.php hanya di-load saat APP_ENV=testing.
        // Tidak berpengaruh di production (route _test/* tidak pernah ada).
        $middleware->validateCsrfTokens(except: [
            '_test/*',
        ]);

        $middleware->alias([
            'role' => CheckRole::class,
            'auth.throttle' => AuthThrottleResponse::class,
            'password.changed' => EnsurePasswordChanged::class,
            'mitra.complete' => EnsureMitraProfileCompleted::class,
        ]);

        // Redirect user yang sudah login ke dashboard yang sesuai
        $middleware->redirectUsersTo(function (Request $request) {
            $user = $request->user();

            // User belum terverifikasi selalu diarahkan ke halaman verifikasi
            // (PRD-AUTH-002 §5), termasuk saat membuka route `guest`.
            if ($user !== null && ! $user->hasVerifiedEmail()) {
                return '/verify-email';
            }

            if ($user?->role === 'admin') {
                return '/admin/dashboard';
            }

            if ($user?->role === 'mitra') {
                return '/mitra/dashboard';
            }

            return '/client/dashboard';
        });

    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Tangani Session Expired (419), Unauthorized (401), dan halaman Error Inertia
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            $status = $response->getStatusCode();

            // 1. Tangani jika request berasal dari Inertia (XHR) saat sesi habis
            if ($request->header('X-Inertia')) {
                if ($status === 419 || $status === 401) {
                    return redirect()
                        ->route('login')
                        ->with('error', 'Sesi Anda telah berakhir. Silakan login kembali.');
                }
            }

            // 2. Render halaman Error.svelte untuk kunjungan halaman biasa (non-JSON, non-local)
            if (
                ! app()->environment('local') &&
                ! $request->is('api/*') &&
                ! $request->expectsJson() &&
                in_array($status, [403, 404, 419, 429, 500, 503], true)
            ) {
                // PRD-AUTH-002 §6.3 / TC-06: header Retry-After dari
                // ThrottleRequestsException harus tetap ada meskipun
                // response diganti menjadi halaman Inertia.
                $retryAfter = $response->headers->get('Retry-After');

                if ($retryAfter === null && $e instanceof ThrottleRequestsException) {
                    $retryAfter = $e->getHeaders()['Retry-After'] ?? null;
                }

                $errorResponse = Inertia::render('Error', [
                    'status' => $status,
                    'retry_after' => $retryAfter !== null ? (int) $retryAfter : null,
                ])
                    ->toResponse($request)
                    ->setStatusCode($status);

                if ($retryAfter !== null) {
                    $errorResponse->headers->set('Retry-After', (string) $retryAfter);
                }

                return $errorResponse;
            }

            return $response;
        });
    })->create();
