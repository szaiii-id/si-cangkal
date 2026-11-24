<?php

namespace App\Data\Doku;

/**
 * Hasil permintaan refund ke DOKU.
 */
final readonly class DokuRefundResult
{
    public function __construct(
        public bool $success,
        public string $status,
        public ?string $refundNo = null,
        public ?string $message = null,
    ) {}
}
