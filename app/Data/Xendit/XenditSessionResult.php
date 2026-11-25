<?php

namespace App\Data\Xendit;

/**
 * Hasil pembuatan Payment Session Xendit.
 *
 * @param  array<string, mixed>  $payload
 */
final readonly class XenditSessionResult
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $sessionId,
        public string $checkoutUrl,
        public array $payload,
        public ?string $paymentRequestId = null,
    ) {}
}
