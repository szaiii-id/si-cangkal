<?php

use App\Enums\BookingStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentMetode;
use App\Models\Booking;
use App\Models\MitraBankAccount;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use App\Services\Payment\Contracts\XenditPaymentServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function createBookingForXenditAdapter(User $mitra, User $client, int $total): Booking
{
    $package = Package::factory()->forMitra($mitra)->aktif()->create();

    return Booking::factory()
        ->forPackage($package)
        ->forClient($client)
        ->create([
            'mitra_id' => $mitra->id,
            'status' => BookingStatus::PENDING_PAYMENT,
            'total_harga' => $total,
        ]);
}

beforeEach(function () {
    config([
        'payment.xendit.secret_key' => 'xnd_development_test_key',
        'payment.xendit.callback_token' => 'callback-token-rahasia',
    ]);

    Http::preventStrayRequests();

    $this->mitra = User::factory()->mitra()->create(['must_change_password' => false]);
    $this->client = User::factory()->client()->create(['must_change_password' => false]);
    $this->booking = createBookingForXenditAdapter($this->mitra, $this->client, 1_000_000);
});

describe('adapter Xendit', function () {
    it('membuat Payment Session tanpa header XenPlatform', function () {
        Http::fake([
            'https://api.xendit.co/sessions' => Http::response([
                'payment_session_id' => 'ps-1234567890123456789012345',
                'payment_link_url' => 'https://dev.xen.to/abc123',
                'status' => 'ACTIVE',
                'reference_id' => $this->booking->kode,
                'amount' => 1_000_000,
                'payment_request_id' => null,
            ], 201),
        ]);

        $result = app(XenditPaymentServiceInterface::class)->createSession($this->booking);

        expect($result->sessionId)->toBe('ps-1234567890123456789012345')
            ->and($result->checkoutUrl)->toBe('https://dev.xen.to/abc123');

        Http::assertSent(function (Request $request): bool {
            if ($request->method() !== 'POST' || $request->url() !== 'https://api.xendit.co/sessions') {
                return false;
            }

            $data = $request->data();

            return $data['reference_id'] === $this->booking->kode
                && $data['session_type'] === 'PAY'
                && $data['mode'] === 'PAYMENT_LINK'
                && $data['country'] === 'ID'
                && $data['currency'] === 'IDR'
                && $data['amount'] === 1_000_000
                && is_string($data['metadata']['booking_id'])
                && is_string($data['metadata']['sequence'])
                && ! $request->hasHeader('for-user-id')
                && ! $request->hasHeader('with-split-rule')
                && $request->hasHeader('Authorization', 'Basic '.base64_encode('xnd_development_test_key:'));
        });
    });

    it('menolak webhook dengan callback token yang salah', function () {
        $payload = json_encode([
            'event' => 'payment.capture',
            'data' => ['payment_id' => 'py-123', 'reference_id' => $this->booking->kode, 'request_amount' => 1_000_000, 'status' => 'SUCCEEDED'],
        ], JSON_THROW_ON_ERROR);

        expect(fn () => app(XenditPaymentServiceInterface::class)
            ->verifyWebhook($payload, 'salah'))
            ->toThrow(ValidationException::class);
    });

    it('membaca field kunci dari webhook payment yang sah', function () {
        $payload = json_encode([
            'event' => 'payment.capture',
            'data' => [
                'payment_id' => 'py-123',
                'reference_id' => $this->booking->kode,
                'amount' => 1_000_000,
                'status' => 'SUCCEEDED',
                'payment_request_id' => 'pr-123',
            ],
        ], JSON_THROW_ON_ERROR);

        $event = app(XenditPaymentServiceInterface::class)
            ->verifyWebhook($payload, 'callback-token-rahasia');

        expect($event->event)->toBe('payment.capture')
            ->and($event->paymentId)->toBe('py-123')
            ->and($event->referenceId)->toBe($this->booking->kode)
            ->and($event->amount)->toBe(1_000_000.0)
            ->and($event->status)->toBe('SUCCEEDED');
    });

    it('mengirim payout ke rekening mitra tanpa split rule', function () {
        MitraBankAccount::create([
            'user_id' => $this->mitra->id,
            'tipe' => 'bank',
            'nama_penyedia' => 'BCA',
            'nomor_rekening' => '1234567890',
            'nama_pemilik' => 'Mitra Travel',
            'is_primary' => true,
        ]);

        Http::fake([
            'https://api.xendit.co/disbursements' => Http::response([
                'id' => 'po-123',
                'status' => 'PENDING',
            ]),
        ]);

        $payment = Payment::create([
            'booking_id' => $this->booking->id,
            'jumlah' => 1_000_000,
            'metode' => PaymentMetode::XENDIT,
            'gateway' => PaymentGateway::XENDIT,
            'gateway_status' => 'SUCCESS',
            'gateway_payload' => ['payment_id' => 'py-123'],
        ]);

        $result = app(XenditPaymentServiceInterface::class)->payout($this->booking, $payment);

        expect($result->payoutId)->toBe('po-123')
            ->and($result->status)->toBe('PENDING');

        Http::assertSent(function (Request $request): bool {
            if ($request->url() !== 'https://api.xendit.co/disbursements') {
                return false;
            }

            $data = $request->data();

            return ! $request->hasHeader('for-user-id')
                && ! $request->hasHeader('with-split-rule')
                && $data['external_id'] === 'payout-'.$this->booking->kode.'-1'
                && $data['bank_code'] === 'BCA'
                && $data['account_number'] === '1234567890'
                && $data['account_holder_name'] === 'Mitra Travel'
                && $data['amount'] === 1_000_000
                && $data['currency'] === 'IDR';
        });
    });

    it('mengirim refund memakai payment_id', function () {
        Http::fake([
            'https://api.xendit.co/v3/refunds' => Http::response([
                'refund_id' => 'rfd-123',
                'status' => 'SUCCEEDED',
            ]),
        ]);

        $payment = Payment::create([
            'booking_id' => $this->booking->id,
            'jumlah' => 1_000_000,
            'metode' => PaymentMetode::XENDIT,
            'gateway' => PaymentGateway::XENDIT,
            'gateway_status' => 'SUCCESS',
            'gateway_payload' => ['payment_id' => 'py-abc'],
        ]);

        $refund = new Refund([
            'booking_id' => $this->booking->id,
            'mitra_id' => $this->mitra->id,
            'amount' => 500_000,
            'currency' => 'IDR',
            'reason' => 'CANCELLATION',
        ]);

        $result = app(XenditPaymentServiceInterface::class)->refund($payment, $refund);

        expect($result->refundId)->toBe('rfd-123')
            ->and($result->status)->toBe('SUCCEEDED');

        Http::assertSent(function (Request $request): bool {
            $data = $request->data();

            return $request->url() === 'https://api.xendit.co/v3/refunds'
                && $request->hasHeader('api-version', '2024-11-11')
                && ! $request->hasHeader('for-user-id')
                && $data['payment_id'] === 'py-abc'
                && $data['amount'] === 500_000.0
                && $data['reason'] === 'CANCELLATION'
                && is_string($data['metadata']['refund_id'])
                && is_string($data['metadata']['booking_id']);
        });
    });
});
