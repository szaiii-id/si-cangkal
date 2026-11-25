<?php

use App\Enums\BookingStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentMetode;
use App\Enums\RefundStatus;
use App\Models\Booking;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use App\Services\Refund\RefundApprovalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function createBookingForXenditRefund(User $mitra, User $client): Booking
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
    config([
        'payment.xendit.secret_key' => 'xnd_development_test_key',
        'payment.xendit.payment_api_version' => '2024-11-11',
    ]);

    Http::preventStrayRequests();

    $this->mitra = User::factory()->mitra()->create(['must_change_password' => false]);
    $this->client = User::factory()->client()->create(['must_change_password' => false]);
    $this->booking = createBookingForXenditRefund($this->mitra, $this->client);

    $this->payment = Payment::create([
        'booking_id' => $this->booking->id,
        'jumlah' => 1_000_000,
        'metode' => PaymentMetode::XENDIT,
        'gateway' => PaymentGateway::XENDIT,
        'gateway_status' => 'SUCCESS',
        'gateway_payload' => ['payment_id' => 'py-abc'],
    ]);

    $this->refund = Refund::factory()->create([
        'booking_id' => $this->booking->id,
        'mitra_id' => $this->mitra->id,
        'amount' => 500_000,
        'status' => RefundStatus::PENDING->value,
    ]);
});

describe('refund gateway Xendit', function () {
    it('memanggil refund sebelum dana dicairkan', function () {
        Http::fake([
            'https://api.xendit.co/v3/refunds' => Http::response([
                'refund_id' => 'rfd-123',
                'status' => 'SUCCEEDED',
            ]),
        ]);

        $result = app(RefundApprovalService::class)->approve($this->refund, $this->mitra, null);

        expect($result->status)->toBe(RefundStatus::SUCCEEDED)
            ->and($result->gateway_refund_id)->toBe('rfd-123')
            ->and($result->gateway_status)->toBe('SUCCESS');

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.xendit.co/v3/refunds');

        $this->assertDatabaseHas('payments', [
            'booking_id' => $this->booking->id,
            'jumlah' => -500_000,
            'gateway' => PaymentGateway::XENDIT->value,
        ]);
    });

    it('memakai refund manual setelah payout sukses tanpa memanggil gateway', function () {
        $this->payment->update([
            'gateway_payload' => ['payment_id' => 'py-abc', 'payout_id' => 'po-123'],
            'released_at' => now(),
            'gateway_status' => 'SETTLED',
        ]);

        $result = app(RefundApprovalService::class)->approve($this->refund, $this->mitra, 'Trip dibatalkan');

        expect($result->status)->toBe(RefundStatus::SUCCEEDED)
            ->and($result->gateway_refund_id)->toBeNull();

        Http::assertNothingSent();

        $this->assertDatabaseHas('bookings', [
            'id' => $this->booking->id,
            'status' => BookingStatus::CANCELLED->value,
        ]);
    });
});
