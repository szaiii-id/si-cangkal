<?php

namespace App\Services\Payment\Doku;

use App\Models\Booking;
use App\Models\Refund;

/**
 * Request-Id deterministik untuk idempotensi DOKU.
 *
 * DOKU mengembalikan response yang sama dengan HTTP 409 bila request
 * dikirim ulang memakai Request-Id dan body yang identik. Kunci di sini
 * sengaja stabil per booking + purpose supaya retry aman, tetapi unik
 * untuk setiap transaksi dan tujuan.
 */
final class DokuIdempotencyKey
{
    public static function forCharge(Booking $booking): string
    {
        return self::make('charge', $booking->id);
    }

    public static function forRelease(Booking $booking): string
    {
        return self::make('release', $booking->id);
    }

    public static function forRefund(Refund $refund): string
    {
        return self::make('refund', $refund->id);
    }

    private static function make(string $purpose, int $id): string
    {
        return sprintf(
            'sicangkal-doku-%s-%d-%s',
            $purpose,
            $id,
            substr(sha1("{$purpose}:{$id}"), 0, 12),
        );
    }
}
