<?php

use App\Data\Xendit\XenditWebhookEvent;
use App\Enums\BookingStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentMetode;
use App\Jobs\DisburseToMitraJob;
use App\Models\Booking;
use App\Models\MitraBankAccount;
use App\Models\Package;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payment\XenditNotificationProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function createBookingForDisbursement(User $mitra, User $client): Booking
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

function createSettledPaymentForDisbursement(Booking $booking): Payment
{
    return Payment::create([
        'booking_id' => $booking->id,
        'jumlah' => 1_000_000,
        'metode' => PaymentMetode::XENDIT,
        'gateway' => PaymentGateway::XENDIT,
        'gateway_order_id' => $booking->kode,
        'gateway_request_id' => 'ps-1234567890123456789012345',
        'gateway_status' => 'SUCCESS',
        'gateway_payload' => ['payment_id' => 'py-123'],
    ]);
}

beforeEach(function () {
    config([
        'payment.xendit.secret_key' => 'xnd_development_test_key',
        'payment.xendit.payout_api_version' => '2025-09-01',
    ]);

    Http::preventStrayRequests();

    $this->mitra = User::factory()->mitra()->create(['must_change_password' => false]);
    $this->client = User::factory()->client()->create(['must_change_password' => false]);
    $this->booking = createBookingForDisbursement($this->mitra, $this->client);
    $this->payment = createSettledPaymentForDisbursement($this->booking);

    MitraBankAccount::create([
        'user_id' => $this->mitra->id,
        'tipe' => 'bank',
        'nama_penyedia' => 'BCA',
        'nomor_rekening' => '1234567890',
        'nama_pemilik' => 'Mitra Travel',
        'is_primary' => true,
    ]);
});

describe('DisburseToMitraJob', function () {
    it('mengirim payout dan menyimpan payout_id', function () {
        Http::fake([
            'https://api.xendit.co/v3/payouts' => Http::response([
                'payout_id' => 'po-123',
                'status' => 'ACCEPTED',
            ]),
        ]);

        Bus::dispatchSync(new DisburseToMitraJob($this->booking->id));

        $payment = $this->payment->fresh();

        expect(data_get($payment->gateway_payload, 'payout_id'))->toBe('po-123')
            ->and($payment->released_at)->not->toBeNull()
            ->and($payment->gateway_status)->toBe('PAYOUT_REQUESTED');

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.xendit.co/v3/payouts');
    });

    it('tidak mengirim payout dua kali', function () {
        $this->payment->update([
            'gateway_payload' => ['payment_id' => 'py-123', 'payout_id' => 'po-existing'],
            'released_at' => now(),
        ]);

        Bus::dispatchSync(new DisburseToMitraJob($this->booking->id));

        Http::assertNothingSent();
    });

    it('melempar error ketika saldo tidak cukup agar job di-retry', function () {
        Http::fake([
            'https://api.xendit.co/v3/payouts' => Http::response([
                'error_code' => 'INSUFFICIENT_BALANCE',
                'message' => 'Saldo tidak cukup.',
            ], 400),
        ]);

        expect(fn () => Bus::dispatchSync(new DisburseToMitraJob($this->booking->id)))
            ->toThrow(\Illuminate\Http\Client\RequestException::class);

        expect($this->payment->fresh()->released_at)->toBeNull();
    });
});

describe('webhook payout Xendit', function () {
    it('menandai dana settled saat v3_payout.succeeded', function () {
        $this->payment->update([
            'gateway_payload' => ['payment_id' => 'py-123', 'payout_id' => 'po-123'],
            'released_at' => now(),
            'gateway_status' => 'PAYOUT_REQUESTED',
        ]);

        $event = new XenditWebhookEvent(
            event: 'v3_payout.succeeded',
            paymentId: null,
            payoutId: 'po-123',
            referenceId: $this->booking->kode,
            amount: 1_000_000.0,
            status: 'SUCCEEDED',
            payload: [],
        );

        app(XenditNotificationProcessor::class)->process($event);

        $payment = $this->payment->fresh();

        expect($payment->settled_at)->not->toBeNull()
            ->and($payment->gateway_status)->toBe('SETTLED');
    });
});
