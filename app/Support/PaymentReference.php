<?php

namespace App\Support;

use App\Models\Booking;

/**
 * Reference_id level atas untuk Payment Session Xendit.
 *
 * Satu booking bisa memiliki beberapa attempt checkout (sesi lama
 * kedaluwarsa, lalu dibuat lagi). reference_id harus unik per attempt
 * supaya webhook tidak bisa mengkredit Payment yang salah.
 */
final class PaymentReference
{
    public static function forAttempt(Booking $booking, int $sequence): string
    {
        return $booking->kode.'-'.$sequence;
    }
}
