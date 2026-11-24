<?php

namespace App\Data\Doku;

/**
 * Notifikasi DOKU yang sudah terverifikasi signature-nya.
 */
final readonly class DokuNotification
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $requestId,
        public string $invoiceNumber,
        public int $amount,
        public string $status,
        public array $payload,
    ) {}

    public function isSuccess(): bool
    {
        return strtoupper($this->status) === 'SUCCESS';
    }

    public function isFailed(): bool
    {
        return strtoupper($this->status) === 'FAILED';
    }
}
