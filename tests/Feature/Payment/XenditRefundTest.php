<?php

use App\Data\Xendit\XenditWebhookEvent;
use App\Enums\BookingStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentMetode;
use App\Enums\RefundStatus;
use App\Jobs\ReconcileXenditTransactionsJob;
use App\Models\Booking;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use App\Services\Payment\Contracts\XenditPaymentServiceInterface;
use App\Services\Payment\XenditNotificationProcessor;
use App\Services\Refund\RefundApprovalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;
use RuntimeException;

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
            'settled_at' => now(),
            'gateway_status' => 'SETTLED',
        ]);

        $result = app(RefundApprovalService::class)->approve($this->refund, $this->mitra, 'Trip dibatalkan');

        expect($result->status)->toBe(RefundStatus::AWAITING_PROOF)
            ->and($result->gateway_refund_id)->toBeNull();

        Http::assertNothingSent();

        // Bukti belum diunggah → booking belum dibatalkan.
        $this->assertDatabaseHas('bookings', [
            'id' => $this->booking->id,
            'status' => BookingStatus::CONFIRMED->value,
        ]);
    });

    it('menyimpan gateway_refund_id sebelum transaksi utama agar rekonsiliasi bisa memulihkan refund', function () {
        Http::fake([
            'https://api.xendit.co/v3/refunds' => Http::response([
                'refund_id' => 'rfd-recover',
                'status' => 'SUCCEEDED',
            ]),
            'https://api.xendit.co/v3/refunds/rfd-recover' => Http::response([
                'refund_id' => 'rfd-recover',
                'status' => 'SUCCEEDED',
                'reference_id' => 'refund-'.$this->refund->id,
            ]),
        ]);

        // Simulasikan transaksi utama gagal tepat setelah Xendit menerima refund.
        $logger = Mockery::mock(LoggerInterface::class);
        $thrown = false;
        $logger->shouldReceive('info')->byDefault()->andReturnNull();
        $logger->shouldReceive('info')
            ->with('refund.approved', Mockery::any())
            ->andReturnUsing(function () use (&$thrown) {
                if (! $thrown) {
                    $thrown = true;

                    throw new RuntimeException('simulasi commit gagal');
                }

                return null;
            });
        Log::shouldReceive('channel')->with('audit')->andReturn($logger);

        try {
            app(RefundApprovalService::class)->approve($this->refund, $this->mitra, null);
        } catch (RuntimeException) {
            // Diharapkan: transaksi utama rollback.
        }

        expect($this->refund->fresh()->gateway_refund_id)->toBe('rfd-recover')
            ->and(Payment::where('booking_id', $this->booking->id)->where('jumlah', '<', 0)->count())->toBe(0)
            ->and($this->booking->fresh()->status)->toBe(BookingStatus::CONFIRMED);

        // Rekonsiliasi harus menemukan refund yang "hilang" tadi.
        (new ReconcileXenditTransactionsJob)->handle(
            app(XenditPaymentServiceInterface::class),
            app(XenditNotificationProcessor::class),
        );

        expect($this->refund->fresh()->status)->toBe(RefundStatus::SUCCEEDED)
            ->and(Payment::where('booking_id', $this->booking->id)->where('jumlah', '<', 0)->count())->toBe(1)
            ->and($this->booking->fresh()->status)->toBe(BookingStatus::CANCELLED);
    });

    it('menunggu webhook bila gateway mengembalikan PENDING', function () {
        Http::fake([
            'https://api.xendit.co/v3/refunds' => Http::response([
                'refund_id' => 'rfd-pending',
                'status' => 'PENDING',
            ]),
        ]);

        $refund = app(RefundApprovalService::class)->approve($this->refund, $this->mitra, null);

        expect($refund->status)->toBe(RefundStatus::PENDING)
            ->and($refund->gateway_status)->toBe('PENDING')
            ->and($this->booking->fresh()->status)->toBe(BookingStatus::CONFIRMED)
            ->and(Payment::where('booking_id', $this->booking->id)->where('jumlah', '<', 0)->count())->toBe(0);

        $event = new XenditWebhookEvent(
            event: 'refund.succeeded',
            paymentId: 'py-abc',
            payoutId: null,
            referenceId: 'refund-'.$refund->id,
            amount: 500_000.0,
            status: 'SUCCEEDED',
            payload: [],
        );

        app(XenditNotificationProcessor::class)->process($event);

        expect($refund->fresh()->status)->toBe(RefundStatus::SUCCEEDED)
            ->and($this->booking->fresh()->status)->toBe(BookingStatus::CANCELLED)
            ->and(Payment::where('booking_id', $this->booking->id)->where('jumlah', '<', 0)->count())->toBe(1);
    });

    it('refund.failed tidak menulis Payment negatif dan tidak membatalkan booking', function () {
        $this->refund->update([
            'gateway_status' => 'PENDING',
            'gateway_refund_id' => 'rfd-gagal',
        ]);

        app(XenditNotificationProcessor::class)->process(new XenditWebhookEvent(
            event: 'refund.failed',
            paymentId: 'py-abc',
            payoutId: null,
            referenceId: 'refund-'.$this->refund->id,
            amount: 500_000.0,
            status: 'FAILED',
            payload: ['data' => ['failure_code' => 'INSUFFICIENT_FUNDS']],
        ));

        $refund = $this->refund->fresh();

        expect($refund->status)->toBe(RefundStatus::PENDING)
            ->and($refund->gateway_status)->toBe('FAILED')
            ->and(Payment::where('booking_id', $this->booking->id)->where('jumlah', '<', 0)->count())->toBe(0)
            ->and($this->booking->fresh()->status)->toBe(BookingStatus::CONFIRMED);
    });
});
