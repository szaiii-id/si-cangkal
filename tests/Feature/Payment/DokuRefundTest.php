<?php

use App\Enums\BookingStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentMetode;
use App\Enums\RefundReason;
use App\Enums\RefundStatus;
use App\Jobs\SyncDokuRefundJob;
use App\Models\Booking;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use App\Services\Refund\RefundApprovalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Psr\Log\LoggerInterface;

uses(RefreshDatabase::class);

/*
|==========================================================================
| SETUP — Integration Test
|==========================================================================
|
| Refund ganda:
| - pembayaran gateway → DOKU Refund API
| - pembayaran manual → alur RefundApprovalService lama
|
*/

beforeEach(function () {
    config([
        'payment.doku.client_id' => 'MCH-SANDBOX-001',
        'payment.doku.secret_key' => 'secret-sandbox',
        'payment.doku.base_url' => 'https://api-sandbox.doku.com',
        'payment.doku.refund_path' => '/cancellation/credit-card/refund',
    ]);

    Http::preventStrayRequests();

    $audit = Mockery::mock(LoggerInterface::class);
    $audit->shouldReceive('info')->byDefault()->andReturnNull();

    Log::shouldReceive('channel')->with('audit')->byDefault()->andReturn($audit);
    Log::shouldReceive('driver')->byDefault()->andReturn($audit);
    Log::shouldReceive('warning')->byDefault()->andReturnNull();
    Log::shouldReceive('error')->byDefault()->andReturnNull();
    Log::shouldReceive('info')->byDefault()->andReturnNull();

    $this->mitra = User::factory()->mitra()->create(['must_change_password' => false]);
    $this->client = User::factory()->client()->create(['must_change_password' => false]);
});

function prepareRefundWithPayment(User $mitra, User $client, bool $gateway): array
{
    $package = Package::factory()->forMitra($mitra)->aktif()->create();

    $booking = Booking::factory()
        ->forPackage($package)
        ->forClient($client)
        ->create([
            'mitra_id' => $mitra->id,
            'status' => BookingStatus::CONFIRMED,
            'total_harga' => 1_000_000,
        ]);

    Payment::create([
        'booking_id' => $booking->id,
        'jumlah' => 1_000_000,
        'metode' => $gateway ? PaymentMetode::DOKU : PaymentMetode::TRANSFER_MANUAL,
        'gateway' => $gateway ? PaymentGateway::DOKU : PaymentGateway::MANUAL,
        'gateway_order_id' => $gateway ? $booking->kode : null,
        'gateway_request_id' => $gateway ? 'charge-1' : null,
        'gateway_status' => $gateway ? 'SUCCESS' : null,
        'verified_at' => now(),
    ]);

    $refund = Refund::create([
        'booking_id' => $booking->id,
        'mitra_id' => $mitra->id,
        'amount' => 1_000_000,
        'currency' => 'IDR',
        'reason' => RefundReason::CANCELLATION,
        'status' => RefundStatus::PENDING,
        'initiated_by_mitra' => $mitra->id,
    ]);

    return [$booking, $refund];
}

function fakeDokuRefund(string $status = 'SUCCESS'): void
{
    Http::fake([
        'api-sandbox.doku.com/cancellation/credit-card/refund' => Http::response([
            'order' => ['invoice_number' => 'BKG-DUMMY'],
            'payment' => ['original_request_id' => 'charge-1'],
            'refund' => [
                'amount' => 1_000_000,
                'status' => $status,
                'approval_code' => $status === 'SUCCESS' ? 'APR-123' : null,
            ],
        ]),
    ]);
}

describe('refund gateway DOKU', function () {
    it('memanggil refund API untuk pembayaran gateway', function () {
        [$booking, $refund] = prepareRefundWithPayment($this->mitra, $this->client, true);
        fakeDokuRefund();

        $result = app(RefundApprovalService::class)
            ->approve($refund, $this->mitra, 'Refund gateway');

        Http::assertSent(function (Request $request) use ($booking): bool {
            $data = $request->data();

            return $request->url() === 'https://api-sandbox.doku.com/cancellation/credit-card/refund'
                && $data['order']['invoice_number'] === $booking->kode
                && $data['payment']['original_request_id'] === 'charge-1'
                && $data['refund']['amount'] === 1_000_000
                && str_starts_with((string) $request->header('Signature')[0], 'HMACSHA256=');
        });

        expect($result->status)->toBe(RefundStatus::SUCCEEDED)
            ->and($result->gateway_status)->toBe('SUCCESS')
            ->and($result->gateway_refund_id)->toBe('APR-123');

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => BookingStatus::CANCELLED->value,
        ]);
    });

    it('tetap memakai alur manual untuk pembayaran transfer manual', function () {
        [$booking, $refund] = prepareRefundWithPayment($this->mitra, $this->client, false);
        fakeDokuRefund();

        $result = app(RefundApprovalService::class)
            ->approve($refund, $this->mitra, 'Refund manual');

        Http::assertNothingSent();

        expect($result->status)->toBe(RefundStatus::SUCCEEDED);

        $this->assertDatabaseHas('payments', [
            'booking_id' => $booking->id,
            'jumlah' => -1_000_000,
            'metode' => PaymentMetode::TRANSFER_MANUAL->value,
        ]);
    });

    it('gateway gagal → refund tetap PENDING dan job retry diantrekan', function () {
        Queue::fake();
        [$booking, $refund] = prepareRefundWithPayment($this->mitra, $this->client, true);
        fakeDokuRefund('FAILED');

        $result = app(RefundApprovalService::class)
            ->approve($refund, $this->mitra, 'Issuer QRIS tidak mendukung');

        expect($result->status)->toBe(RefundStatus::PENDING)
            ->and($result->gateway_status)->toBe('FAILED');

        $this->assertDatabaseHas('refunds', [
            'id' => $refund->id,
            'status' => RefundStatus::PENDING->value,
            'gateway_status' => 'FAILED',
        ]);

        Queue::assertPushed(SyncDokuRefundJob::class, 1);

        expect($booking->fresh()->status)->toBe(BookingStatus::CONFIRMED);
    });

    it('job retry menyelesaikan refund saat gateway akhirnya sukses', function () {
        [$booking, $refund] = prepareRefundWithPayment($this->mitra, $this->client, true);

        Http::fake([
            'api-sandbox.doku.com/cancellation/credit-card/refund-1' => Http::response([
                'refund' => ['status' => 'FAILED', 'message' => 'issuer tidak mendukung'],
            ]),
            'api-sandbox.doku.com/cancellation/credit-card/refund-2' => Http::response([
                'refund' => ['status' => 'SUCCESS', 'approval_code' => 'APR-456'],
            ]),
        ]);

        config(['payment.doku.refund_path' => '/cancellation/credit-card/refund-1']);
        app(RefundApprovalService::class)->approve($refund, $this->mitra, 'Percobaan pertama');

        expect($refund->fresh()->gateway_status)->toBe('FAILED');

        config(['payment.doku.refund_path' => '/cancellation/credit-card/refund-2']);
        (new SyncDokuRefundJob($refund->id))->handle(app(RefundApprovalService::class));

        expect($refund->fresh()->status)->toBe(RefundStatus::SUCCEEDED)
            ->and($refund->fresh()->gateway_status)->toBe('SUCCESS');
    });

    it('approve dua kali tidak memanggil refund API dua kali', function () {
        [$booking, $refund] = prepareRefundWithPayment($this->mitra, $this->client, true);
        fakeDokuRefund();

        $service = app(RefundApprovalService::class);
        $service->approve($refund, $this->mitra, 'Pertama');

        expect(fn () => $service->approve($refund->fresh(), $this->mitra, 'Kedua'))
            ->toThrow(ValidationException::class);

        Http::assertSentCount(1);
    });
});
