/**
 * Logika murni untuk halaman verifikasi email (PRD-AUTH-002).
 *
 * Dipisahkan dari komponen Svelte agar bisa diuji di level Unit
 * tanpa DOM / Inertia (sesuai piramida pengujian).
 */

/** Fallback untuk cooldown kirim ulang (detik). */
export const RESEND_COOLDOWN_SECONDS = 60;

/**
 * Normalisasi header `Retry-After` menjadi jumlah detik.
 *
 * BVA:
 * - null / undefined / string kosong → fallback
 * - angka negatif, 0, NaN, Infinity → fallback
 * - string numerik valid ("42") → 42
 * - angka desimal → dibulatkan ke bawah
 */
export function parseRetryAfter(
    value: string | number | null | undefined,
    fallback: number = RESEND_COOLDOWN_SECONDS,
): number {
    if (value === null || value === undefined || value === '') {
        return fallback;
    }

    const seconds =
        typeof value === 'number' ? value : Number.parseInt(value, 10);

    if (!Number.isFinite(seconds) || seconds <= 0) {
        return fallback;
    }

    return Math.floor(seconds);
}

/** Label tombol saat cooldown masih aktif. */
export function formatCooldownLabel(seconds: number): string {
    return `Tunggu ${Math.max(0, Math.floor(seconds))} detik…`;
}
