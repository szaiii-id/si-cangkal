<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

final class TrustProxies extends Middleware
{
    /**
     * The headers that should be used to detect proxies.
     *
     * @var int
     */
    protected $headers =
        Request::HEADER_X_FORWARDED_FOR |
        Request::HEADER_X_FORWARDED_HOST |
        Request::HEADER_X_FORWARDED_PORT |
        Request::HEADER_X_FORWARDED_PROTO |
        Request::HEADER_X_FORWARDED_AWS_ELB;

    /**
     * Get the trusted proxies for the application.
     *
     * @return array<int, string>|string|null
     */
    protected function proxies(): array|string|null
    {
        if (! app()->isProduction()) {
            return '*';
        }

        /** @var string|null $configured */
        $configured = config('app.trusted_proxies');

        if (is_string($configured) && trim($configured) !== '') {
            return $configured === '*'
                ? '*'
                : array_map('trim', explode(',', $configured));
        }

        // Default aman di cPanel/shared hosting: percayai IP proxy upstream langsung
        $remoteAddr = request()->server->get('REMOTE_ADDR');

        return is_string($remoteAddr) && $remoteAddr !== ''
            ? [$remoteAddr]
            : null;
    }
}
