<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\BookingPaymentLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function activeLinkFor(Booking $booking): BookingPaymentLink
{
    return BookingPaymentLink::create([
        'booking_id' => $booking->id,
        'token' => (string) Str::uuid(),
        'expires_at' => now()->addMinutes(30),
    ]);
}

describe('pencabutan token otomatis', function () {
    it('mencabut token pada status akhir pembayaran', function (BookingStatus $status) {
        $booking = Booking::factory()->create(['status' => BookingStatus::PENDING_PAYMENT]);
        $link = activeLinkFor($booking);

        $booking->update(['status' => $status]);

        expect($link->fresh()->revoked_at)->not->toBeNull();
    })->with([
        BookingStatus::PAYMENT_VERIFIED,
        BookingStatus::CONFIRMED,
        BookingStatus::IN_PROGRESS,
        BookingStatus::COMPLETED,
        BookingStatus::CANCELLED,
    ]);

    it('tidak mencabut token saat status tetap menunggu pembayaran', function () {
        $booking = Booking::factory()->create(['status' => BookingStatus::PENDING_PAYMENT]);
        $link = activeLinkFor($booking);

        $booking->update(['catatan' => 'Diperbarui']);

        expect($link->fresh()->revoked_at)->toBeNull();
    });
});
