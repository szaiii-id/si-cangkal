<?php

namespace App\Data\Doku;

/**
 * Hasil release settlement DOKU.
 */
final readonly class DokuReleaseResult
{
    public function __construct(
        public string $settlementDate,
        public float $settlementAmount,
    ) {}
}
