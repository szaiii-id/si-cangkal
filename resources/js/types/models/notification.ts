import type { TripEloquent } from './trip';
import type { BookingEloquent } from './booking';

/**
 * Notifikasi In-App untuk semua user.
 *
 * Menggunakan tabel `notifications` (custom).
 */
export type AppNotification = {
    id: string;
    user_id: string;
    tipe: string;
    judul: string;
    pesan: string;
    data: Record<string, unknown> | null;
    read_at: string | null;
    created_at: string;
};

/**
 * E-Ticket dari Eloquent mentah (snake_case).
 *
 * Dipakai oleh: endpoint admin yang perlu lihat raw e-ticket.
 *   BUKAN untuk client — client pakai `ETicketInfo`.
 */
export type ETicket = {
    id: number;
    uuid: string;
    trip_id: number;
    booking_id: string;
    qr_code: string;
    pdf_path: string;
    generated_at: string | null;
    created_at: string;

    // Relasi opsional
    trip?: TripEloquent | null;
    booking?: BookingEloquent | null;
};

/**
 * E-Ticket info untuk props client.
 *
 * Backend `Client\BookingController@show` mengirim objek ini
 * (bukan full ETicket model).
 */
export type ETicketInfo = {
    uuid: string;
    download_url: string;
    generated_at: string | null;
};
