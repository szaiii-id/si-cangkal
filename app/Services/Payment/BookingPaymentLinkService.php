<?php

namespace App\Services\Payment;

use App\Data\Xendit\XenditSessionResult;
use App\Enums\PaymentGateway;
use App\Mail\BookingPaymentLinkMail;
use App\Models\Booking;
use App\Models\BookingPaymentLink;
use App\Models\Payment;
use App\Services\Invoice\Contracts\InvoiceServiceInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Mengelola token capability untuk tautan pembayaran publik.
 *
 * Token dipakai ulang selama payment masih pending, dan dirotasi saat
 * sesi Xendit sebelumnya sudah berakhir.
 */
final class BookingPaymentLinkService
{
    public function __construct(
        private readonly InvoiceServiceInterface $invoice,
    ) {}

    public function forBooking(Booking $booking, XenditSessionResult $session): BookingPaymentLink
    {
        $expiresAt = $this->expiresAtFromSession($session);

        $hasPendingPayment = Payment::query()
            ->where('booking_id', $booking->id)
            ->where('gateway', PaymentGateway::XENDIT)
            ->where('gateway_status', 'pending')
            ->exists();

        if ($hasPendingPayment) {
            /** @var BookingPaymentLink|null $active */
            $active = BookingPaymentLink::query()
                ->where('booking_id', $booking->id)
                ->active()
                ->latest()
                ->first();

            if ($active !== null) {
                $active->update(['expires_at' => $expiresAt]);

                return $active->refresh();
            }
        }

        $this->revokeActive($booking);

        /** @var BookingPaymentLink $link */
        $link = BookingPaymentLink::query()->create([
            'booking_id' => $booking->id,
            'token' => (string) Str::uuid(),
            'expires_at' => $expiresAt,
        ]);

        $this->sendEmail($link);

        return $link;
    }

    public function revokeActive(Booking $booking): void
    {
        BookingPaymentLink::query()
            ->where('booking_id', $booking->id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    public function findByToken(string $token): ?BookingPaymentLink
    {
        /** @var BookingPaymentLink|null $link */
        $link = BookingPaymentLink::query()
            ->where('token', $token)
            ->active()
            ->with('booking')
            ->first();

        return $link;
    }

    private function sendEmail(BookingPaymentLink $link): void
    {
        $booking = $link->booking;
        $email = $booking->client?->email ?? '';

        if ($booking->sumber !== 'manual' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return;
        }

        try {
            Mail::to($email)->queue(new BookingPaymentLinkMail(
                url: route('payments.public', ['token' => $link->token]),
                booking: $booking,
                invoicePdf: $this->invoice->pdfForBooking($booking),
            ));
        } catch (Throwable $e) {
            Log::warning('BookingPaymentLinkService: gagal mengantrekan email', [
                'booking_id' => $booking->id,
                'link_id' => $link->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function expiresAtFromSession(XenditSessionResult $session): ?Carbon
    {
        $raw = data_get($session->payload, 'expires_at');

        if (! is_string($raw) || $raw === '') {
            return null;
        }

        try {
            return Carbon::parse($raw);
        } catch (Throwable) {
            return null;
        }
    }
}
