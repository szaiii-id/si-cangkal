/**
 * Toast notification system.
 *
 * Simple store + API untuk menampilkan toast global.
 * State disimpan di module-level (singleton), sehingga bisa
 * dipanggil dari mana saja tanpa perlu Context API.
 *
 * Pemakaian:
 *
 *   import { showToast } from '@/utils/toast';
 *
 *   showToast('Berhasil disimpan', 'success');
 *   showToast('Gagal memuat data', 'error', { duration: 8000 });
 *   showToast('Rate limit', 'warning', { duration: 0 }); // tidak auto-dismiss
 *
 * Komponen `<ToastContainer>` yang me-render:
 * - Listener ke `toasts` (Svelte store)
 * - Auto-dismiss setelah `duration` ms
 * - Stack max 3 (sisanya queue)
 */

// ==========================================
// TYPES
// ==========================================

export type ToastVariant = 'success' | 'error' | 'warning' | 'info';

export type ToastItem = {
    id: string;
    message: string;
    variant: ToastVariant;
    duration: number;
    createdAt: number;
};

export type ToastOptions = {
    /**
     * Durasi auto-dismiss dalam ms.
     * - `0` = tidak auto-dismiss (harus manual close)
     * - Default: `5000` (5 detik)
     */
    duration?: number;
};

// ==========================================
// CONSTANTS
// ==========================================

const DEFAULT_DURATION = 5000;
const MAX_VISIBLE = 3;

// ==========================================
// STATE (Svelte 5 Runes — module-level)
// ==========================================

let toastsState = $state<ToastItem[]>([]);
const listeners = new Set<(items: ToastItem[]) => void>();

/**
 * Subscribe ke perubahan toast. Return `unsubscribe` function.
 *
 * Dipakai oleh `<ToastContainer>` untuk reaktif render.
 */
export function subscribeToasts(
    callback: (items: ToastItem[]) => void,
): () => void {
    listeners.add(callback);
    callback(toastsState);

    return () => {
        listeners.delete(callback);
    };
}

/**
 * Notifikasi ke semua listener setelah state berubah.
 */
function notifyListeners(): void {
    for (const listener of listeners) {
        listener(toastsState);
    }
}

// ==========================================
// API
// ==========================================

/**
 * Tampilkan toast baru.
 *
 * @param message Pesan yang ditampilkan
 * @param variant `'success' | 'error' | 'warning' | 'info'`
 * @param options `{ duration?: number }`
 * @returns ID toast (bisa dipakai untuk dismiss manual)
 */
export function showToast(
    message: string,
    variant: ToastVariant = 'info',
    options: ToastOptions = {},
): string {
    const id = generateId();
    const duration = options.duration ?? DEFAULT_DURATION;

    const item: ToastItem = {
        id,
        message,
        variant,
        duration,
        createdAt: Date.now(),
    };

    // Tambah ke list
    toastsState = [...toastsState, item];

    // Kalau lebih dari MAX_VISIBLE, hapus yang paling lama
    if (toastsState.length > MAX_VISIBLE) {
        const excess = toastsState.length - MAX_VISIBLE;
        toastsState = toastsState.slice(excess);
    }

    notifyListeners();

    // Auto-dismiss kalau duration > 0
    if (duration > 0) {
        setTimeout(() => {
            dismissToast(id);
        }, duration);
    }

    return id;
}

/**
 * Dismiss toast berdasarkan ID.
 */
export function dismissToast(id: string): void {
    toastsState = toastsState.filter((t) => t.id !== id);
    notifyListeners();
}

/**
 * Dismiss semua toast.
 */
export function dismissAllToasts(): void {
    toastsState = [];
    notifyListeners();
}

/**
 * Getter reaktif untuk list toast.
 *
 * Dipakai oleh `<ToastContainer>`:
 *   const toasts = $derived(getToasts());
 */
export function getToasts(): ToastItem[] {
    return toastsState;
}

// ==========================================
// INTERNAL
// ==========================================

/**
 * Generate ID unik untuk toast.
 *
 * Pakai `crypto.randomUUID()` kalau tersedia (browser modern),
 * fallback ke `Date.now() + random`.
 */
function generateId(): string {
    if (typeof crypto !== 'undefined' && 'randomUUID' in crypto) {
        return crypto.randomUUID();
    }

    return `${Date.now()}-${Math.random().toString(36).slice(2, 9)}`;
}
