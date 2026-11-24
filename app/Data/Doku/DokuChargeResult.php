<?php

namespace App\Data\Doku;

/**
 * Hasil pembuatan DOKU Checkout.
 */
final readonly class DokuChargeResult
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $checkoutUrl,
        public string $requestId,
        public ?string $sessionId = null,
        public array $payload = [],
    ) {}
}
