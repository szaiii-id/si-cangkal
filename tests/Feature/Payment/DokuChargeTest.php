<?php

use App\Enums\BookingStatus;
use App\Enums\PaymentGateway;
use App\Models\Booking;
use App\Models\MitraBankAccount;
use App\Models\Package;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payment\Doku\DokuIdempotencyKey;
use App\Services\Payment\PaymentChargeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/*
|==========================================================================
| SETUP — Integration Test
|==========================================================================
|
| Create charge DOKU Checkout dengan Http::fake(): tidak ada panggilan
| jaringan sungguhan. Fokus pada payload, idempotensi, dan penyimpanan
| jejak pembayaran.
|
*/

beforeEach(function () {
    config([
        'payment.doku.client_id' => 'MCH-SANDBOX-001',
        'payment.doku.secret_key' => 'secret-sandbox',
        'payment.doku.notification_url' => 'https://tunnel.lhr.life/payments/doku/webhook',
    ]);

    Http::preventStrayRequests();

    $this->mitra = User::factory()->mitra()->create(['must_change_password' => false]);
    $this->client = User::factory()->client()->create(['must_change_password' => false]);

    $this->booking = createBookingForDokuCharge($this->mitra, $this->client, 1_000_000);
});

function createBookingForDokuCharge(User $mitra, User $client, int $total): Booking
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

function fakeDokuCheckoutSuccess(): void
{
    Http::fake([
        'api-sandbox.doku.com/*' => Http::response([
            'message' => ['SUCCESS'],
            'response' => [
                'order' => [
                    'amount' => '1000000',
                    'invoice_number' => 'BKG-DUMMY',
                    'session_id' => 'sess-123',
                ],
                'payment' => [
                    'url' => 'https://sandbox.doku.com/checkout-link-v2/token123',
                    'token_id' => 'token123',
                ],
            ],
        ]),
    ]);
}

describe('create charge DOKU Checkout', function () {
    it('mengirim payload checkout dengan hold dan split ke rekening mitra', function () {
        MitraBankAccount::create([
            'user_id' => $this->mitra->id,
            'tipe' => 'bank',
            'nama_penyedia' => 'BCA',
            'nomor_rekening' => '1234567890',
            'nama_pemilik' => 'Mitra Travel',
            'is_primary' => true,
            'settlement_bank_account_id' => 'SBA-0001-123',
            'settlement_verified_at' => now(),
        ]);

        fakeDokuCheckoutSuccess();

        $result = app(PaymentChargeService::class)
            ->createForBooking($this->booking);

        expect($result->checkoutUrl)->toBe('https://sandbox.doku.com/checkout-link-v2/token123')
            ->and($result->requestId)->toBe(DokuIdempotencyKey::forCharge($this->booking))
            ->and($result->sessionId)->toBe('sess-123');

        Http::assertSent(function (Request $request): bool {
            if ($request->method() !== 'POST'
                || $request->url() !== 'https://api-sandbox.doku.com/checkout/v1/payment') {
                return false;
            }

            $data = $request->data();

            return $request->hasHeader('Request-Id', DokuIdempotencyKey::forCharge($this->booking))
                && str_starts_with((string) $request->header('Signature')[0], 'HMACSHA256=')
                && $data['order']['amount'] === 1_000_000
                && $data['order']['invoice_number'] === $this->booking->kode
                && $data['order']['currency'] === 'IDR'
                && $data['additional_info']['hold_settlement'] === true
                && $data['additional_info']['settlement'][0]['bank_account_settlement_id'] === 'SBA-0001-123'
                && $data['additional_info']['settlement'][0]['type'] === 'PERCENTAGE'
                && $data['additional_info']['override_notification_url'] === 'https://tunnel.lhr.life/payments/doku/webhook'
                && in_array('QRIS', $data['payment']['payment_method_types'], true)
                && in_array('VIRTUAL_ACCOUNT_BCA', $data['payment']['payment_method_types'], true)
                && in_array('EMONEY_DANA', $data['payment']['payment_method_types'], true);
        });

        $this->assertDatabaseHas('payments', [
            'booking_id' => $this->booking->id,
            'gateway' => PaymentGateway::DOKU->value,
            'gateway_order_id' => $this->booking->kode,
            'gateway_request_id' => DokuIdempotencyKey::forCharge($this->booking),
            'gateway_status' => 'pending',
            'jumlah' => 1_000_000,
        ]);
    });

    it('menolak create charge bila rekening settlement mitra belum terverifikasi', function () {
        fakeDokuCheckoutSuccess();

        expect(fn () => app(PaymentChargeService::class)->createForBooking($this->booking))
            ->toThrow(RuntimeException::class);

        Http::assertNothingSent();
    });

    it('mengembalikan charge yang sudah ada tanpa request baru', function () {
        MitraBankAccount::create([
            'user_id' => $this->mitra->id,
            'tipe' => 'bank',
            'nama_penyedia' => 'BCA',
            'nomor_rekening' => '1234567890',
            'nama_pemilik' => 'Mitra Travel',
            'is_primary' => true,
            'settlement_bank_account_id' => 'SBA-0001-123',
            'settlement_verified_at' => now(),
        ]);

        fakeDokuCheckoutSuccess();

        $service = app(PaymentChargeService::class);

        $first = $service->createForBooking($this->booking);
        $second = $service->createForBooking($this->booking);

        expect($second->checkoutUrl)->toBe($first->checkoutUrl);

        Http::assertSentCount(1);
        expect(Payment::where('booking_id', $this->booking->id)->count())->toBe(1);
    });
});
