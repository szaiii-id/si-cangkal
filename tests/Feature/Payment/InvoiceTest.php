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

function createInvoiceLink(array $overrides = []): array
{
    $booking = Booking::factory()->create([
        'status' => BookingStatus::PENDING_PAYMENT,
        'sumber' => 'manual',
        'total_harga' => 1_500_000,
    ]);

    Payment::create([
        'booking_id' => $booking->id,
        'jumlah' => 1_500_000,
        'metode' => PaymentMetode::XENDIT,
        'gateway' => PaymentGateway::XENDIT,
        'gateway_order_id' => $booking->kode,
        'gateway_status' => 'pending',
        'gateway_payload' => [],
    ]);

    $link = BookingPaymentLink::create([
        'booking_id' => $booking->id,
        'token' => (string) Str::uuid(),
        'expires_at' => now()->addMinutes(30),
        ...$overrides,
    ]);

    return [$booking, $link];
}

describe('invoice PDF', function () {
    it('mengunduh invoice untuk token yang aktif', function () {
        [$booking, $link] = createInvoiceLink();

        $response = $this->get('/bayar/'.$link->token.'/invoice');

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'attachment; filename=Invoice-'.$booking->kode.'.pdf');

        expect(str_starts_with($response->getContent(), '%PDF'))->toBeTrue();
    });

    it('menolak token yang sudah dicabut', function () {
        [, $link] = createInvoiceLink(['revoked_at' => now()]);

        $this->get('/bayar/'.$link->token.'/invoice')->assertNotFound();
    });
});
