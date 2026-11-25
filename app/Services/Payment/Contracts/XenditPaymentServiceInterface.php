<?php

namespace App\Services\Payment\Contracts;

use App\Data\Xendit\XenditPayoutResult;
use App\Data\Xendit\XenditRefundResult;
use App\Data\Xendit\XenditSessionResult;
use App\Data\Xendit\XenditStatus;
use App\Data\Xendit\XenditWebhookEvent;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Refund;

/**
 * Kontrak gateway Xendit.
 *
 * Hanya memakai fitur Test Mode yang tersedia tanpa KYC, XenPlatform,
 * maupun aktivasi channel: Payment Session, webhook, refund, dan payout.
 */
interface XenditPaymentServiceInterface
{
    public function createSession(Booking $booking, int $sequence = 1): XenditSessionResult;

    public function verifyWebhook(string $rawBody, ?string $callbackToken): XenditWebhookEvent;

    public function checkStatus(Booking $booking): XenditStatus;

    public function refund(Payment $payment, Refund $refund): XenditRefundResult;

    public function getRefund(string $refundId): XenditRefundResult;

    public function payout(Booking $booking, Payment $payment): XenditPayoutResult;

    public function getPayout(string $payoutId): XenditPayoutResult;
}
