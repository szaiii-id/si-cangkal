import type { BookingStatus } from '@/types';

const DESCRIPTIONS: Record<BookingStatus, string> = {
    pending_payment:
        'Silakan transfer sesuai total tagihan ke rekening Mitra, lalu unggah bukti transfer.',
    payment_verified:
        'Pembayaran telah diverifikasi oleh Mitra. E-Ticket Anda sedang disiapkan.',
    confirmed: 'Booking sudah dikonfirmasi. E-Ticket siap diunduh.',
    in_progress: 'Trip Anda sedang berjalan. Selamat menikmati!',
    completed:
        'Trip sudah selesai. Terima kasih sudah menggunakan SI-CANGKAL.',
    cancelled: 'Booking ini sudah dibatalkan.',
};

export function getBookingStatusDescription(status: BookingStatus): string {
    return DESCRIPTIONS[status];
}