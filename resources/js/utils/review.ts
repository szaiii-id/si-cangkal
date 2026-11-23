/**
 * Logika murni untuk tampilan review.
 *
 * Sengaja dipisah dari komponen Svelte supaya bisa diuji sebagai unit
 * test (tanpa DOM) dan dipakai ulang oleh widget bintang maupun katalog.
 */

const LABEL_NILAI = [
    'Sangat buruk',
    'Buruk',
    'Cukup',
    'Bagus',
    'Sangat bagus',
] as const;

/**
 * Label tekstual untuk rating 1–5.
 *
 * Mengembalikan null untuk nilai di luar rentang supaya pemanggil
 * bisa menyembunyikan label, bukan menampilkan "undefined".
 */
export function ratingLabel(rating: number): string | null {
    if (!Number.isInteger(rating) || rating < 1 || rating > 5) {
        return null;
    }

    return LABEL_NILAI[rating - 1];
}

/**
 * Format angka rata-rata rating untuk ringkasan katalog.
 *
 * 0 (belum ada review) ditampilkan "0.0"; nilai lain dibulatkan
 * ke satu angka desimal.
 */
export function formatRataRata(rataRata: number): string {
    if (!Number.isFinite(rataRata) || rataRata <= 0) {
        return '0.0';
    }

    return rataRata.toFixed(1);
}
