<?php

use App\Enums\BookingStatus;
use App\Enums\PaymentGateway;
use App\Models\Booking;
use App\Models\MitraBankAccount;
use App\Models\Package;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/*
|==========================================================================
| Endpoint checkout client
|==========================================================================
|
| POST /client/bookings/{booking}/pay mengembalikan URL checkout DOKU.
| Transfer manual tetap tersedia sebagai opsi terpisah.
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
                'payment' => ['url' => 'https://sandbox.doku.com/checkout-link-v2/abc123'],
            ],
        ]),
    ]);

    $this->mitra = User::factory()->mitra()->create(['must_change_password' => false]);
    $this->client = User::factory()->client()->create(['must_change_password' => false]);
    $this->otherClient = User::factory()->client()->create(['must_change_password' => false]);

    $package = Package::factory()->forMitra($this->mitra)->aktif()->create();

    $this->booking = Booking::factory()
        ->forPackage($package)
        ->forClient($this->client)
        ->create([
            'mitra_id' => $this->mitra->id,
            'status' => BookingStatus::PENDING_PAYMENT,
            'total_harga' => 1_000_000,
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

describe('POST /client/bookings/{booking}/pay', function () {
    it('mengembalikan URL checkout untuk pemilik booking', function () {
        $response = $this->actingAs($this->client)
            ->postJson("/client/bookings/{$this->booking->id}/pay");

        // Tanpa header X-Inertia, Inertia::location menjadi redirect 302.
        // Saat dipanggil lewat router Inertia, response menjadi 409 + Location.
        $response->assertRedirect('https://sandbox.doku.com/checkout-link-v2/abc123');

        $this->assertDatabaseHas('payments', [
            'booking_id' => $this->booking->id,
            'gateway' => PaymentGateway::DOKU->value,
            'gateway_status' => 'pending',
        ]);
    });

    it('menolak client bukan pemilik booking', function () {
        $this->actingAs($this->otherClient)
            ->postJson("/client/bookings/{$this->booking->id}/pay")
            ->assertForbidden();

        Http::assertNothingSent();
    });

    it('menolak booking yang sudah dibayar', function () {
        $this->booking->update(['status' => BookingStatus::CONFIRMED]);

        $this->actingAs($this->client)
            ->postJson("/client/bookings/{$this->booking->id}/pay")
            ->assertStatus(422);

        Http::assertNothingSent();
        expect(Payment::where('booking_id', $this->booking->id)->count())->toBe(0);
    });
});
