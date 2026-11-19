import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import {
    showToast,
    dismissToast,
    dismissAllToasts,
    subscribeToasts,
    getToasts,
} from '../toast.svelte';

describe('Toast Notification System', () => {
    beforeEach(() => {
        // 1. Gunakan Fake Timers agar bisa mengontrol setTimeout()
        vi.useFakeTimers();
        
        // 2. Bersihkan state sebelum setiap test
        dismissAllToasts();
    });

    afterEach(() => {
        // 3. Kembalikan timer asli setelah test selesai
        vi.useRealTimers();
        vi.restoreAllMocks();
    });

    // ==========================================
    // 1. Basic State & Subscription
    // ==========================================
    describe('State Management', () => {
        it('harus menambahkan toast baru ke state', () => {
            expect(getToasts()).toHaveLength(0);

            const id = showToast('Berhasil disimpan', 'success');
            
            const toasts = getToasts();
            expect(toasts).toHaveLength(1);
            expect(toasts[0].id).toBe(id);
            expect(toasts[0].message).toBe('Berhasil disimpan');
            expect(toasts[0].variant).toBe('success');
        });

        it('harus memberi notifikasi ke subscriber saat ada toast baru', () => {
            const callback = vi.fn();
            const unsubscribe = subscribeToasts(callback);

            // Saat subscribe, callback langsung dipanggil dengan state saat ini (kosong)
            expect(callback).toHaveBeenCalledWith([]);

            // Tambah toast
            showToast('Halo', 'info');
            
            // Callback harus dipanggil dengan 1 toast
            expect(callback).toHaveBeenCalledWith(
                expect.arrayContaining([
                    expect.objectContaining({ message: 'Halo' }),
                ])
            );

            unsubscribe();
        });

        it('harus berhenti menerima notifikasi setelah unsubscribe', () => {
            const callback = vi.fn();
            const unsubscribe = subscribeToasts(callback);

            unsubscribe(); // Berhenti berlangganan
            showToast('Tidak boleh muncul', 'info');

            // Callback hanya dipanggil sekali (saat subscribe)
            expect(callback).toHaveBeenCalledTimes(1);
        });
    });

    // ==========================================
    // 2. Dismiss Logic
    // ==========================================
    describe('Dismiss Toasts', () => {
        it('harus menghapus toast berdasarkan ID', () => {
            const id1 = showToast('Toast 1', 'info');
            showToast('Toast 2', 'info');

            expect(getToasts()).toHaveLength(2);
            
            dismissToast(id1);
            
            expect(getToasts()).toHaveLength(1);
            expect(getToasts()[0].message).toBe('Toast 2');
        });

        it('harus menghapus semua toast', () => {
            showToast('A', 'info');
            showToast('B', 'success');
            expect(getToasts()).toHaveLength(2);

            dismissAllToasts();
            expect(getToasts()).toHaveLength(0);
        });
    });

    // ==========================================
    // 3. Auto-Dismiss (Fake Timers)
    // ==========================================
    describe('Auto-Dismiss', () => {
        it('harus otomatis hilang setelah durasi default (5 detik)', () => {
            showToast('Auto-dismiss', 'info');
            expect(getToasts()).toHaveLength(1);

            // Majukan waktu 4999 ms (belum hilang)
            vi.advanceTimersByTime(4999);
            expect(getToasts()).toHaveLength(1);

            // Majukan waktu 1 ms lagi (genap 5000 ms, harus hilang)
            vi.advanceTimersByTime(1);
            expect(getToasts()).toHaveLength(0);
        });

        it('harus otomatis hilang sesuai custom duration', () => {
            showToast('Custom', 'warning', { duration: 2000 });
            expect(getToasts()).toHaveLength(1);

            vi.advanceTimersByTime(2000);
            expect(getToasts()).toHaveLength(0);
        });

        it('TIDAK boleh auto-dismiss jika duration = 0', () => {
            showToast('Permanent', 'error', { duration: 0 });
            expect(getToasts()).toHaveLength(1);

            // Majukan waktu 1 jam pun tidak boleh hilang
            vi.advanceTimersByTime(3600000);
            expect(getToasts()).toHaveLength(1);
        });
    });

    // ==========================================
    // 4. Limit Max Stack (MAX_VISIBLE = 3)
    // ==========================================
    describe('Limit Max Stack', () => {
        it('hanya boleh menampilkan maksimal 3 toast sekaligus', () => {
            showToast('Toast 1', 'info');
            showToast('Toast 2', 'info');
            showToast('Toast 3', 'info');
            
            expect(getToasts()).toHaveLength(3);

            // Tambah toast ke-4
            showToast('Toast 4', 'info');
            
            const toasts = getToasts();
            expect(toasts).toHaveLength(3); 
            
            // Toast terlama (Toast 1) harus terhapus, digantikan Toast 4
            expect(toasts[0].message).toBe('Toast 2');
            expect(toasts[2].message).toBe('Toast 4');
        });
    });
});