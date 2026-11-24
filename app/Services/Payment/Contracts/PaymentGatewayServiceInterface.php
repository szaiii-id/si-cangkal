<?php

namespace App\Services\Payment\Contracts;

use App\Data\Doku\DokuChargeResult;
use App\Data\Doku\DokuNotification;
use App\Data\Doku\DokuRefundResult;
use App\Data\Doku\DokuReleaseResult;
use App\Data\Doku\DokuStatus;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Refund;

/**
 * Kontrak payment gateway. Adapter konkret (DOKU sekarang) bisa diganti
 * tanpa mengubah service di atasnya.
 */
interface PaymentGatewayServiceInterface
{
    public function createCharge(Booking $booking, int $dueMinutes = 60, int $sequence = 1): DokuChargeResult;

    public function checkStatus(Booking $booking): DokuStatus;

    public function release(Booking $booking): DokuReleaseResult;

    public function refund(Payment $payment, Refund $refund): DokuRefundResult;

    /**
     * @param  array<string, mixed>  $headers
     */
    public function verifyNotification(string $rawBody, array $headers, string $targetPath): DokuNotification;
}
