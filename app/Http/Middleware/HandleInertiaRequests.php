<?php

namespace App\Http\Middleware;

use App\Models\AppNotification;
use App\Support\Timezone;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * Nama cookie yang menyimpan timezone viewer (di-set dari client-side).
     */
    public const VIEWER_TZ_COOKIE = 'viewer_timezone';

    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'username' => $user->username,
                    'email' => $user->email,
                    'role' => $user->role,
                    'timezone' => $user->timezone,
                    // ✅ Boolean flag — jangan expose token asli!
                    'has_fcm_token' => (bool) $user->fcm_token,
                ] : null,
            ],
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
                'error' => fn () => $request->session()->get('error'),
                'retry_after' => fn () => $request->session()->get('retry_after'),
                'credentials' => fn () => $request->session()->get('credentials'),
            ],
            // ==========================================
            // TIMEZONE
            // ==========================================
            'viewer_timezone' => fn (): string => $this->resolveViewerTimezone($request),
            'timezone_options' => fn (): array => Timezone::options(),
            'server_timezone' => fn (): mixed => config('app.timezone'),

            'unread_notifications' => fn (): int => $user !== null
                ? AppNotification::where('user_id', $user->id)->whereNull('read_at')->count()
                : 0,
        ];
    }

    protected function resolveViewerTimezone(Request $request): string
    {
        $cookieTz = $request->cookie(self::VIEWER_TZ_COOKIE);
        if (is_string($cookieTz) && Timezone::isValid($cookieTz)) {
            return $cookieTz;
        }

        $user = $request->user();
        if ($user !== null && Timezone::isValid($user->timezone)) {
            return (string) $user->timezone;
        }

        return Timezone::DEFAULT;
    }
}