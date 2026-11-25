<?php

use App\Enums\BookingStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentMetode;
use App\Enums\RefundReason;
use App\Enums\RefundStatus;
use App\Jobs\ReconcileXenditTransactionsJob;
use App\Models\Booking;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use App\Services\Payment\Contracts\XenditPaymentServiceInterface;
use App\Services\Payment\XenditNotificationProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function createBookingForReconciliation(User $mitra, User $client): Booking
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

beforeEach(function () {
    Http::preventStrayRequests();

    $this->mitra = User::factory()->mitra()->create(['must_change_password' => false]);
    $this->client = User::factory()->client()->create(['must_change_password' => false]);
    $this->booking = createBookingForReconciliation($this->mitra, $this->client);
});

function runReconciliation(): void
{
    $job = new ReconcileXenditTransactionsJob;

    $job->handle(
        app(XenditPaymentServiceInterface::class),
        app(XenditNotificationProcessor::class),
    );
}

describe('reconciliation Xendit', function () {
    it('menyelesaikan payout yang statusnya SUCCEEDED di Xendit', function () {
        $payment = Payment::create([
            'booking_id' => $this->booking->id,
            'jumlah' => 1_000_000,
            'metode' => PaymentMetode::XENDIT,
            'gateway' => PaymentGateway::XENDIT,
            'gateway_status' => 'PAYOUT_REQUESTED',
            'released_at' => now(),
            'gateway_payload' => ['payout_id' => 'po-123'],
        ]);

        Http::fake([
            'https://api.xendit.co/disbursements/po-123' => Http::response([
                'payout_id' => 'po-123',
                'status' => 'COMPLETED',
            ]),
        ]);

        runReconciliation();

        $fresh = $payment->fresh();

        expect($fresh->settled_at)->not->toBeNull()
            ->and($fresh->gateway_status)->toBe('SETTLED');
    });

    it('menyelesaikan refund yang statusnya SUCCEEDED di Xendit', function () {
        Payment::create([
            'booking_id' => $this->booking->id,
            'jumlah' => 1_000_000,
            'metode' => PaymentMetode::XENDIT,
            'gateway' => PaymentGateway::XENDIT,
            'gateway_status' => 'SUCCESS',
            'gateway_payload' => ['payment_id' => 'py-123'],
        ]);

        $refund = Refund::create([
            'booking_id' => $this->booking->id,
            'mitra_id' => $this->mitra->id,
            'amount' => 500_000,
            'currency' => 'IDR',
            'reason' => RefundReason::CANCELLATION->value,
            'status' => RefundStatus::PENDING->value,
            'gateway_status' => 'PENDING',
            'gateway_refund_id' => 'rfd-123',
        ]);

        Http::fake([
            'https://api.xendit.co/v3/refunds/rfd-123' => Http::response([
                'refund_id' => 'rfd-123',
                'status' => 'SUCCEEDED',
                'reference_id' => 'refund-'.$refund->id,
            ]),
        ]);

        runReconciliation();

        expect($refund->fresh()->status)->toBe(RefundStatus::SUCCEEDED)
            ->and($this->booking->fresh()->status)->toBe(BookingStatus::CANCELLED)
            ->and(Payment::where('booking_id', $this->booking->id)->where('jumlah', '<', 0)->count())->toBe(1);
    });

    it('membiarkan transaksi yang masih PENDING', function () {
        $payment = Payment::create([
            'booking_id' => $this->booking->id,
            'jumlah' => 1_000_000,
            'metode' => PaymentMetode::XENDIT,
            'gateway' => PaymentGateway::XENDIT,
            'gateway_status' => 'PAYOUT_REQUESTED',
            'released_at' => now(),
            'gateway_payload' => ['payout_id' => 'po-pending'],
        ]);

        Http::fake([
            'https://api.xendit.co/disbursements/po-pending' => Http::response([
                'payout_id' => 'po-pending',
                'status' => 'PENDING',
            ]),
        ]);

        runReconciliation();

        expect($payment->fresh()->settled_at)->toBeNull()
            ->and($payment->fresh()->gateway_status)->toBe('PAYOUT_REQUESTED');
    });
});
