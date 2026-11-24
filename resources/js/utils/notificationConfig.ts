/**
 * Konfigurasi icon + warna per tipe notifikasi.
 *
 * Dipisah dari `Notifications/Index.svelte` agar halaman tetap
 * < 250 baris dan config mudah di-reuse di tempat lain
 * (misal: preview di dashboard, toast).
 *
 *   File ini HANYA berisi pemetaan tipe → icon & warna.
 * Logika redirect (klik notifikasi → halaman tujuan) ada di
 * `pages/Notifications/Index.svelte` karena butuh akses ke
 * `router` dan `data.url` dari item notifikasi.
 */

export type TipeConfig = {
    icon: string;
    bg: string;
    text: string;
};

const CONFIG: Record<string, TipeConfig> = {
    // Booking
    booking_confirmed: {
        icon: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
        bg: 'bg-green/10',
        text: 'text-green-text',
    },
    booking_cancelled: {
        icon: 'M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z',
        bg: 'bg-red-50',
        text: 'text-red-700',
    },

    // Payment
    payment_proof_uploaded: {
        icon: 'M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12',
        bg: 'bg-blue/10',
        text: 'text-blue-text',
    },
    payment_verified: {
        icon: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
        bg: 'bg-green/10',
        text: 'text-green-text',
    },
    payment_rejected: {
        icon: 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z',
        bg: 'bg-red-50',
        text: 'text-red-700',
    },

    // Refund
    refund_succeeded: {
        icon: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
        bg: 'bg-green/10',
        text: 'text-green-text',
    },
    refund_rejected: {
        icon: 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z',
        bg: 'bg-red-50',
        text: 'text-red-700',
    },
    refund_requested: {
        icon: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
        bg: 'bg-sun/10',
        text: 'text-sun-text',
    },

    // Trip
    trip_started: {
        icon: 'M13 10V3L4 14h7v7l9-11h-7z',
        bg: 'bg-purple/10',
        text: 'text-purple-text',
    },
    trip_completed: {
        icon: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
        bg: 'bg-green/10',
        text: 'text-green-text',
    },

    // Review
    review_baru: {
        icon: 'M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.539 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.196-1.539-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.783-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z',
        bg: 'bg-sun/10',
        text: 'text-sun-text',
    },

    // Admin
    mitra_registered: {
        icon: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z',
        bg: 'bg-blue/10',
        text: 'text-blue-text',
    },

    // Fallback
    default: {
        icon: 'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9',
        bg: 'bg-gray-100',
        text: 'text-gray-500',
    },
};

/**
 * Ambil config icon + warna untuk tipe notifikasi.
 *
 * Return `default` kalau tipe tidak dikenal.
 */
export function getTipeConfig(tipe: string): TipeConfig {
    return CONFIG[tipe] ?? CONFIG.default;
}
