<?php

namespace App\Data\Xendit;

/**
 * @param  array<string, mixed>  $payload
 */
final readonly class XenditRefundResult
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $refundId,
        public string $status,
        public array $payload,
    ) {}
}
