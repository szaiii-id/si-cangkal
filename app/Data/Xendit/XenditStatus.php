<?php

namespace App\Data\Xendit;

/**
 * @param  array<string, mixed>  $payload
 */
final readonly class XenditStatus
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $status,
        public array $payload,
    ) {}
}
