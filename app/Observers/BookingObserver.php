<?php

namespace App\Observers;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Services\Payment\BookingPaymentLinkService;

/**
 * Mencabut tautan pembayaran publik begitu booking tidak lagi menunggu
 * pembayaran. Satu titik untuk semua jalur: webhook, verifikasi manual,
 * refund, pembatalan, dan expiry scheduler.
 */
final class BookingObserver
{
    /**
     * @var list<BookingStatus>
     */
    private const REVOKE_STATUSES = [
        BookingStatus::PAYMENT_VERIFIED,
        BookingStatus::CONFIRMED,
        BookingStatus::IN_PROGRESS,
        BookingStatus::COMPLETED,
        BookingStatus::CANCELLED,
    ];

    public function __construct(
        private readonly BookingPaymentLinkService $paymentLink,
    ) {}

    public function updated(Booking $booking): void
    {
        if (! $booking->wasChanged('status')) {
            return;
        }

        if (in_array($booking->status, self::REVOKE_STATUSES, true)) {
            $this->paymentLink->revokeActive($booking);
        }
    }
}
