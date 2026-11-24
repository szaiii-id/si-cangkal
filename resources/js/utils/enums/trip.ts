import type { TripStatus } from '@/types';

const LABEL: Record<TripStatus, string> = {
    scheduled: 'Terjadwal',
    active: 'Sedang Berjalan',
    completed: 'Selesai',
    cancelled: 'Dibatalkan',
};

const BADGE: Record<TripStatus, string> = {
    scheduled: 'bg-blue/10 text-blue-text border border-blue/20',
    active: 'bg-purple/10 text-purple-text border border-purple/20',
    completed: 'bg-green/10 text-green-text border border-green/20',
    cancelled: 'bg-red-50 text-red-700 border border-red-200',
};

const SHORT: Record<TripStatus, string> = {
    scheduled: 'Terjadwal',
    active: 'Berjalan',
    completed: 'Selesai',
    cancelled: 'Batal',
};

export function tripStatusLabel(status: string): string {
    return LABEL[status as TripStatus] ?? status;
}

export function tripStatusBadge(status: string): string {
    return (
        BADGE[status as TripStatus] ??
        'bg-gray-100 text-gray-700 border border-gray-200'
    );
}

export function tripStatusShort(status: string): string {
    return SHORT[status as TripStatus] ?? status;
}
