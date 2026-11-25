<?php

namespace App\Services\Payment;

use App\Data\Xendit\XenditSessionResult;
use App\Enums\PaymentGateway;
use App\Enums\PaymentMetode;
use App\Models\Booking;
use App\Models\Payment;
use App\Services\Payment\Contracts\XenditPaymentServiceInterface;

/**
 * Satu-satunya entry point pembuatan Payment Session Xendit.
 *
 * Menghindari session ganda untuk booking yang sama dan menyimpan jejak
 * pembayaran ke tabel payments.
 */
final class PaymentChargeService
{
    public function __construct(
        private readonly XenditPaymentServiceInterface $gateway,
        private readonly BookingPaymentLinkService $paymentLink,
    ) {}

    public function createForBooking(Booking $booking, ?int $dueMinutes = null): XenditSessionResult
    {
        $existing = Payment::query()
            ->where('booking_id', $booking->id)
            ->where('gateway', PaymentGateway::XENDIT)
            ->where('gateway_status', 'pending')
            ->whereNull('released_at')
            ->latest()
            ->first();

        if ($existing !== null) {
            $existingResult = $this->resultFromPayment($existing);

            if ($existingResult !== null) {
                $this->paymentLink->forBooking($booking, $existingResult);

                return $existingResult;
            }
        }

        $sequence = 1 + (int) Payment::query()
            ->where('booking_id', $booking->id)
            ->where('gateway', PaymentGateway::XENDIT)
            ->count();

        $result = $this->gateway->createSession($booking, $sequence);

        Payment::create([
            'booking_id' => $booking->id,
            'jumlah' => $booking->total_harga,
            'metode' => PaymentMetode::XENDIT,
            'gateway' => PaymentGateway::XENDIT,
            'gateway_order_id' => $booking->kode,
            'gateway_request_id' => $result->sessionId,
            'gateway_status' => 'pending',
            'gateway_payload' => [
                'payment_link_url' => $result->checkoutUrl,
                'payment_session_id' => $result->sessionId,
                'payment_request_id' => $result->paymentRequestId,
                ...$result->payload,
            ],
        ]);

        $this->paymentLink->forBooking($booking, $result);

        return $result;
    }

    private function resultFromPayment(Payment $payment): ?XenditSessionResult
    {
        $checkoutUrl = (string) data_get($payment->gateway_payload, 'payment_link_url', '');
        $sessionId = (string) ($payment->gateway_request_id ?? '');

        if ($checkoutUrl === '' || $sessionId === '') {
            return null;
        }

        $payload = is_array($payment->gateway_payload) ? $payment->gateway_payload : [];
        $paymentRequestId = data_get($payload, 'payment_request_id');

        return new XenditSessionResult(
            sessionId: $sessionId,
            checkoutUrl: $checkoutUrl,
            payload: $payload,
            paymentRequestId: is_string($paymentRequestId) && $paymentRequestId !== ''
                ? $paymentRequestId
                : null,
        );
    }
}
