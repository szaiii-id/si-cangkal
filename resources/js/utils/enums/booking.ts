import type { BookingStatus } from '@/types';

const LABEL: Record<BookingStatus, string> = {
    pending_payment: 'Menunggu Pembayaran',
    payment_verified: 'Pembayaran Diverifikasi',
    confirmed: 'Terkonfirmasi',
    in_progress: 'Sedang Berjalan',
    completed: 'Selesai',
    cancelled: 'Dibatalkan',
};

const BADGE: Record<BookingStatus, string> = {
    pending_payment: 'bg-sun/10 text-sun-text border border-sun/20',
    payment_verified: 'bg-blue/10 text-blue-text border border-blue/20',
    confirmed: 'bg-green/10 text-green-text border border-green/20',
    in_progress: 'bg-purple/10 text-purple-text border border-purple/20',
    completed: 'bg-gray-100 text-gray-700 border border-gray-200',
    cancelled: 'bg-red-50 text-red-700 border border-red-200',
};

const SHORT: Record<BookingStatus, string> = {
    pending_payment: 'Pending',
    payment_verified: 'Verifikasi',
    confirmed: 'Konfirmasi',
    in_progress: 'Berjalan',
    completed: 'Selesai',
    cancelled: 'Batal',
};

export function bookingStatusLabel(status: string): string {
    return LABEL[status as BookingStatus] ?? status;
}

export function bookingStatusBadge(status: string): string {
    return (
        BADGE[status as BookingStatus] ??
        'bg-gray-100 text-gray-700 border border-gray-200'
    );
}

export function bookingStatusShort(status: string): string {
    return SHORT[status as BookingStatus] ?? status;
}
