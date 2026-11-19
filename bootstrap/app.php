<?php

use App\Http\Middleware\AuthThrottleResponse;
use App\Http\Middleware\CheckRole;
use App\Http\Middleware\EnsureMitraProfileCompleted;
use App\Http\Middleware\EnsurePasswordChanged;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
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
        // Trust proxy (ngrok, load balancer, dll)
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO
                | Request::HEADER_X_FORWARDED_AWS_ELB,
        );

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

        // Tangani Session Expired (419) dan Unauthorized (401) untuk request Inertia
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            $status = $response->getStatusCode();

            // Hanya tangani jika request berasal dari Inertia (XHR)
            if ($request->header('X-Inertia')) {
                if ($status === 419 || $status === 401) {
                    return redirect()
                        ->route('login')
                        ->with('error', 'Sesi Anda telah berakhir. Silakan login kembali.');
                }
            }

            return $response;
        });
    })->create();
