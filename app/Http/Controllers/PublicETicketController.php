<?php

namespace App\Http\Controllers;

use App\Enums\BookingStatus;
use App\Enums\TripStatus;
use App\Services\ETicket\ETicketService;
use App\Support\PiiMask;
use Illuminate\Http\Response;

/**
 * Halaman publik verifikasi E-Ticket (hasil scan QR).
 *
 * Halaman ini sengaja TIDAK memakai Inertia agar:
 * - Ringan dibuka dari kamera HP tanpa memuat bundle JS aplikasi.
 * - Tidak bergantung pada sesi login (petugas lapangan cukup scan QR).
 *
 * Prinsip keamanan:
 * - UUID E-Ticket bersifat rahasia (capability URL), bukan data personal.
 * - Hanya menampilkan data yang aman untuk ditunjukkan di lapangan:
 *   kode booking/trip, nama paket, tanggal, jumlah peserta, dan nama
 *   client yang disamarkan.
 * - Email, nomor HP, daftar peserta, dan tautan PDF TIDAK pernah dikirim
 *   ke halaman ini.
 * - Respons diberi header noindex/no-referrer/no-store agar tidak
 *   terindeks mesin pencari atau tersimpan di cache bersama.
 */
final class PublicETicketController extends Controller
{
    /**
     * Status booking yang dianggap tiketnya masih berlaku.
     *
     * `payment_verified` disertakan untuk kompatibilitas data legacy.
     *
     * @var list<BookingStatus>
     */
    private const VALID_BOOKING_STATUSES = [
        BookingStatus::PAYMENT_VERIFIED,
        BookingStatus::CONFIRMED,
        BookingStatus::IN_PROGRESS,
        BookingStatus::COMPLETED,
    ];

    public function show(string $uuid, ETicketService $eTicketService): Response
    {
        $ticket = $eTicketService->findByUuid($uuid);

        abort_if($ticket === null, 404);

        $booking = $ticket->booking;
        $trip = $ticket->trip;

        abort_if($booking === null || $trip === null, 404);

        $isValid = in_array($booking->status, self::VALID_BOOKING_STATUSES, true)
            && $trip->status !== TripStatus::CANCELLED;

        $snapshot = $trip->snapshot_package ?? [];

        return response()
            ->view('e-tickets.verify', [
                'isValid' => $isValid,
                'bookingKode' => $booking->kode,
                'tripKode' => $trip->kode,
                'namaPaket' => (string) ($snapshot['nama'] ?? $trip->package->nama ?? 'Paket Wisata'),
                'tanggalMulai' => $trip->tanggal_mulai->translatedFormat('d F Y'),
                'tanggalSelesai' => $trip->tanggal_selesai->translatedFormat('d F Y'),
                'jumlahPeserta' => $trip->jumlah_peserta,
                'namaClient' => PiiMask::name($booking->client?->name),
                'statusBooking' => $booking->status->label(),
                'statusTrip' => $trip->status->label(),
            ])
            ->header('X-Robots-Tag', 'noindex, nofollow, noarchive')
            ->header('Referrer-Policy', 'no-referrer')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    }
}
