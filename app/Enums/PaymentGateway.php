<?php

namespace App\Enums;

/**
 * Sumber pembayaran sebuah transaksi.
 *
 * `manual` adalah alur lama: client transfer ke rekening mitra lalu
 * mitra memverifikasi bukti transfer. `xendit` adalah alur gateway:
 * pembayaran diverifikasi lewat webhook Xendit dan dananya dikirim
 * ke rekening mitra lewat payout.
 */
enum PaymentGateway: string
{
    case MANUAL = 'manual';
    case XENDIT = 'xendit';

    /**
     * Label untuk tampilan (Bahasa Indonesia).
     */
    public function label(): string
    {
        return match ($this) {
            self::MANUAL => 'Transfer Manual',
            self::XENDIT => 'Xendit',
        };
    }

    public function isGateway(): bool
    {
        return $this === self::XENDIT;
    }
}
