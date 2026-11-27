<?php

namespace App\Mail;

use App\Models\Booking;
use App\Services\Invoice\Contracts\InvoiceServiceInterface;
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
     * PDF dibuat saat email dikirim, bukan disimpan di payload job.
     *
     * Mailable ini ber-ShouldQueue: menyimpan byte PDF mentah di properti
     * membuat json_encode gagal (UTF-8 tidak valid) ketika job ditulis ke
     * queue database, sehingga email tidak pernah terkirim.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(
                fn (): string => app(InvoiceServiceInterface::class)
                    ->pdfForBooking($this->booking),
                'Invoice-'.$this->booking->kode.'.pdf',
            )->withMime('application/pdf'),
        ];
    }
}
