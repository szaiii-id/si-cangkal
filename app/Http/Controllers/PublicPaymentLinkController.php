<?php

namespace App\Http\Controllers;

use App\Enums\BookingStatus;
use App\Enums\PaymentGateway;
use App\Models\Payment;
use App\Services\Invoice\Contracts\InvoiceServiceInterface;
use App\Services\Payment\BookingPaymentLinkService;
use App\Support\PiiMask;
use Illuminate\Http\Response;

/**
 * Halaman pembayaran publik untuk booking manual.
 *
 * Memakai token capability seperti E-Ticket: tanpa login, PII disamarkan,
 * dan tidak boleh diindeks/disimpan cache bersama.
 */
final class PublicPaymentLinkController extends Controller
{
    public function __construct(
        private readonly BookingPaymentLinkService $paymentLink,
    ) {}

    public function show(string $token): Response
    {
        $link = $this->paymentLink->findByToken($token);
        $booking = $link?->booking;

        abort_if($link === null || $booking === null, 404);

        $payment = Payment::query()
            ->where('booking_id', $booking->id)
            ->where('gateway', PaymentGateway::XENDIT)
            ->latest()
            ->first();

        $checkoutUrl = $booking->status === BookingStatus::PENDING_PAYMENT
            && $payment !== null
            && $payment->gateway_status === 'pending'
            ? (string) data_get($payment->gateway_payload, 'payment_link_url', '')
            : '';

        return response()
            ->view('payment-links.show', [
                'token' => $token,
                'bookingKode' => $booking->kode,
                'namaPaket' => $booking->package->nama,
                'tanggalKeberangkatan' => $booking->tanggal_keberangkatan
                    ?->translatedFormat('d F Y') ?? '-',
                'jumlahPeserta' => $booking->jumlah_peserta,
                'totalHarga' => (float) $booking->total_harga,
                'namaClient' => PiiMask::name($booking->client?->name),
                'statusBooking' => $booking->status->label(),
                'canPay' => $checkoutUrl !== '',
                'checkoutUrl' => $checkoutUrl,
                'expiresAt' => $link->expires_at?->translatedFormat('d M Y H:i') ?? '-',
            ])
            ->header('X-Robots-Tag', 'noindex, nofollow, noarchive')
            ->header('Referrer-Policy', 'no-referrer')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    }

    public function invoice(string $token, InvoiceServiceInterface $invoices): Response
    {
        $link = $this->paymentLink->findByToken($token);
        $booking = $link?->booking;

        abort_if($link === null || $booking === null, 404);

        return response($invoices->pdfForBooking($booking), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename='.$invoices->fileName($booking),
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
            'Referrer-Policy' => 'no-referrer',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }
}
