<?php

use App\Data\Xendit\XenditWebhookEvent;
use App\Enums\BookingStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentMetode;
use App\Jobs\DisburseToMitraJob;
use App\Models\Booking;
use App\Models\Package;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payment\XenditNotificationProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;

uses(RefreshDatabase::class);

function createBookingForXenditPayoutWebhook(User $mitra, User $client): Booking
{
    $package = Package::factory()->forMitra($mitra)->aktif()->create();

    return Booking::factory()
        ->forPackage($package)
        ->forClient($client)
        ->create([
            'mitra_id' => $mitra->id,
            'status' => BookingStatus::CONFIRMED,
            'total_harga' => 1_000_000,
        ]);
}

function createRequestedPayoutPayment(Booking $booking): Payment
{
    return Payment::create([
        'booking_id' => $booking->id,
        'jumlah' => 1_000_000,
        'metode' => PaymentMetode::XENDIT,
        'gateway' => PaymentGateway::XENDIT,
        'gateway_status' => 'PAYOUT_REQUESTED',
        'released_at' => now(),
        'gateway_payload' => ['payment_id' => 'py-123', 'payout_id' => 'po-123', 'payout_attempt' => 1],
    ]);
}

function payoutWebhookEvent(string $status, string $failureCode): XenditWebhookEvent
{
    return new XenditWebhookEvent(
        event: 'v3_payout.'.$status,
        paymentId: null,
        payoutId: 'po-123',
        referenceId: null,
        amount: 0.0,
        status: strtoupper($status),
        payload: ['data' => ['payout_id' => 'po-123', 'failure_code' => $failureCode]],
    );
}

beforeEach(function () {
    Bus::fake();

    $this->mitra = User::factory()->mitra()->create(['must_change_password' => false]);
    $this->client = User::factory()->client()->create(['must_change_password' => false]);
    $this->booking = createBookingForXenditPayoutWebhook($this->mitra, $this->client);
    $this->payment = createRequestedPayoutPayment($this->booking);
});

describe('webhook kegagalan payout', function () {
    it('menjadwalkan ulang payout bila saldo tidak cukup', function () {
        app(XenditNotificationProcessor::class)
            ->process(payoutWebhookEvent('failed', 'INSUFFICIENT_BALANCE'));

        $payment = $this->payment->fresh();

        expect($payment->released_at)->toBeNull()
            ->and($payment->gateway_status)->toBe('SUCCESS')
            ->and(data_get($payment->gateway_payload, 'payout_attempt'))->toBe(2)
            ->and(data_get($payment->gateway_payload, 'payout_id'))->toBeNull();

        Bus::assertDispatched(DisburseToMitraJob::class, 1);
    });

    it('tidak mengulang payout bila rekening tujuan invalid', function () {
        app(XenditNotificationProcessor::class)
            ->process(payoutWebhookEvent('failed', 'INVALID_DESTINATION'));

        $payment = $this->payment->fresh();

        expect($payment->gateway_status)->toBe('PAYOUT_FAILED')
            ->and(data_get($payment->gateway_payload, 'payout_failure_code'))->toBe('INVALID_DESTINATION');

        Bus::assertNotDispatched(DisburseToMitraJob::class);
    });
});
