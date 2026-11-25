<?php

use App\Data\Xendit\XenditSessionResult;
use App\Enums\BookingStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentMetode;
use App\Models\Booking;
use App\Models\BookingPaymentLink;
use App\Models\Payment;
use App\Services\Payment\BookingPaymentLinkService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function sessionResultForLink(string $expiresAt): XenditSessionResult
{
    return new XenditSessionResult(
        sessionId: 'ps-1234567890123456789012345',
        checkoutUrl: 'https://dev.xen.to/abc123',
        payload: ['expires_at' => $expiresAt],
    );
}

function pendingPaymentFor(Booking $booking, string $status = 'pending'): Payment
{
    return Payment::create([
        'booking_id' => $booking->id,
        'jumlah' => 1_000_000,
        'metode' => PaymentMetode::XENDIT,
        'gateway' => PaymentGateway::XENDIT,
        'gateway_order_id' => $booking->kode,
        'gateway_status' => $status,
        'gateway_payload' => [],
    ]);
}

describe('BookingPaymentLinkService', function () {
    it('membuat token dengan expires_at dari sesi Xendit', function () {
        $booking = Booking::factory()->create(['status' => BookingStatus::PENDING_PAYMENT]);
        pendingPaymentFor($booking);

        $expiresAt = now()->addMinutes(30)->toIso8601String();
        $link = app(BookingPaymentLinkService::class)->forBooking(
            $booking,
            sessionResultForLink($expiresAt),
        );

        expect($link->token)->not->toBeEmpty()
            ->and($link->expires_at->toIso8601String())->toBe($expiresAt)
            ->and($link->revoked_at)->toBeNull();
    });

    it('memakai ulang token saat pembayaran masih pending', function () {
        $booking = Booking::factory()->create(['status' => BookingStatus::PENDING_PAYMENT]);
        pendingPaymentFor($booking);

        $service = app(BookingPaymentLinkService::class);
        $first = $service->forBooking($booking, sessionResultForLink(now()->addMinutes(30)->toIso8601String()));
        $second = $service->forBooking($booking, sessionResultForLink(now()->addMinutes(25)->toIso8601String()));

        expect($second->id)->toBe($first->id)
            ->and(BookingPaymentLink::where('booking_id', $booking->id)->count())->toBe(1);
    });

    it('memakai expired_at booking bila sesi tidak mengirim expires_at', function () {
        $expiredAt = now()->addHours(2);
        $booking = Booking::factory()->create([
            'status' => BookingStatus::PENDING_PAYMENT,
            'expired_at' => $expiredAt,
        ]);
        pendingPaymentFor($booking);

        $link = app(BookingPaymentLinkService::class)->forBooking(
            $booking,
            new XenditSessionResult('ps-1', 'https://dev.xen.to/x', [], null),
        );

        expect($link->expires_at->toIso8601String())->toBe($expiredAt->toIso8601String());
    });

    it('merotasi token saat sesi lama sudah expired', function () {
        $booking = Booking::factory()->create(['status' => BookingStatus::PENDING_PAYMENT]);
        pendingPaymentFor($booking, 'EXPIRED');

        $service = app(BookingPaymentLinkService::class);
        $old = $service->forBooking($booking, sessionResultForLink(now()->subMinute()->toIso8601String()));
        $new = $service->forBooking($booking, sessionResultForLink(now()->addMinutes(30)->toIso8601String()));

        expect($new->id)->not->toBe($old->id)
            ->and($old->fresh()->revoked_at)->not->toBeNull();
    });
});
