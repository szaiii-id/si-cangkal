<?php

use App\Data\Xendit\XenditSessionResult;
use App\Enums\BookingStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentMetode;
use App\Mail\BookingPaymentLinkMail;
use App\Models\Booking;
use App\Models\Payment;
use App\Services\Payment\BookingPaymentLinkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\SendQueuedMailable;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

uses(RefreshDatabase::class);

function bookingForMail(string $sumber): Booking
{
    $booking = Booking::factory()->create([
        'status' => BookingStatus::PENDING_PAYMENT,
        'sumber' => $sumber,
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

    return $booking;
}

function sessionForMail(): XenditSessionResult
{
    return new XenditSessionResult(
        sessionId: 'ps-1234567890123456789012345',
        checkoutUrl: 'https://dev.xen.to/abc123',
        payload: ['expires_at' => now()->addMinutes(30)->toIso8601String()],
    );
}

beforeEach(function () {
    Mail::fake();
});

describe('email otomatis link pembayaran', function () {
    it('mailable dapat diserialisasi ke payload queue database', function () {
        $booking = bookingForMail('manual');

        $mail = new BookingPaymentLinkMail(
            url: 'https://example.test/pay',
            booking: $booking,
        );

        // Queue database menulis json_encode(['job' => serialize($job)]).
        // Byte PDF mentah di payload mailable membuat JSON gagal, sehingga
        // email tidak pernah dikirim meski Mail::fake() melihatnya lolos.
        $payload = json_encode(
            ['job' => serialize(new SendQueuedMailable($mail))],
            JSON_THROW_ON_ERROR,
        );

        expect($payload)->toBeString();
    });

    it('mengirim email saat token baru dibuat untuk booking manual', function () {
        $booking = bookingForMail('manual');

        app(BookingPaymentLinkService::class)->forBooking($booking, sessionForMail());

        Mail::assertQueued(BookingPaymentLinkMail::class, 1);
        Mail::assertQueued(BookingPaymentLinkMail::class, function (BookingPaymentLinkMail $mail) use ($booking): bool {
            $hasInvoice = collect($mail->attachments())->contains(
                static fn ($attachment): bool => $attachment->as === 'Invoice-'.$booking->kode.'.pdf',
            );

            return $mail->hasTo($booking->client->email) && $hasInvoice;
        });
    });

    it('tidak mengirim ulang email saat token dipakai ulang', function () {
        $booking = bookingForMail('manual');
        $service = app(BookingPaymentLinkService::class);

        $service->forBooking($booking, sessionForMail());
        $service->forBooking($booking, sessionForMail());

        Mail::assertQueued(BookingPaymentLinkMail::class, 1);
    });

    it('mengirim email baru saat token dirotasi', function () {
        $booking = bookingForMail('manual');
        $payment = $booking->payments()->latest()->firstOrFail();
        $payment->update(['gateway_status' => 'EXPIRED']);

        $service = app(BookingPaymentLinkService::class);
        $service->forBooking($booking, sessionForMail());
        $service->forBooking($booking, sessionForMail());

        Mail::assertQueued(BookingPaymentLinkMail::class, 2);
    });

    it('tidak menggagalkan pembuatan link bila email gagal', function () {
        Mail::shouldReceive('to')->andThrow(new RuntimeException('mail down'));

        $booking = bookingForMail('manual');
        $link = app(BookingPaymentLinkService::class)
            ->forBooking($booking, sessionForMail());

        expect($link->token)->not->toBeEmpty();
    });

    it('tidak mengirim email untuk booking aplikasi', function () {
        $booking = bookingForMail('aplikasi');

        app(BookingPaymentLinkService::class)->forBooking($booking, sessionForMail());

        Mail::assertNothingQueued();
    });
});
