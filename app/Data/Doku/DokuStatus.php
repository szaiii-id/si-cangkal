<?php

namespace App\Data\Doku;

/**
 * Status transaksi hasil Check Status API DOKU.
 */
final readonly class DokuStatus
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $status,
        public ?string $transactionId = null,
        public array $payload = [],
    ) {}

    public function isSuccess(): bool
    {
        return strtoupper($this->status) === 'SUCCESS';
    }
}
