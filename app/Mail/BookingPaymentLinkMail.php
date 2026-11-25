<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class BookingPaymentLinkMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $url,
        public readonly Booking $booking,
        public readonly string $invoicePdf,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Pembayaran booking '.$this->booking->kode,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.booking-payment-link',
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(
                fn (): string => $this->invoicePdf,
                'Invoice-'.$this->booking->kode.'.pdf',
                ['mime' => 'application/pdf'],
            ),
        ];
    }
}
