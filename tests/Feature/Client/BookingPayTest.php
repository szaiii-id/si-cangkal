<?php

use App\Enums\BookingStatus;
use App\Enums\PaymentGateway;
use App\Models\Booking;
use App\Models\BookingPaymentLink;
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
| POST /client/bookings/{booking}/pay mengembalikan URL Payment Session
| Xendit. Transfer manual tetap tersedia sebagai opsi terpisah.
|
*/

beforeEach(function () {
    config([
        'payment.xendit.secret_key' => 'xnd_development_test_key',
        'payment.xendit.callback_token' => 'callback-token-rahasia',
    ]);

    Http::preventStrayRequests();

    Http::fake([
        'https://api.xendit.co/sessions' => Http::response([
            'payment_session_id' => 'ps-1234567890123456789012345',
            'payment_link_url' => 'https://dev.xen.to/abc123',
            'status' => 'ACTIVE',
        ], 201),
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
});

describe('POST /client/bookings/{booking}/pay', function () {
    it('mengembalikan URL checkout Xendit untuk pemilik booking', function () {
        $response = $this->actingAs($this->client)
            ->postJson("/client/bookings/{$this->booking->id}/pay");

        // Tanpa header X-Inertia, Inertia::location menjadi redirect 302.
        $response->assertRedirect('https://dev.xen.to/abc123');

        $this->assertDatabaseHas('payments', [
            'booking_id' => $this->booking->id,
            'gateway' => PaymentGateway::XENDIT->value,
            'gateway_status' => 'pending',
            'gateway_request_id' => 'ps-1234567890123456789012345',
        ]);

        expect(BookingPaymentLink::query()
            ->where('booking_id', $this->booking->id)
            ->active()
            ->exists())->toBeTrue();
    });

    it('memakai ulang session yang masih pending', function () {
        $this->actingAs($this->client)->postJson("/client/bookings/{$this->booking->id}/pay");
        $this->actingAs($this->client)->postJson("/client/bookings/{$this->booking->id}/pay");

        expect(Payment::where('booking_id', $this->booking->id)->count())->toBe(1);

        Http::assertSentCount(1);
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
