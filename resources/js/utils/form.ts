/**
 * Helper form — parse Rupiah, deteksi perubahan, dan util umum.
 */

// Import dari formatCurrency agar tidak duplikasi logika
import { parseCurrency } from './formatCurrency';

export { parseCurrency as parseRupiah };

// ==========================================
// PERFORMANCE: CACHE INTL FORMATTER
// ==========================================
// Hindari membuat instance baru saat dipanggil berulang kali di dalam loop.
const rupiahInputFormatter = new Intl.NumberFormat('id-ID', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
});

// ==========================================
// FORMAT RUPIAH INPUT (UI)
// ==========================================

/**
 * Format angka jadi string input Rupiah (tanpa "Rp", dengan titik ribuan).
 *
 * @example
 *   formatRupiahInput(850000)  // "850.000"
 */
export function formatRupiahInput(value: number | null | undefined): string {
    if (value === null || value === undefined || Number.isNaN(value)) return '';

    return rupiahInputFormatter.format(value);
}

// ==========================================
// PARSE INTEGER (untuk jumlah peserta, hari, dll)
// ==========================================

/**
 * Parse input user ke integer (buang non-digit).
 *
 * @example
 *   parseIntInput('12')       // 12
 *   parseIntInput('1.500')    // 1500
 *   parseIntInput('abc')      // 0
 */
export function parseIntInput(
    value: string | number | null | undefined,
): number {
    if (value === null || value === undefined || value === '') return 0;

    if (typeof value === 'number') {
        return Number.isFinite(value) ? Math.trunc(value) : 0;
    }

    const digitsOnly = value.replace(/\D/g, '');
    return digitsOnly === '' ? 0 : parseInt(digitsOnly, 10);
}

// ==========================================
// DETEKSI PERUBAHAN FORM
// ==========================================

/**
 * Cek apakah dua object punya nilai berbeda (shallow compare).
 *
 * Berguna untuk tombol "Simpan" disabled saat tidak ada perubahan.
 *
 * @example
 *   hasFormChanges({ name: 'A' }, { name: 'B' })  // true
 *   hasFormChanges({ name: 'A' }, { name: 'A' })  // false
 */
export function hasFormChanges<T extends Record<string, unknown>>(
    current: T,
    initial: T,
): boolean {
    const keys = new Set([...Object.keys(current), ...Object.keys(initial)]);

    for (const key of keys) {
        const a = normalizeForCompare(current[key]);
        const b = normalizeForCompare(initial[key]);

        if (a !== b) return true;
    }

    return false;
}

// ==========================================
// TRIM OBJECT
// ==========================================

/**
 * Trim semua string value pada object (shallow).
 *
 * @example
 *   trimObject({ nama: '  Budi  ', email: ' a@b.c ' })
 *   // { nama: 'Budi', email: 'a@b.c' }
 */
export function trimObject<T extends Record<string, unknown>>(obj: T): T {
    const result: Record<string, unknown> = {};

    for (const [key, value] of Object.entries(obj)) {
        result[key] = typeof value === 'string' ? value.trim() : value;
    }

    return result as T;
}

// ==========================================
// EMPTY CHECK
// ==========================================

/**
 * Cek apakah value kosong (null, undefined, '', []).
 */
export function isEmpty(value: unknown): boolean {
    if (value === null || value === undefined) return true;
    if (typeof value === 'string') return value.trim() === '';
    if (Array.isArray(value)) return value.length === 0;
    return false;
}

// ==========================================
// INTERNAL
// ==========================================

function normalizeForCompare(value: unknown): unknown {
    // Number string "5" vs number 5 dianggap sama
    if (typeof value === 'string' && value.trim() !== '') {
        const asNumber = Number(value);
        if (!Number.isNaN(asNumber)) return asNumber;
        return value.trim();
    }

    if (typeof value === 'number') return value;
    if (value === null || value === undefined) return '';
    if (typeof value === 'string') return value.trim();

    return JSON.stringify(value);
}
