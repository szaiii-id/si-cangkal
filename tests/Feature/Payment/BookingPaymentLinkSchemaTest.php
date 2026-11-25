<?php

use App\Models\Booking;
use App\Models\BookingPaymentLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

describe('schema tautan pembayaran publik', function () {
    it('bookings memiliki kolom sumber', function () {
        expect(Schema::hasColumn('bookings', 'sumber'))->toBeTrue()
            ->and(Booking::factory()->create()->sumber)->toBe('aplikasi');
    });

    it('tabel booking_payment_links memiliki kolom yang dibutuhkan', function () {
        expect(Schema::hasColumns('booking_payment_links', [
            'id',
            'booking_id',
            'token',
            'expires_at',
            'revoked_at',
        ]))->toBeTrue();
    });

    it('hanya mengembalikan token yang masih aktif', function () {
        $booking = Booking::factory()->create();

        $active = BookingPaymentLink::create([
            'booking_id' => $booking->id,
            'token' => (string) Str::uuid(),
            'expires_at' => now()->addMinutes(30),
        ]);

        BookingPaymentLink::create([
            'booking_id' => $booking->id,
            'token' => (string) Str::uuid(),
            'expires_at' => now()->addMinutes(30),
            'revoked_at' => now(),
        ]);

        expect(BookingPaymentLink::active()->pluck('id')->all())->toBe([$active->id]);
    });
});
