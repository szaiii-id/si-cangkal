<?php

use App\Enums\BookingStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentMetode;
use App\Models\Booking;
use App\Models\MitraBankAccount;
use App\Models\Package;
use App\Models\Payment;
use App\Models\User;
use App\Services\Notification\Contracts\NotificationDispatcherInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Mockery;

uses(RefreshDatabase::class);

/*
|==========================================================================
| Payment link opsional untuk booking manual mitra
|==========================================================================
|
| Mitra membuat & menyalin link checkout DOKU untuk dikirim ke client.
| Upload bukti transfer manual tetap tersedia sebagai fallback.
|
*/

beforeEach(function () {
    config([
        'payment.doku.client_id' => 'MCH-SANDBOX-001',
        'payment.doku.secret_key' => 'secret-sandbox',
        'payment.doku.notification_url' => 'https://tunnel.lhr.life/payments/doku/webhook',
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'api-sandbox.doku.com/checkout/v1/payment' => Http::response([
            'message' => ['SUCCESS'],
            'response' => [
                'order' => ['session_id' => 'sess-1'],
                'payment' => ['url' => 'https://sandbox.doku.com/checkout-link-v2/link123'],
            ],
        ]),
    ]);

    $this->notifier = Mockery::mock(NotificationDispatcherInterface::class);
    $this->notifier->shouldReceive('send')->byDefault()->andReturnNull();
    $this->app->instance(NotificationDispatcherInterface::class, $this->notifier);

    $this->mitra = User::factory()->mitra()->create(['must_change_password' => false]);
    $this->otherMitra = User::factory()->mitra()->create(['must_change_password' => false]);
    $this->client = User::factory()->client()->create(['must_change_password' => false]);

    $package = Package::factory()->forMitra($this->mitra)->aktif()->create();

    $this->booking = Booking::factory()
        ->forPackage($package)
        ->forClient($this->client)
        ->create([
            'mitra_id' => $this->mitra->id,
            'status' => BookingStatus::PENDING_PAYMENT,
            'total_harga' => 1_000_000,
            'expired_at' => now()->addHours(2),
        ]);

    MitraBankAccount::create([
        'user_id' => $this->otherMitra->id,
        'tipe' => 'bank',
        'nama_penyedia' => 'BRI',
        'nomor_rekening' => '9876543210',
        'nama_pemilik' => 'Mitra Lain',
        'is_primary' => true,
        'settlement_bank_account_id' => 'SBA-0002-456',
        'settlement_verified_at' => now(),
    ]);

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
});

describe('POST /mitra/bookings/{booking}/payment-link', function () {
    it('mitra pemilik bisa membuat link pembayaran', function () {
        $response = $this->actingAs($this->mitra)
            ->postJson("/mitra/bookings/{$this->booking->id}/payment-link");

        $response->assertOk()
            ->assertJsonPath('url', 'https://sandbox.doku.com/checkout-link-v2/link123')
            ->assertJsonStructure(['url', 'expires_at']);

        $this->assertDatabaseHas('payments', [
            'booking_id' => $this->booking->id,
            'gateway' => PaymentGateway::DOKU->value,
            'gateway_status' => 'pending',
        ]);
    });

    it('mitra bukan pemilik ditolak', function () {
        $this->actingAs($this->otherMitra)
            ->postJson("/mitra/bookings/{$this->booking->id}/payment-link")
            ->assertForbidden();

        Http::assertNothingSent();
    });

    it('booking yang bukan pending ditolak', function () {
        $this->booking->update(['status' => BookingStatus::CONFIRMED]);

        $this->actingAs($this->mitra)
            ->postJson("/mitra/bookings/{$this->booking->id}/payment-link")
            ->assertForbidden();

        Http::assertNothingSent();
    });

    it('mengirim notifikasi in-app ke client dengan url link', function () {
        $this->notifier->shouldReceive('send')
            ->once()
            ->with(
                user: Mockery::on(fn ($u) => $u->id === $this->client->id),
                tipe: 'payment_link_created',
                judul: Mockery::type('string'),
                pesan: Mockery::type('string'),
                data: Mockery::on(fn ($d) => isset($d['url'])
                    && str_contains((string) $d['url'], 'checkout-link-v2')),
            );

        $this->actingAs($this->mitra)
            ->postJson("/mitra/bookings/{$this->booking->id}/payment-link")
            ->assertOk();
    });

    it('regenerasi link memakai Request-Id baru', function () {
        Payment::create([
            'booking_id' => $this->booking->id,
            'jumlah' => 1_000_000,
            'metode' => PaymentMetode::DOKU,
            'gateway' => PaymentGateway::DOKU,
            'gateway_order_id' => $this->booking->kode,
            'gateway_request_id' => 'charge-1',
            'gateway_status' => 'EXPIRED',
        ]);

        $this->actingAs($this->mitra)
            ->postJson("/mitra/bookings/{$this->booking->id}/payment-link")
            ->assertOk();

        Http::assertSent(function (Request $request): bool {
            return $request->hasHeader('Request-Id')
                && $request->header('Request-Id')[0] !== 'charge-1';
        });
    });
});
