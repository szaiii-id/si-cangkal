<?php

namespace App\Services\ETicket;

use App\Models\ETicket;
use App\Models\Trip;
use App\Services\ETicket\Contracts\ETicketServiceInterface;
use App\Support\AuditLogger;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

final class ETicketService implements ETicketServiceInterface
{
    public const DISK = 'local';

    public const DIRECTORY = 'e-tickets';

    public function generateForTrip(Trip $trip): ETicket
    {
        $trip->loadMissing(['booking.client', 'package', 'booking.participants']);

        $uuid = (string) Str::uuid();
        // URL publik untuk verifikasi tiket saat QR di-scan. Harus memakai
        // nama route agar tetap sinkron bila path berubah.
        $qrUrl = route('e-tickets.verify', ['uuid' => $uuid]);

        $qrContent = (string) QrCode::format('png')->size(300)->generate($qrUrl);

        $qrPath = self::DIRECTORY."/qr-{$uuid}.png";
        Storage::disk(self::DISK)->put($qrPath, $qrContent);

        $pdfPath = self::DIRECTORY."/ticket-{$uuid}.pdf";
        $pdf = Pdf::loadView('pdf.e-ticket', [
            'trip' => $trip,
            'booking' => $trip->booking,
            'qrPath' => Storage::disk(self::DISK)->path($qrPath),
            'qrContent' => $qrContent,
            'uuid' => $uuid,
        ])->setPaper('a4');

        $pdfOutput = $pdf->output();
        Storage::disk(self::DISK)->put($pdfPath, (string) $pdfOutput);

        $ticket = ETicket::updateOrCreate(
            ['booking_id' => $trip->booking_id],
            [
                'uuid' => $uuid,
                'trip_id' => $trip->id,
                'qr_code' => $qrPath,
                'pdf_path' => $pdfPath,
                'generated_at' => now(),
            ],
        );

        AuditLogger::log('eticket.generated', [
            'actor_id' => null,
            'actor_role' => 'system',
            'ticket_id' => $ticket->id,
            'ticket_uuid' => $ticket->uuid,
            'trip_id' => $trip->id,
            'trip_kode' => $trip->kode,
            'booking_id' => $trip->booking_id,
            'pdf_path' => $pdfPath,
        ]);

        Log::info('ETicketService: E-Ticket berhasil dibuat', [
            'ticket_id' => $ticket->id,
            'trip_id' => $trip->id,
            'booking_id' => $trip->booking_id,
        ]);

        return $ticket;
    }

    public function findByUuid(string $uuid): ?ETicket
    {
        /** @var ETicket|null $ticket */
        $ticket = ETicket::where('uuid', $uuid)
            ->with(['trip.package', 'booking.client', 'booking.participants'])
            ->first();

        return $ticket;
    }

    public function pdfAbsolutePath(ETicket $ticket): ?string
    {
        if (empty($ticket->pdf_path)) {
            return null;
        }

        $disk = Storage::disk(self::DISK);

        if (! $disk->exists($ticket->pdf_path)) {
            return null;
        }

        return $disk->path($ticket->pdf_path);
    }
}
