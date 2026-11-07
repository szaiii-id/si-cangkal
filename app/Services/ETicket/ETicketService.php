<?php

namespace App\Services\ETicket;

use App\Models\ETicket;
use App\Models\Trip;
use App\Support\AuditLogger;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * Service pembuatan E-Ticket (QR + PDF).
 *
 * Perubahan dari versi lama:
 * - File disimpan di disk `local` (S-10, B-01), bukan `public`.
 * - Audit log saat E-Ticket berhasil di-generate (S-16).
 * - `generateForTrip()` idempoten lewat `updateOrCreate` (tidak berubah).
 *
 * Catatan akses file:
 * - QR dan PDF TIDAK bisa diakses lewat URL `/storage/...`.
 * - Client mengunduh lewat route privat `client.e-tickets.download`
 *   yang memeriksa Policy `BookingPolicy@downloadETicket`.
 * - QR ditampilkan di PDF sebagai base64, jadi QR tidak perlu
 *   diakses terpisah oleh client.
 */
final class ETicketService
{
    /**
     * Disk penyimpanan E-Ticket.
     *
     * Wajib `local` agar tidak terekspos publik (S-10, B-01).
     */
    public const DISK = 'local';

    /**
     * Direktori penyimpanan E-Ticket di dalam disk.
     */
    public const DIRECTORY = 'e-tickets';

    /**
     * Generate E-Ticket untuk sebuah trip.
     *
     * Idempoten: `updateOrCreate` pada `booking_id` mencegah duplikat
     * saat job di-retry.
     *
     * @throws \Throwable
     */
    public function generateForTrip(Trip $trip): ETicket
    {
        $trip->loadMissing(['booking.client', 'package', 'booking.participants']);

        $uuid = (string) Str::uuid();
        $qrUrl = url("/e-tickets/{$uuid}");

        /*
         * Stub PHPStan menganggap QrCode::generate() mengembalikan
         * HtmlString|string, padahal untuk format 'png' hasilnya
         * SELALU binary string. Type assertion @var string aman dan
         * menghilangkan error class.notFound.
         *
         * @var string $qrContent
         */
        $qrContent = QrCode::format('png')->size(300)->generate($qrUrl);

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

        Storage::disk(self::DISK)->put($pdfPath, $pdf->output());

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

    /**
     * Cari E-Ticket berdasarkan UUID (untuk route download privat).
     */
    public function findByUuid(string $uuid): ?ETicket
    {
        return ETicket::where('uuid', $uuid)
            ->with(['trip.package', 'booking.client', 'booking.participants'])
            ->first();
    }

    /**
     * Ambil path absolut file PDF E-Ticket di disk `local`.
     *
     * @return string|null Null bila file tidak ditemukan di disk.
     */
    public function pdfAbsolutePath(ETicket $ticket): ?string
    {
        $disk = Storage::disk(self::DISK);

        if (! $disk->exists($ticket->pdf_path)) {
            return null;
        }

        return $disk->path($ticket->pdf_path);
    }
}