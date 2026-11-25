<?php

use App\Data\Xendit\XenditWebhookEvent;
use App\Enums\BookingStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentMetode;
use App\Jobs\ProcessXenditNotificationJob;
use App\Models\Booking;
use App\Models\Package;
use App\Models\Payment;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Services\Payment\XenditNotificationProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;

uses(RefreshDatabase::class);

function createBookingForXenditWebhook(User $mitra, User $client): Booking
{
    $package = Package::factory()->forMitra($mitra)->aktif()->create();

    return Booking::factory()
        ->forPackage($package)
        ->forClient($client)
        ->create([
            'mitra_id' => $mitra->id,
            'status' => BookingStatus::PENDING_PAYMENT,
            'total_harga' => 1_000_000,
        ]);
}

function createPendingXenditPayment(Booking $booking): Payment
{
    return Payment::create([
        'booking_id' => $booking->id,
        'jumlah' => 1_000_000,
        'metode' => PaymentMetode::XENDIT,
        'gateway' => PaymentGateway::XENDIT,
        'gateway_order_id' => $booking->kode,
        'gateway_request_id' => 'ps-1234567890123456789012345',
        'gateway_status' => 'pending',
        'gateway_payload' => ['payment_link_url' => 'https://dev.xen.to/abc123'],
    ]);
}

function xenditPaymentWebhook(Booking $booking, int $amount = 1_000_000): array
{
    return [
        'event' => 'payment.capture',
        'business_id' => 'business-123',
        'data' => [
            'payment_id' => 'py-123',
            'payment_request_id' => 'pr-123',
            'reference_id' => $booking->kode,
            'request_amount' => $amount,
            'status' => 'SUCCEEDED',
        ],
    ];
}

beforeEach(function () {
    config(['payment.xendit.callback_token' => 'callback-token-rahasia']);

    Bus::fake();

    $this->mitra = User::factory()->mitra()->create(['must_change_password' => false]);
    $this->client = User::factory()->client()->create(['must_change_password' => false]);
    $this->booking = createBookingForXenditWebhook($this->mitra, $this->client);
    $this->payment = createPendingXenditPayment($this->booking);
});

describe('POST /payments/xendit/webhook', function () {
    it('menerima webhook bertanda tangan dan mengantrekan pemrosesannya', function () {
        $this->postJson('/payments/xendit/webhook', xenditPaymentWebhook($this->booking), [
            'X-Callback-Token' => 'callback-token-rahasia',
        ])->assertOk();

        $this->assertDatabaseHas('webhook_events', [
            'provider' => 'xendit',
            'request_id' => 'py-123',
        ]);

        Bus::assertDispatched(ProcessXenditNotificationJob::class, 1);
    });

    it('menerima webhook pada path alias /xendit/webhook', function () {
        $this->postJson('/xendit/webhook', xenditPaymentWebhook($this->booking), [
            'X-Callback-Token' => 'callback-token-rahasia',
        ])->assertOk();

        Bus::assertDispatched(ProcessXenditNotificationJob::class, 1);
    });

    it('menolak webhook dengan token salah', function () {
        $this->postJson('/payments/xendit/webhook', xenditPaymentWebhook($this->booking), [
            'X-Callback-Token' => 'salah',
        ])->assertUnauthorized();

        expect(WebhookEvent::count())->toBe(0);

        Bus::assertNothingDispatched();
    });

    it('tidak mengantrekan webhook yang sama dua kali', function () {
        $headers = ['X-Callback-Token' => 'callback-token-rahasia'];

        $this->postJson('/payments/xendit/webhook', xenditPaymentWebhook($this->booking), $headers)->assertOk();
        $this->postJson('/payments/xendit/webhook', xenditPaymentWebhook($this->booking), $headers)->assertOk();

        expect(WebhookEvent::where('provider', 'xendit')->count())->toBe(1);

        Bus::assertDispatched(ProcessXenditNotificationJob::class, 1);
    });

    it('menghapus penanda idempotensi bila pemrosesan gagal', function () {
        WebhookEvent::query()->create([
            'provider' => 'xendit',
            'request_id' => 'py-failed',
            'payload' => [],
        ]);

        $job = new ProcessXenditNotificationJob(
            new XenditWebhookEvent(
                event: 'payment.capture',
                paymentId: 'py-failed',
                payoutId: null,
                referenceId: $this->booking->kode,
                amount: 1_000_000.0,
                status: 'SUCCEEDED',
                payload: [],
            ),
            'py-failed',
        );

        $job->failed();

        expect(WebhookEvent::query()
            ->where('provider', 'xendit')
            ->where('request_id', 'py-failed')
            ->count())->toBe(0);
    });
});

describe('XenditNotificationProcessor', function () {
    it('mengonfirmasi booking dan menyimpan payment_id', function () {
        $event = new XenditWebhookEvent(
            event: 'payment.capture',
            paymentId: 'py-123',
            payoutId: null,
            referenceId: $this->booking->kode,
            amount: 1_000_000.0,
            status: 'SUCCEEDED',
            payload: [],
        );

        app(XenditNotificationProcessor::class)->process($event);

        expect($this->booking->fresh()->status)->toBe(BookingStatus::CONFIRMED)
            ->and($this->payment->fresh()->gateway_status)->toBe('SUCCESS')
            ->and($this->payment->fresh()->paid_at)->not->toBeNull()
            ->and(data_get($this->payment->fresh()->gateway_payload, 'payment_id'))->toBe('py-123');
    });

    it('tidak mengubah booking ketika amount tidak cocok', function () {
        $event = new XenditWebhookEvent(
            event: 'payment.capture',
            paymentId: 'py-123',
            payoutId: null,
            referenceId: $this->booking->kode,
            amount: 999_000.0,
            status: 'SUCCEEDED',
            payload: [],
        );

        app(XenditNotificationProcessor::class)->process($event);

        expect($this->booking->fresh()->status)->toBe(BookingStatus::PENDING_PAYMENT)
            ->and($this->payment->fresh()->gateway_status)->toBe('pending');
    });

    it('menandai payment gagal tanpa mengubah status booking', function () {
        $event = new XenditWebhookEvent(
            event: 'payment.failure',
            paymentId: 'py-123',
            payoutId: null,
            referenceId: $this->booking->kode,
            amount: 1_000_000.0,
            status: 'FAILED',
            payload: [],
        );

        app(XenditNotificationProcessor::class)->process($event);

        expect($this->payment->fresh()->gateway_status)->toBe('FAILED')
            ->and($this->booking->fresh()->status)->toBe(BookingStatus::PENDING_PAYMENT);
    });

    it('menandai payment kedaluwarsa tanpa mengubah status booking', function () {
        $event = new XenditWebhookEvent(
            event: 'payment_session.expired',
            paymentId: null,
            payoutId: null,
            referenceId: $this->booking->kode,
            amount: 0.0,
            status: 'EXPIRED',
            payload: [],
        );

        app(XenditNotificationProcessor::class)->process($event);

        expect($this->payment->fresh()->gateway_status)->toBe('EXPIRED')
            ->and($this->booking->fresh()->status)->toBe(BookingStatus::PENDING_PAYMENT);
    });

    it('tidak memproses ulang payment yang sudah SUCCESS', function () {
        $this->payment->update(['gateway_status' => 'SUCCESS']);

        $event = new XenditWebhookEvent(
            event: 'payment.capture',
            paymentId: 'py-123',
            payoutId: null,
            referenceId: $this->booking->kode,
            amount: 1_000_000.0,
            status: 'SUCCEEDED',
            payload: [],
        );

        app(XenditNotificationProcessor::class)->process($event);

        expect($this->booking->fresh()->status)->toBe(BookingStatus::PENDING_PAYMENT);
    });
});
