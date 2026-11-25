<?php

namespace App\Services\Payment\Xendit;

use App\Data\Xendit\XenditPayoutResult;
use App\Data\Xendit\XenditRefundResult;
use App\Data\Xendit\XenditSessionResult;
use App\Data\Xendit\XenditStatus;
use App\Data\Xendit\XenditWebhookEvent;
use App\Enums\RefundReason;
use App\Models\Booking;
use App\Models\MitraBankAccount;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use App\Services\Payment\Contracts\XenditPaymentServiceInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Adapter Xendit Test Mode.
 *
 * Semua request memakai secret key dari config dan tidak pernah memakai
 * header XenPlatform (for-user-id / with-split-rule).
 */
final class XenditGatewayService implements XenditPaymentServiceInterface
{
    private const BANK_CHANNELS = [
        'BCA' => 'ID_BCA',
        'BRI' => 'ID_BRI',
        'BNI' => 'ID_BNI',
        'MANDIRI' => 'ID_MANDIRI',
        'PERMATA' => 'ID_PERMATA',
        'CIMB' => 'ID_CIMB',
        'BSI' => 'ID_BSI',
    ];

    public function createSession(Booking $booking, int $sequence = 1): XenditSessionResult
    {
        $client = $booking->client;
        $nama = trim((string) ($client->name ?? 'Client'));
        $parts = explode(' ', $nama, 2);

        $payload = $this->request()
            ->post($this->url('/sessions'), [
                'reference_id' => $booking->kode,
                'session_type' => 'PAY',
                'mode' => 'PAYMENT_LINK',
                'amount' => (int) $booking->total_harga,
                'currency' => 'IDR',
                'country' => 'ID',
                'customer' => [
                    'type' => 'INDIVIDUAL',
                    'reference_id' => 'client-'.$client->id,
                    'email' => $client->email,
                    'individual_detail' => [
                        'given_names' => $parts[0] !== '' ? $parts[0] : 'Client',
                        'surname' => $parts[1] ?? null,
                    ],
                ],
                'description' => 'Pembayaran booking '.$booking->kode,
                'success_return_url' => route('client.bookings.show', $booking),
                'cancel_return_url' => route('client.bookings.show', $booking),
                'locale' => 'id',
                'metadata' => [
                    'booking_id' => $booking->id,
                    'booking_kode' => $booking->kode,
                    'sequence' => $sequence,
                ],
            ]);

        $payload->throw();

        /** @var array<string, mixed> $body */
        $body = $payload->json();

        return new XenditSessionResult(
            sessionId: (string) ($body['payment_session_id'] ?? ''),
            checkoutUrl: (string) ($body['payment_link_url'] ?? ''),
            payload: $body,
            paymentRequestId: isset($body['payment_request_id'])
                ? (string) $body['payment_request_id']
                : null,
        );
    }

    public function verifyWebhook(string $rawBody, ?string $callbackToken): XenditWebhookEvent
    {
        $expected = (string) config('payment.xendit.callback_token');

        if ($expected === '' || $callbackToken === null || ! hash_equals($expected, $callbackToken)) {
            throw ValidationException::withMessages([
                'callback_token' => ['Callback token Xendit tidak valid.'],
            ]);
        }

        try {
            /** @var array<string, mixed> $body */
            $body = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw ValidationException::withMessages([
                'payload' => ['Body webhook Xendit bukan JSON yang valid.'],
            ]);
        }

        /** @var array<string, mixed> $data */
        $data = is_array($body['data'] ?? null) ? $body['data'] : [];

        return new XenditWebhookEvent(
            event: (string) ($body['event'] ?? ''),
            paymentId: $this->nullableString($data['payment_id'] ?? null),
            payoutId: $this->nullableString($data['payout_id'] ?? null),
            referenceId: $this->nullableString($data['reference_id'] ?? null),
            amount: (float) ($data['amount'] ?? $data['source_amount'] ?? 0),
            status: (string) ($data['status'] ?? ''),
            payload: $body,
        );
    }

    public function checkStatus(Booking $booking): XenditStatus
    {
        $sessionId = (string) ($booking->payments()
            ->where('gateway', 'xendit')
            ->latest()
            ->value('gateway_request_id') ?? '');

        if ($sessionId === '') {
            throw new RuntimeException('Booking tidak memiliki Payment Session Xendit.');
        }

        $response = $this->request()->get($this->url('/sessions/'.$sessionId));
        $response->throw();

        /** @var array<string, mixed> $body */
        $body = $response->json();

        return new XenditStatus(
            status: (string) ($body['status'] ?? ''),
            payload: $body,
        );
    }

    public function refund(Payment $payment, Refund $refund): XenditRefundResult
    {
        $paymentId = data_get($payment->gateway_payload, 'payment_id');

        if (! is_string($paymentId) || $paymentId === '') {
            throw new RuntimeException('Payment tidak memiliki payment_id Xendit.');
        }

        $response = $this->request()
            ->withHeader('api-version', (string) config('payment.xendit.payment_api_version'))
            ->post($this->url('/v3/refunds'), [
                'reference_id' => 'refund-'.$refund->id.'-'.Str::random(6),
                'payment_id' => $paymentId,
                'amount' => (float) $refund->amount,
                'reason' => $this->refundReason($refund->reason),
                'metadata' => [
                    'refund_id' => $refund->id,
                    'booking_id' => $payment->booking_id,
                ],
            ]);

        $response->throw();

        /** @var array<string, mixed> $body */
        $body = $response->json();

        return new XenditRefundResult(
            refundId: (string) ($body['refund_id'] ?? ''),
            status: (string) ($body['status'] ?? ''),
            payload: $body,
        );
    }

    public function payout(Booking $booking, Payment $payment): XenditPayoutResult
    {
        $account = $booking->mitra?->bankAccounts()
            ->orderByDesc('is_primary')
            ->first();

        if (! $account instanceof MitraBankAccount) {
            throw new RuntimeException('Mitra belum memiliki rekening bank.');
        }

        $fee = max(0, (float) config('payment.xendit.platform_fee_percent', 0));
        $amount = max(0, (float) $payment->jumlah * (1 - $fee / 100));
        $nama = trim((string) $account->nama_pemilik);
        $parts = explode(' ', $nama, 2);

        $response = $this->request()
            ->withHeader('api-version', (string) config('payment.xendit.payout_api_version'))
            ->withHeader('idempotency-key', 'payout-'.$booking->kode)
            ->post($this->url('/v3/payouts'), [
                'reference_id' => $booking->kode,
                'recipient' => [
                    'type' => 'INDIVIDUAL',
                    'given_name' => $parts[0] !== '' ? $parts[0] : 'Mitra',
                    'surname' => $parts[1] ?? null,
                    'relationship' => 'BUSINESS_PARTNER',
                    'account_details' => [
                        'currency' => 'IDR',
                        'account_country' => 'ID',
                        'account_holder_name' => $account->nama_pemilik,
                        'account_number' => $account->nomor_rekening,
                        'routing_type_1' => 'BANK_CODE',
                        'routing_value_1' => $this->bankChannelCode((string) $account->nama_penyedia),
                    ],
                    'address' => ['country' => 'ID'],
                ],
                'payout_details' => [
                    'source_currency' => 'IDR',
                    'source_amount' => (int) $amount,
                    'destination_currency' => 'IDR',
                ],
                'source_of_fund' => 'BUSINESS_REVENUE',
                'purpose_code' => 'OTHER',
                'description' => 'Payout booking '.$booking->kode,
                'metadata' => [
                    'booking_id' => $booking->id,
                    'payment_id' => $payment->id,
                ],
            ]);

        $response->throw();

        return $this->payoutResult($response);
    }

    public function getPayout(string $payoutId): XenditPayoutResult
    {
        $response = $this->request()
            ->withHeader('api-version', (string) config('payment.xendit.payout_api_version'))
            ->get($this->url('/v3/payouts/'.$payoutId));

        $response->throw();

        return $this->payoutResult($response);
    }

    private function payoutResult(Response $response): XenditPayoutResult
    {
        /** @var array<string, mixed> $body */
        $body = $response->json();

        return new XenditPayoutResult(
            payoutId: (string) ($body['payout_id'] ?? ''),
            status: (string) ($body['status'] ?? ''),
            payload: $body,
        );
    }

    private function refundReason(RefundReason $reason): string
    {
        return match ($reason) {
            RefundReason::CANCELLATION => 'CANCELLATION',
            RefundReason::REQUESTED_BY_CUSTOMER => 'REQUESTED_BY_CUSTOMER',
            RefundReason::DUPLICATE => 'DUPLICATE',
            RefundReason::FRAUDULENT => 'FRAUDULENT',
            RefundReason::OTHERS => 'OTHERS',
        };
    }

    private function bankChannelCode(string $namaPenyedia): string
    {
        $key = Str::upper(trim($namaPenyedia));

        if (isset(self::BANK_CHANNELS[$key])) {
            return self::BANK_CHANNELS[$key];
        }

        return 'ID_'.preg_replace('/[^A-Z0-9]/', '', $key);
    }

    private function request(): PendingRequest
    {
        return Http::asJson()->withBasicAuth((string) config('payment.xendit.secret_key'), '');
    }

    private function url(string $path): string
    {
        return rtrim((string) config('payment.xendit.base_url'), '/').$path;
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
