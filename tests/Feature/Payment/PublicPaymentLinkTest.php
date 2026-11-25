<?php

use App\Enums\BookingStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentMetode;
use App\Models\Booking;
use App\Models\BookingPaymentLink;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function createPublicLink(array $linkOverrides = [], string $paymentStatus = 'pending'): array
{
    $booking = Booking::factory()->create([
        'status' => BookingStatus::PENDING_PAYMENT,
        'sumber' => 'manual',
    ]);

    Payment::create([
        'booking_id' => $booking->id,
        'jumlah' => 1_500_000,
        'metode' => PaymentMetode::XENDIT,
        'gateway' => PaymentGateway::XENDIT,
        'gateway_order_id' => $booking->kode,
        'gateway_status' => $paymentStatus,
        'gateway_payload' => [
            'payment_link_url' => 'https://checkout-staging.xendit.co/sessions/ps-123',
            'expires_at' => now()->addMinutes(30)->toIso8601String(),
        ],
    ]);

    $link = BookingPaymentLink::create([
        'booking_id' => $booking->id,
        'token' => (string) Str::uuid(),
        'expires_at' => now()->addMinutes(30),
        ...$linkOverrides,
    ]);

    return [$booking, $link];
}

describe('halaman pembayaran publik', function () {
    it('menampilkan ringkasan dan menyamarkan nama client', function () {
        [$booking, $link] = createPublicLink();

        $response = $this->get('/bayar/'.$link->token);

        $response->assertOk()
            ->assertSee($booking->kode)
            ->assertSee('Bayar dengan Xendit')
            ->assertDontSee($booking->client->email)
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
    });

    it('menolak token yang tidak ada, dicabut, atau kedaluwarsa', function () {
        $this->get('/bayar/'.Str::uuid())->assertNotFound();

        [, $revoked] = createPublicLink(['revoked_at' => now()]);
        $this->get('/bayar/'.$revoked->token)->assertNotFound();

        [, $expired] = createPublicLink(['expires_at' => now()->subMinute()]);
        $this->get('/bayar/'.$expired->token)->assertNotFound();
    });

    it('menyembunyikan tombol bayar bila pembayaran tidak lagi pending', function () {
        [, $link] = createPublicLink([], 'SUCCESS');

        $this->get('/bayar/'.$link->token)
            ->assertOk()
            ->assertDontSee('Bayar dengan Xendit');
    });
});
