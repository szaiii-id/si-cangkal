<?php

namespace App\Services\Invoice;

use App\Models\Booking;
use App\Services\Invoice\Contracts\InvoiceServiceInterface;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Membuat invoice PDF secara on-demand supaya datanya selalu mengikuti
 * booking terbaru dan tidak perlu disimpan.
 */
final class InvoiceService implements InvoiceServiceInterface
{
    public function pdfForBooking(Booking $booking): string
    {
        $booking->loadMissing(['package', 'client', 'mitra', 'participants', 'payments']);

        return (string) Pdf::loadView('pdf.invoice', [
            'booking' => $booking,
            'packageName' => $booking->package->nama,
            'mitraName' => $booking->mitra->name,
            'clientName' => $booking->client->name,
            'clientEmail' => $booking->client->email,
        ])->setPaper('a4')->output();
    }

    public function fileName(Booking $booking): string
    {
        return 'Invoice-'.$booking->kode.'.pdf';
    }
}
