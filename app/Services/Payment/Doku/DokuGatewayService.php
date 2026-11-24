<?php

namespace App\Services\Payment\Doku;

use App\Data\Doku\DokuChargeResult;
use App\Data\Doku\DokuNotification;
use App\Data\Doku\DokuReleaseResult;
use App\Data\Doku\DokuStatus;
use App\Enums\PaymentGateway;
use App\Models\Booking;
use App\Models\MitraBankAccount;
use App\Models\Payment;
use App\Models\Refund;
use App\Services\Payment\Contracts\PaymentGatewayServiceInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use LogicException;
use RuntimeException;

/**
 * Adapter payment gateway untuk DOKU.
 *
 * Fase 1 memakai DOKU Checkout dengan skema tanda tangan simetris
 * HMAC-SHA256. Method selain createCharge/verifyNotification disiapkan
 * sebagai kontrak dan akan diisi pada task berikutnya.
 */
final class DokuGatewayService implements PaymentGatewayServiceInterface
{
    public function createCharge(Booking $booking, int $dueMinutes = 60, int $sequence = 1): DokuChargeResult
    {
        $bankAccount = $this->settlementAccountFor($booking);
        $requestId = DokuIdempotencyKey::forCharge($booking, $sequence);
        $timestamp = now()->utc()->format('Y-m-d\TH:i:s\Z');
        $checkoutPath = (string) config('payment.doku.checkout_path');
        $url = rtrim((string) config('payment.doku.base_url'), '/').$checkoutPath;

        $payload = [
            'order' => [
                'amount' => (int) $booking->total_harga,
                'invoice_number' => $booking->kode,
                'currency' => 'IDR',
                'callback_url' => route('client.bookings.show', $booking),
                'auto_redirect' => true,
            ],
            'payment' => [
                'payment_due_date' => $dueMinutes,
                'payment_method_types' => [
                    'QRIS',
                    'VIRTUAL_ACCOUNT_BCA',
                    'VIRTUAL_ACCOUNT_BANK_MANDIRI',
                    'VIRTUAL_ACCOUNT_BRI',
                    'VIRTUAL_ACCOUNT_BNI',
                    'EMONEY_DANA',
                    'EMONEY_OVO',
                    'EMONEY_SHOPEEPAY',
                ],
            ],
            'customer' => [
                'id' => (string) $booking->user_id,
                'email' => (string) $booking->client?->email,
            ],
            'additional_info' => [
                'hold_settlement' => true,
                'settlement' => [[
                    'bank_account_settlement_id' => (string) $bankAccount->settlement_bank_account_id,
                    'value' => 100,
                    'type' => 'PERCENTAGE',
                ]],
                'override_notification_url' => (string) config('payment.doku.notification_url'),
            ],
        ];

        $bodyJson = json_encode($payload, JSON_THROW_ON_ERROR);

        $response = Http::withHeaders([
            'Client-Id' => (string) config('payment.doku.client_id'),
            'Request-Id' => $requestId,
            'Request-Timestamp' => $timestamp,
            'Signature' => DokuSignature::forRequest(
                targetPath: $checkoutPath,
                body: $bodyJson,
                requestId: $requestId,
                timestamp: $timestamp,
            ),
        ])->withBody($bodyJson, 'application/json')->post($url);

        if (! $response->successful()) {
            throw new RuntimeException(
                'Gagal membuat charge DOKU: HTTP '.$response->status().' '.$response->body(),
            );
        }

        /** @var array<string, mixed> $body */
        $body = $response->json() ?? [];

        if (data_get($body, 'message.0') !== 'SUCCESS') {
            throw new RuntimeException(
                'DOKU menolak charge: '.implode(', ', array_map('strval', data_get($body, 'message', []))),
            );
        }

        $checkoutUrl = (string) data_get($body, 'response.payment.url', '');

        if ($checkoutUrl === '') {
            throw new RuntimeException('DOKU tidak mengembalikan URL checkout.');
        }

        $sessionId = data_get($body, 'response.order.session_id');

        return new DokuChargeResult(
            checkoutUrl: $checkoutUrl,
            requestId: $requestId,
            sessionId: is_scalar($sessionId) ? (string) $sessionId : null,
            payload: $body,
        );
    }

    public function checkStatus(Booking $booking): DokuStatus
    {
        throw new LogicException('Check Status DOKU diimplementasikan pada task berikutnya.');
    }

    public function release(Booking $booking): DokuReleaseResult
    {
        $payment = Payment::query()
            ->where('booking_id', $booking->id)
            ->where('gateway', PaymentGateway::DOKU)
            ->latest()
            ->first();

        if ($payment === null) {
            throw new RuntimeException('Tidak ada pembayaran DOKU untuk dilepas settlement-nya.');
        }

        $bankAccount = $this->settlementAccountFor($booking);
        $requestId = DokuIdempotencyKey::forRelease($booking);
        $timestamp = now()->utc()->format('Y-m-d\TH:i:s\Z');
        $releasePath = (string) config('payment.doku.release_path');
        $url = rtrim((string) config('payment.doku.base_url'), '/').$releasePath;

        $payload = [
            'order' => [
                'invoice_number' => $booking->kode,
                'amount' => (int) $booking->total_harga,
                'currency' => 'IDR',
            ],
            'transaction' => [
                'original_request_id' => (string) $payment->gateway_request_id,
            ],
            'override_settlement' => [[
                'bank_account_settlement_id' => (string) $bankAccount->settlement_bank_account_id,
                'value' => 100,
                'type' => 'PERCENTAGE',
            ]],
        ];

        $bodyJson = json_encode($payload, JSON_THROW_ON_ERROR);

        $response = Http::withHeaders([
            'Client-Id' => (string) config('payment.doku.client_id'),
            'Request-Id' => $requestId,
            'Request-Timestamp' => $timestamp,
            'Signature' => DokuSignature::forRequest(
                targetPath: $releasePath,
                body: $bodyJson,
                requestId: $requestId,
                timestamp: $timestamp,
            ),
        ])->withBody($bodyJson, 'application/json')->post($url);

        if (! $response->successful()) {
            throw new RuntimeException(
                'Gagal melepas settlement DOKU: HTTP '.$response->status().' '.$response->body(),
            );
        }

        /** @var array<string, mixed> $body */
        $body = $response->json() ?? [];
        $settlementDate = (string) data_get($body, 'transaction.settlement_date', '');

        if ($settlementDate === '') {
            throw new RuntimeException('DOKU tidak mengembalikan tanggal settlement.');
        }

        return new DokuReleaseResult(
            settlementDate: $settlementDate,
            settlementAmount: (float) data_get($body, 'transaction.settlement_amount', 0),
        );
    }

    public function refund(Payment $payment, Refund $refund): void
    {
        throw new LogicException('Refund DOKU diimplementasikan pada task berikutnya.');
    }

    public function verifyNotification(string $rawBody, array $headers, string $targetPath): DokuNotification
    {
        if (! DokuSignature::verifyNotification($rawBody, $headers, $targetPath)) {
            throw ValidationException::withMessages([
                'signature' => ['Signature notifikasi DOKU tidak valid.'],
            ]);
        }

        /** @var array<string, mixed> $payload */
        $payload = json_decode($rawBody, true) ?: [];

        return new DokuNotification(
            requestId: (string) ($headers['Request-Id'] ?? ''),
            invoiceNumber: (string) data_get($payload, 'order.invoice_number', ''),
            amount: (int) data_get($payload, 'order.amount', 0),
            status: (string) data_get($payload, 'transaction.status', ''),
            payload: $payload,
        );
    }

    private function settlementAccountFor(Booking $booking): MitraBankAccount
    {
        $mitra = $booking->mitra;

        if ($mitra === null) {
            throw new RuntimeException('Booking tidak memiliki mitra.');
        }

        $account = $mitra->bankAccounts()
            ->whereNotNull('settlement_bank_account_id')
            ->whereNotNull('settlement_verified_at')
            ->orderByDesc('is_primary')
            ->first();

        if ($account === null) {
            throw new RuntimeException(
                'Mitra belum memiliki rekening settlement DOKU yang terverifikasi.',
            );
        }

        return $account;
    }
}
