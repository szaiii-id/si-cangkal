<?php

namespace App\Enums;

/**
 * Sumber pembayaran sebuah transaksi.
 *
 * `manual` adalah alur lama: client transfer ke rekening mitra lalu
 * mitra memverifikasi bukti transfer. `doku` adalah alur gateway:
 * pembayaran diverifikasi lewat webhook DOKU dan dananya di-split
 * langsung ke rekening mitra.
 */
enum PaymentGateway: string
{
    case MANUAL = 'manual';
    case DOKU = 'doku';

    /**
     * Label untuk tampilan (Bahasa Indonesia).
     */
    public function label(): string
    {
        return match ($this) {
            self::MANUAL => 'Transfer Manual',
            self::DOKU => 'DOKU',
        };
    }

    public function isGateway(): bool
    {
        return $this === self::DOKU;
    }
}
