<?php

namespace App\Services\Payment;

use App\Data\Doku\DokuChargeResult;
use App\Enums\PaymentGateway;
use App\Enums\PaymentMetode;
use App\Models\Booking;
use App\Models\Payment;
use App\Services\Payment\Contracts\PaymentGatewayServiceInterface;

/**
 * Satu-satunya entry point pembuatan charge.
 *
 * Menghindari charge ganda untuk booking yang sama dan menyimpan jejak
 * pembayaran gateway ke tabel payments.
 */
final class PaymentChargeService
{
    public function __construct(
        private readonly PaymentGatewayServiceInterface $gateway,
    ) {}

    public function createForBooking(Booking $booking, ?int $dueMinutes = null): DokuChargeResult
    {
        $existing = Payment::query()
            ->where('booking_id', $booking->id)
            ->where('gateway', PaymentGateway::DOKU)
            ->where('gateway_status', 'pending')
            ->whereNull('released_at')
            ->latest()
            ->first();

        if ($existing !== null) {
            $existingResult = $this->resultFromPayment($existing);

            if ($existingResult !== null) {
                return $existingResult;
            }
        }

        $sequence = 1 + (int) Payment::query()
            ->where('booking_id', $booking->id)
            ->where('gateway', PaymentGateway::DOKU)
            ->count();

        $result = $this->gateway->createCharge(
            $booking,
            $dueMinutes ?? $this->defaultDueMinutes($booking),
            $sequence,
        );

        Payment::create([
            'booking_id' => $booking->id,
            'jumlah' => $booking->total_harga,
            'metode' => PaymentMetode::DOKU,
            'gateway' => PaymentGateway::DOKU,
            'gateway_order_id' => $booking->kode,
            'gateway_request_id' => $result->requestId,
            'gateway_status' => 'pending',
            'gateway_payload' => $result->payload,
        ]);

        return $result;
    }

    private function defaultDueMinutes(Booking $booking): int
    {
        if ($booking->expired_at === null) {
            return 60;
        }

        return max(5, (int) now()->diffInMinutes($booking->expired_at, false));
    }

    private function resultFromPayment(Payment $payment): ?DokuChargeResult
    {
        $payload = $payment->gateway_payload;

        if (! is_array($payload)) {
            return null;
        }

        $checkoutUrl = (string) data_get($payload, 'response.payment.url', '');
        $requestId = (string) ($payment->gateway_request_id ?? '');

        if ($checkoutUrl === '' || $requestId === '') {
            return null;
        }

        $sessionId = data_get($payload, 'response.order.session_id');

        return new DokuChargeResult(
            checkoutUrl: $checkoutUrl,
            requestId: $requestId,
            sessionId: is_scalar($sessionId) ? (string) $sessionId : null,
            payload: $payload,
        );
    }
}
