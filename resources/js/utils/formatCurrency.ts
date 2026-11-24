/**
 * Utility untuk format angka menjadi tampilan Rupiah Indonesia.
 */

// ==========================================
// PERFORMANCE: CACHE INTL FORMATTER
// ==========================================
// Membuat instance Intl berulang kali sangat lambat.
// Kita buat sekali dan simpan di memori (Singleton).
const currencyFormatter = new Intl.NumberFormat('id-ID', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
});

// ==========================================
// FORMATTERS
// ==========================================

/**
 * Format angka menjadi string Rupiah.
 */
export function formatCurrency(
    value: number | string | null | undefined,
): string {
    const num = parseToNumber(value);

    if (num === null) {
        return '—';
    }

    return `Rp ${currencyFormatter.format(num)}`;
}

/**
 * Format angka tanpa prefix "Rp".
 */
export function formatNumber(
    value: number | string | null | undefined,
): string {
    const num = parseToNumber(value);

    if (num === null) {
        return '—';
    }

    return currencyFormatter.format(num);
}

/**
 * Parse string input user menjadi number.
 */
/**
 * Parse string input user menjadi number.
 */
export function parseCurrency(
    value: string | number | null | undefined,
): number {
    if (value === null || value === undefined || value === '') {
        return 0;
    }

    if (typeof value === 'number') {
        return Number.isNaN(value) ? 0 : value;
    }

    // 1. Bersihkan prefix Rp dan spasi
    let cleaned = value
        .toString()
        .trim()
        .replace(/^Rp\s*/i, '');

    const hasDot = cleaned.includes('.');
    const hasComma = cleaned.includes(',');

    if (hasDot && hasComma) {
        const lastDot = cleaned.lastIndexOf('.');
        const lastComma = cleaned.lastIndexOf(',');

        if (lastComma > lastDot) {
            cleaned = cleaned.replace(/\./g, '').replace(',', '.');
        } else {
            cleaned = cleaned.replace(/,/g, '');
        }
    } else if (hasDot) {
        // Hanya ada titik
        const parts = cleaned.split('.');
        if (parts.length > 2 || (parts[1] && parts[1].length === 3)) {
            cleaned = cleaned.replace(/\./g, '');
        }
    } else if (hasComma) {
        const parts = cleaned.split(',');
        if (parts.length > 2 || (parts[1] && parts[1].length === 3)) {
            cleaned = cleaned.replace(/,/g, '');
        } else {
            cleaned = cleaned.replace(',', '.');
        }
    }

    const num = parseFloat(cleaned);
    return Number.isNaN(num) ? 0 : num;
}

// ==========================================
// REKENING BANK & CLIPBOARD
// ==========================================

/**
 * Sensor nomor rekening untuk tampilan visual.
 */
export function maskAccountNumber(
    accountNumber: string | null | undefined,
): string {
    if (!accountNumber) return '-';
    const cleanNumber = accountNumber.replace(/\s+/g, '');
    if (cleanNumber.length <= 4) return '****';
    return `•••• •••• ${cleanNumber.slice(-4)}`;
}

/**
 * Salin teks ke clipboard dengan proteksi SSR.
 * (Bisa dipanggil dari event handler tombol di Svelte).
 */
export function copyToClipboard(text: string): Promise<void> {
    if (typeof window === 'undefined' || !navigator.clipboard) {
        return Promise.reject(new Error('Clipboard API tidak tersedia'));
    }
    return navigator.clipboard.writeText(text);
}

// ==========================================
// INTERNAL HELPER
// ==========================================

/**
 * Parse value apapun jadi number, atau null kalau invalid/kosong.
 */
function parseToNumber(
    value: number | string | null | undefined,
): number | null {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    const num = typeof value === 'string' ? parseFloat(value) : value;

    return Number.isNaN(num) ? null : num;
}
