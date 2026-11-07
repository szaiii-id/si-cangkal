/**
 * Helper countdown untuk booking `expired_at`.
 *
 * DRY: logika countdown yang sama dipakai di
 * `Client/Bookings/Index.svelte` dan `Client/Bookings/Show.svelte`.
 *
 * Catatan timezone:
 * - Input `expiredAt` adalah ISO 8601 timestamp (UTC).
 * - Perbandingan pakai `Date.now()` (UTC) — aman lintas timezone.
 */

// ==========================================
// TYPES
// ==========================================

export type CountdownResult = {
    /**
     * Label siap tampil untuk UI.
     *
     * Contoh: "2 jam 30 menit", "45 menit 12 detik", "30 detik", "Expired".
     */
    label: string;

    /**
     * Apakah waktu sudah lewat (diff <= 0)?
     */
    isExpired: boolean;

    /**
     * Apakah sisa waktu < 6 jam?
     */
    isExpiringSoon: boolean;

    /**
     * Sisa waktu dalam milidetik (negatif jika sudah lewat).
     */
    remainingMs: number;
};

// ==========================================
// CONSTANTS
// ==========================================

const EXPIRING_SOON_THRESHOLD_MS = 6 * 60 * 60 * 1000;
const MS_PER_SECOND = 1_000;
const MS_PER_MINUTE = 60 * MS_PER_SECOND;
const MS_PER_HOUR = 60 * MS_PER_MINUTE;

// ==========================================
// MAIN FUNCTION
// ==========================================

/**
 * Format countdown dari `expired_at` ISO timestamp.
 *
 * @param expiredAt  ISO 8601 timestamp (UTC), atau null.
 * @param nowMs      Override untuk `Date.now()` (testing). Default: sekarang.
 * @returns          `CountdownResult` atau `null` jika `expiredAt` null/invalid.
 */
export function formatBookingCountdown(
    expiredAt: string | null | undefined,
    nowMs: number = Date.now(),
): CountdownResult | null {
    if (!expiredAt) {
        return null;
    }

    const expiredMs = new Date(expiredAt).getTime();

    if (Number.isNaN(expiredMs)) {
        return null;
    }

    const remainingMs = expiredMs - nowMs;

    if (remainingMs <= 0) {
        return {
            label: 'Expired',
            isExpired: true,
            isExpiringSoon: false,
            remainingMs,
        };
    }

    const isExpiringSoon = remainingMs < EXPIRING_SOON_THRESHOLD_MS;
    const label = formatRemainingTime(remainingMs);

    return {
        label,
        isExpired: false,
        isExpiringSoon,
        remainingMs,
    };
}

// ==========================================
// FORMAT HELPER
// ==========================================

/**
 * Format sisa waktu milidetik menjadi label ringkas.
 *
 * - >= 1 jam     : "X jam Y menit"
 * - >= 1 menit   : "Y menit Z detik"
 * - < 1 menit    : "Z detik"
 */
function formatRemainingTime(remainingMs: number): string {
    const hours = Math.floor(remainingMs / MS_PER_HOUR);
    const minutes = Math.floor(
        (remainingMs % MS_PER_HOUR) / MS_PER_MINUTE,
    );
    const seconds = Math.floor(
        (remainingMs % MS_PER_MINUTE) / MS_PER_SECOND,
    );

    if (hours > 0) {
        return `${hours} jam ${minutes} menit`;
    }

    if (minutes > 0) {
        return `${minutes} menit ${seconds} detik`;
    }

    return `${seconds} detik`;
}

// ==========================================
// UTILITY HELPERS
// ==========================================

/**
 * Apakah booking sudah expired (waktu lewat)?
 */
export function isBookingExpired(
    expiredAt: string | null | undefined,
    nowMs: number = Date.now(),
): boolean {
    if (!expiredAt) {
        return false;
    }

    const expiredMs = new Date(expiredAt).getTime();

    if (Number.isNaN(expiredMs)) {
        return false;
    }

    return expiredMs <= nowMs;
}

/**
 * Sisa waktu dalam menit (dibulatkan ke bawah).
 * Return null jika `expiredAt` null/invalid.
 */
export function getRemainingMinutes(
    expiredAt: string | null | undefined,
    nowMs: number = Date.now(),
): number | null {
    if (!expiredAt) {
        return null;
    }

    const expiredMs = new Date(expiredAt).getTime();

    if (Number.isNaN(expiredMs)) {
        return null;
    }

    return Math.floor((expiredMs - nowMs) / MS_PER_MINUTE);
}