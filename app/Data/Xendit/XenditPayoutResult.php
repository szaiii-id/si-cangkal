<?php

namespace App\Data\Xendit;

/**
 * @param  array<string, mixed>  $payload
 */
final readonly class XenditPayoutResult
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $payoutId,
        public string $status,
        public array $payload,
    ) {}
}
