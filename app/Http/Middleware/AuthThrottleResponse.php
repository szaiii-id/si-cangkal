<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

final class AuthThrottleResponse
{
    public function handle(Request $request, Closure $next, string $limiterName): Response
    {
        $key = $this->resolveKey($request, $limiterName);
        $maxAttempts = $this->maxAttempts($limiterName);
        $decaySeconds = $this->decaySeconds($limiterName);

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $seconds = max(RateLimiter::availableIn($key), 1);
            $message = $this->buildErrorMessage($limiterName, $seconds);
            $field = $this->resolveFieldName($limiterName);

            if ($request->expectsJson() || $request->header('X-Inertia')) {
                throw ValidationException::withMessages([
                    $field => [$message],
                ])->status(429);
            }

            return back()
                ->withErrors([$field => $message])
                ->with('retry_after', $seconds);
        }

        RateLimiter::hit($key, $decaySeconds);

        return $next($request);
    }

    protected function resolveKey(Request $request, string $limiterName): string
    {
        $input = match ($limiterName) {
            'login' => $request->input('identitas'),
            'register', 'forgot-password', 'reset-password' => $request->input('email'),
            'upload-proof', 'e-ticket-download' => $request->user()->id ?? $request->ip(),
            default => '',
        };

        $normalized = match (true) {
            is_array($input), is_object($input) => (string) json_encode($input),
            is_string($input), is_numeric($input) => (string) $input,
            default => '',
        };

        return mb_strtolower($normalized).'|'.$request->ip();
    }

    protected function maxAttempts(string $limiterName): int
    {
        $override = config("auth-throttle.{$limiterName}.test_override");

        if (! empty($override)) {
            return (int) $override;
        }

        return (int) config("auth-throttle.{$limiterName}.max_attempts", 5);
    }

    protected function decaySeconds(string $limiterName): int
    {
        return (int) config("auth-throttle.{$limiterName}.decay_seconds", 60);
    }

    protected function resolveFieldName(string $limiterName): string
    {
        return match ($limiterName) {
            'login' => 'identitas',
            'register', 'forgot-password', 'reset-password' => 'email',
            'upload-proof' => 'payment_proof',
            'e-ticket-download' => 'eticket',
            default => 'email',
        };
    }

    protected function buildErrorMessage(string $limiterName, int $seconds): string
    {
        $minutes = (int) ceil($seconds / 60);
        $display = $minutes === 1 ? '1 menit' : "{$minutes} menit";

        return match ($limiterName) {
            'login' => "Terlalu banyak percobaan login. Coba lagi dalam {$display}.",
            'register' => "Terlalu banyak percobaan pendaftaran. Coba lagi dalam {$display}.",
            'forgot-password' => "Terlalu banyak permintaan reset password. Coba lagi dalam {$display}.",
            'reset-password' => "Terlalu banyak percobaan reset password. Coba lagi dalam {$display}.",
            'upload-proof' => "Terlalu banyak mengunggah bukti transfer. Coba lagi dalam {$display}.",
            'e-ticket-download' => "Terlalu banyak mengunduh E-Ticket. Coba lagi dalam {$display}.",
            default => "Terlalu banyak percobaan. Coba lagi dalam {$display}.",
        };
    }
}