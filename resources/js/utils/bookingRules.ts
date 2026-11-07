/**
 * Aturan bisnis booking yang SAMA PERSIS dengan backend.
 *
 * Single source of truth untuk aturan yang bergantung pada
 * `BookingStatus`. Menghindari hardcoded status list di banyak
 * file FE yang rawan drift saat backend berubah.
 *
 * Backend reference:
 * - `App\Enums\BookingStatus`
 * - `App\Policies\BookingPolicy`
 * - `App\Services\Booking\BookingPaymentService`
 * - `App\Services\Booking\BookingCancellationService`
 *
 * ⚠️ Kalau backend mengubah aturan, WAJIB update konstanta di sini.
 */

import type { BookingStatus } from '@/types/enum';

// ==========================================
// KONSTANTA URUTAN STATUS
// ==========================================

/**
 * Urutan status siklus hidup booking, dari awal sampai akhir.
 *
 * ⚠️ Urutan WAJIB mengikuti alur bisnis:
 * pending_payment → payment_verified → confirmed → in_progress →
 * completed → cancelled.
 */
export const BOOKING_STATUS_ORDER: readonly BookingStatus[] = [
    'pending_payment',
    'payment_verified',
    'confirmed',
    'in_progress',
    'completed',
    'cancelled',
] as const;

// ==========================================
// CANCEL BY CLIENT
// ==========================================

/**
 * Status yang diizinkan untuk cancel oleh client.
 *
 * Mirror: `BookingStatus::canBeCancelledByClient()` di backend.
 */
export const CLIENT_CANCELLABLE_STATUSES: readonly BookingStatus[] = [
    'pending_payment',
    'payment_verified',
] as const;

/**
 * Apakah client boleh membatalkan booking dengan status ini?
 *
 * ⚠️ Ini cek di level UI SAJA. Backend tetap memvalidasi via
 * `BookingPolicy::cancelByClient()`. Kalau FE menampilkan tombol
 * tapi BE menolak, user akan melihat error 403.
 */
export function canClientCancelBooking(status: BookingStatus): boolean {
    return CLIENT_CANCELLABLE_STATUSES.includes(status);
}

// ==========================================
// CANCEL VIA MITRA (refund)
// ==========================================

/**
 * Status yang memerlukan cancel via mitra (bukan cancel langsung).
 *
 * Setelah `confirmed`, client tidak boleh cancel sendiri. Harus
 * hubungi mitra untuk proses refund manual.
 */
export const NEEDS_MITRA_CANCEL_STATUSES: readonly BookingStatus[] = [
    'confirmed',
    'in_progress',
] as const;

/**
 * Apakah booking ini memerlukan pembatalan via mitra?
 */
export function needsMitraCancel(status: BookingStatus): boolean {
    return NEEDS_MITRA_CANCEL_STATUSES.includes(status);
}

// ==========================================
// UPLOAD PAYMENT PROOF
// ==========================================

/**
 * Status yang mengizinkan upload bukti transfer.
 *
 * Mirror: `BookingPolicy::uploadProof()` di backend.
 */
export const PAYMENT_UPLOADABLE_STATUSES: readonly BookingStatus[] = [
    'pending_payment',
] as const;

/**
 * Apakah bukti transfer boleh diunggah pada status ini?
 */
export function canUploadPaymentProof(status: BookingStatus): boolean {
    return PAYMENT_UPLOADABLE_STATUSES.includes(status);
}

// ==========================================
// VERIFY PAYMENT BY MITRA
// ==========================================

/**
 * Status yang mengizinkan mitra memverifikasi pembayaran.
 *
 * Mirror: `BookingPolicy::verifyPayment()` di backend.
 */
export const PAYMENT_VERIFIABLE_STATUSES: readonly BookingStatus[] = [
    'pending_payment',
] as const;

/**
 * Apakah mitra boleh memverifikasi pembayaran pada status ini?
 */
export function canVerifyPayment(status: BookingStatus): boolean {
    return PAYMENT_VERIFIABLE_STATUSES.includes(status);
}

// ==========================================
// E-TICKET
// ==========================================

/**
 * Status yang MENGHARUSKAN E-Ticket ada.
 *
 * Implisit di backend: E-Ticket di-generate setelah `CONFIRMED`.
 */
export const ETICKET_REQUIRED_STATUSES: readonly BookingStatus[] = [
    'confirmed',
    'in_progress',
    'completed',
] as const;

/**
 * Apakah booking ini harusnya punya E-Ticket?
 *
 * Kalau `true` tapi `eticket === null`, tampilkan fallback UI
 * "E-Ticket sedang diproses" (bukan silent).
 */
export function requiresETicket(status: BookingStatus): boolean {
    return ETICKET_REQUIRED_STATUSES.includes(status);
}

// ==========================================
// STATUS GROUP HELPERS
// ==========================================

/**
 * Status final (tidak bisa diubah lagi).
 *
 * Mirror: `BookingStatus::isFinal()` di backend.
 */
export const FINAL_BOOKING_STATUSES: readonly BookingStatus[] = [
    'completed',
    'cancelled',
] as const;

/**
 * Apakah booking sudah final?
 */
export function isFinalBooking(status: BookingStatus): boolean {
    return FINAL_BOOKING_STATUSES.includes(status);
}

/**
 * Apakah booking masih aktif (belum final)?
 */
export function isActiveBooking(status: BookingStatus): boolean {
    return !isFinalBooking(status);
}

// ==========================================
// STATUS INDIVIDUAL CHECKS
// ==========================================

export function isPendingPayment(status: BookingStatus): boolean {
    return status === 'pending_payment';
}

export function isPaymentVerified(status: BookingStatus): boolean {
    return status === 'payment_verified';
}

export function isConfirmed(status: BookingStatus): boolean {
    return status === 'confirmed';
}

export function isInProgress(status: BookingStatus): boolean {
    return status === 'in_progress';
}

export function isCompleted(status: BookingStatus): boolean {
    return status === 'completed';
}

export function isCancelled(status: BookingStatus): boolean {
    return status === 'cancelled';
}

// ==========================================
// STATUS DESCRIPTIONS (untuk UI)
// ==========================================

/**
 * Deskripsi human-readable untuk setiap status booking.
 *
 * Dipakai di `BookingStatusBanner` dan halaman detail booking.
 */
const BOOKING_STATUS_DESCRIPTIONS: Record<BookingStatus, string> = {
    pending_payment:
        'Silakan transfer sesuai total tagihan ke rekening Mitra, lalu unggah bukti transfer.',
    payment_verified:
        'Pembayaran telah diverifikasi oleh Mitra. E-Ticket Anda sedang disiapkan.',
    confirmed: 'Booking sudah dikonfirmasi. E-Ticket siap diunduh.',
    in_progress: 'Trip Anda sedang berjalan. Selamat menikmati!',
    completed:
        'Trip sudah selesai. Terima kasih sudah menggunakan Titiktemu.',
    cancelled: 'Booking ini sudah dibatalkan.',
};

/**
 * Ambil deskripsi human-readable untuk status booking.
 */
export function getBookingStatusDescription(
    status: BookingStatus,
): string {
    return BOOKING_STATUS_DESCRIPTIONS[status];
}