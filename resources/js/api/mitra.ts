import { router } from '@inertiajs/svelte';
import type { RequestPayload } from '@inertiajs/core';

// Best practice: Ambil tipe parameter ke-3 secara otomatis dari method router.
// Menghindari error 'optimistic' dan otomatis sinkron dengan versi Inertia Anda.
type MitraOptions = Parameters<typeof router.post>[2];

// ==========================================
// PACKAGES
// ==========================================
export const getPackages = (
    params: RequestPayload = {},
    options?: MitraOptions,
) => router.get('/mitra/packages', params, options);

export const getPackage = (id: number, options?: MitraOptions) =>
    router.get(`/mitra/packages/${id}`, {}, options);

export const createPackage = (data: RequestPayload, options?: MitraOptions) =>
    router.post('/mitra/packages', data, options);

// post + _method=PUT: PHP tidak parse PUT multipart
export const updatePackage = (
    id: number,
    data: RequestPayload,
    options?: MitraOptions,
) => router.post(`/mitra/packages/${id}`, data, options);

export const deletePackage = (id: number, options?: MitraOptions) =>
    router.delete(`/mitra/packages/${id}`, options);

export const publishPackage = (id: number, options?: MitraOptions) =>
    router.post(`/mitra/packages/${id}/publish`, {}, options);

export const archivePackage = (id: number, options?: MitraOptions) =>
    router.post(`/mitra/packages/${id}/archive`, {}, options);

export const restorePackage = (id: number, options?: MitraOptions) =>
    router.post(`/mitra/packages/${id}/restore`, {}, options);

// Fungsi baru untuk melengkapi Fase 2
export const getCompletePhase2 = (id: number, options?: MitraOptions) =>
    router.get(`/mitra/packages/${id}/complete-phase2`, {}, options);

// ==========================================
// TRIPS
// ==========================================
export const getTrips = (params: RequestPayload = {}, options?: MitraOptions) =>
    router.get('/mitra/trips', params, options);

/**
 * URL untuk cetak manifest (PDF).
 * Gunakan dengan window.open() atau <a href target="_blank">.
 */
export const getTripManifestUrl = (id: number): string =>
    `/mitra/trips/${id}/manifest`;

/**
 * Tandai trip selesai (active -> completed).
 * Backend juga menyinkronkan status booking + TripDay.
 */
export const completeTrip = (id: number, options?: MitraOptions) =>
    router.post(`/mitra/trips/${id}/complete`, {}, options);

// ==========================================
// BOOKINGS
// ==========================================
export const getBookings = (
    params: RequestPayload = {},
    options?: MitraOptions,
) => router.get('/mitra/bookings', params, options);

export const verifyPayment = (bookingId: string, options?: MitraOptions) =>
    router.post(`/mitra/bookings/${bookingId}/verify`, {}, options);

export const rejectPayment = (
    bookingId: string,
    data: RequestPayload,
    options?: MitraOptions,
) => router.post(`/mitra/bookings/${bookingId}/reject`, data, options);

export const cancelBooking = (
    bookingId: string,
    data: RequestPayload,
    options?: MitraOptions,
) => router.post(`/mitra/bookings/${bookingId}/cancel`, data, options);

/**
 * Request data (bukan navigasi halaman) untuk membuat Payment Session Xendit.
 * CSRF dibaca dari meta tag Blade; redirect ke Xendit dilakukan oleh client
 * setelah mitra menyalin link ini.
 */
function csrfToken(): string {
    return (
        document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
            ?.content ?? ''
    );
}

export async function createPaymentLink(
    bookingId: string,
): Promise<{ url: string; expires_at: string | null; checkout_url: string }> {
    const response = await fetch(`/mitra/bookings/${bookingId}/payment-link`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
    });

    if (!response.ok) {
        throw new Error('Gagal membuat link pembayaran.');
    }

    return (await response.json()) as {
        url: string;
        expires_at: string | null;
        checkout_url: string;
    };
}

export const uploadPaymentProof = (
    bookingId: string,
    data: RequestPayload,
    options?: MitraOptions,
) => router.post(`/mitra/bookings/${bookingId}/upload-proof`, data, options);

export const createManualBooking = (
    packageId: number,
    data: RequestPayload,
    options?: MitraOptions,
) => router.post(`/mitra/packages/${packageId}/manual-booking`, data, options);

// ==========================================
// REFUNDS
// ==========================================
export const getRefunds = (
    params: RequestPayload = {},
    options?: MitraOptions,
) => router.get('/mitra/refunds', params, options);

export const approveRefund = (
    refundId: number,
    data: RequestPayload,
    options?: MitraOptions,
) => router.post(`/mitra/refunds/${refundId}/approve`, data, options);

export const rejectRefund = (
    refundId: number,
    data: RequestPayload,
    options?: MitraOptions,
) => router.post(`/mitra/refunds/${refundId}/reject`, data, options);

export const uploadRefundProof = (
    refundId: number,
    data: RequestPayload,
    options?: MitraOptions,
) => router.post(`/mitra/refunds/${refundId}/proof`, data, options);

// ==========================================
// PROFILE & ONBOARDING
// ==========================================
export const submitOnboarding = (
    data: RequestPayload,
    options?: MitraOptions,
) => router.post('/mitra/onboarding', data, options);

export const updateProfile = (data: RequestPayload, options?: MitraOptions) =>
    router.post('/mitra/profile', data, options);

export const updateBankAccounts = (
    data: RequestPayload,
    options?: MitraOptions,
) => router.put('/mitra/profile/bank-accounts', data, options);

// ==========================================
// PASSWORD
// ==========================================
export const updatePassword = (data: RequestPayload, options?: MitraOptions) =>
    router.put('/mitra/password', data, options);
