import { router } from '@inertiajs/svelte';
import type { RequestPayload } from '@inertiajs/core';

// Best practice: Ambil tipe parameter ke-3 secara otomatis dari method router.
// Ini otomatis sinkron dengan tipe internal Inertia (VisitHelperOptions)
// dan menghindari error kontravarians pada properti 'optimistic'.
type ClientOptions = Parameters<typeof router.post>[2];

// ==========================================
// PROFILE
// ==========================================
export const updateProfile = (data: RequestPayload, options?: ClientOptions) =>
    router.put('/client/profile', data, options);

// ==========================================
// CATALOG
// ==========================================
export const getCatalog = (
    params: RequestPayload = {},
    options?: ClientOptions,
) => router.get('/client/catalog', params, options);

// ==========================================
// BOOKINGS
// ==========================================
export const getBookings = (
    params: RequestPayload = {},
    options?: ClientOptions,
) => router.get('/client/bookings', params, options);

export const createBooking = (
    packageId: number,
    data: RequestPayload,
    options?: ClientOptions,
) => router.post(`/client/bookings/${packageId}`, data, options);

export const cancelBooking = (
    bookingId: string,
    data: RequestPayload = {},
    options?: ClientOptions,
) => router.post(`/client/bookings/${bookingId}/cancel`, data, options);

export const uploadProof = (
    bookingId: string,
    data: RequestPayload,
    options?: ClientOptions,
) => router.post(`/client/bookings/${bookingId}/upload-proof`, data, options);

/**
 * Buat Payment Session Xendit untuk booking pending.
 * Backend memakai Inertia::location sehingga router akan membuka halaman
 * checkout Xendit secara otomatis sebagai hard redirect.
 */
export const createXenditCharge = (
    bookingId: string,
    options?: ClientOptions,
) => router.post(`/client/bookings/${bookingId}/pay`, {}, options);

// ==========================================
// REVIEW
// ==========================================
/**
 * Kirim review untuk booking yang trip-nya sudah selesai.
 * Backend: `Client\ReviewController@store`.
 */
export const createReview = (
    bookingId: string,
    data: RequestPayload,
    options?: ClientOptions,
) => router.post(`/client/bookings/${bookingId}/review`, data, options);

/**
 * URL untuk mengunduh bukti transfer (digunakan di PaymentProofPreview).
 * Backend: `Client\BookingController@downloadProof`.
 */
export const getPaymentProofUrl = (bookingId: string): string =>
    `/client/bookings/${bookingId}/payment-proof`;

/**
 * URL untuk mengunduh E-Ticket.
 * Backend: `Client\ETicketController@download`.
 */
export const getETicketDownloadUrl = (uuid: string): string =>
    `/client/e-tickets/${uuid}/download`;
