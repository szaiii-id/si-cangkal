<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Services\ETicket\ETicketService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Controller unduh E-Ticket untuk client.
 *
 * Perubahan dari versi lama:
 * - File E-Ticket sekarang ada di disk `local` (S-10, B-01).
 * - Otorisasi eksplisit via `BookingPolicy@downloadETicket` (S-02).
 * - Response `StreamedResponse` (bukan `BinaryFileResponse`)
 *   agar konsisten dengan pola disk abstrak.
 *
 * Catatan:
 * - Method `download` memakai `ETicketService::pdfAbsolutePath()`
 *   untuk memvalidasi keberadaan file sebelum stream.
 * - Bila `ETicket` tidak ditemukan → 404.
 * - Bila file hilang dari disk (edge case) → 404 dengan pesan
 *   redirect ramah (bukan stack trace).
 */
final class ETicketController extends Controller
{
    public function __construct(
        private readonly ETicketService $eTicketService,
    ) {}

    public function download(Request $request, string $uuid): StreamedResponse|RedirectResponse
    {
        $ticket = $this->eTicketService->findByUuid($uuid);

        abort_if($ticket === null, 404);

        if ($ticket->booking === null) {
            abort(404);
        }

        $this->authorize('downloadETicket', $ticket->booking);

        $absolutePath = $this->eTicketService->pdfAbsolutePath($ticket);

        if ($absolutePath === null) {
            abort(404, 'File E-Ticket tidak ditemukan. Silakan hubungi mitra.');
        }

        $tripKode = $ticket->trip?->kode ?? 'unknown';

        return response()->streamDownload(
            static function () use ($absolutePath): void {
                $stream = fopen($absolutePath, 'rb');

                if ($stream === false) {
                    return;
                }

                fpassthru($stream);
                fclose($stream);
            },
            "e-ticket-{$tripKode}.pdf",
            [
                'Content-Type' => 'application/pdf',
            ],
        );
    }
}