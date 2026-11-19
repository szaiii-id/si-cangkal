import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';

// 1. MOCK INERTIA ROUTER SEBELUM IMPORT
vi.mock('@inertiajs/svelte', () => ({
    router: {
        get: vi.fn(),
    },
}));

import { router } from '@inertiajs/svelte';
import { createFilter } from '../filters.svelte';

describe('Filter State Manager (filters.svelte.ts)', () => {
    beforeEach(() => {
        vi.useFakeTimers(); // Wajib untuk menguji debounce
        vi.clearAllMocks();
    });

    afterEach(() => {
        vi.useRealTimers();
        vi.restoreAllMocks();
    });

    // Helper pembuat filter yang aman
    const createTestFilter = (debounceMs = 300) => {
        // Variabel penampung untuk mengakses controller di dalam closure
        let controller: any; 

        controller = createFilter({
            url: '/admin/users',
            initial: {
                search: '',
                status: '',
                page: 1,
            },
            // PERBAIKAN: Gunakan closure 'controller', bukan 'this'
            buildQuery: () => {
                return {
                    search: controller.values.search,
                    status: controller.values.status,
                    page: controller.values.page,
                };
            },
            debounceMs,
        });

        return controller;
    };

    // ==========================================
    // 1. STATE MANAGEMENT (values getter)
    // ==========================================
    describe('State Management', () => {
        it('harus menginisialisasi state dengan nilai awal', () => {
            const filter = createTestFilter();
            expect(filter.values.search).toBe('');
            expect(filter.values.status).toBe('');
            expect(filter.values.page).toBe(1);
        });

        it('harus mengubah nilai state secara reaktif saat update() dipanggil', () => {
            const filter = createTestFilter();
            filter.update('status', 'active');
            expect(filter.values.status).toBe('active');
        });

        it('harus mendeteksi filter yang aktif dengan hasActive()', () => {
            const filter = createTestFilter();
            expect(filter.hasActive()).toBe(false);

            filter.update('status', 'active');
            expect(filter.hasActive()).toBe(true);

            filter.update('status', '');
            expect(filter.hasActive()).toBe(false);
        });

        it('hasActive() harus mengabaikan perbedaan huruf besar/kecil dan spasi', () => {
            const filter = createFilter({
                url: '/test',
                initial: { search: 'Budi' },
                buildQuery: () => ({}),
            });

            filter.update('search', ' budi ');
            expect(filter.hasActive()).toBe(false); 
        });
    });

    // ==========================================
    // 2. DEBOUNCE & INERTIA ROUTER
    // ==========================================
    describe('Debounce & Inertia Router', () => {
        it('harus MENUNDA pemanggilan router.get saat update() dipanggil (debounce)', () => {
            const filter = createTestFilter(300);
            filter.update('search', 'budi');
            
            expect(router.get).not.toHaveBeenCalled();

            vi.advanceTimersByTime(299);
            expect(router.get).not.toHaveBeenCalled();

            vi.advanceTimersByTime(1);
            expect(router.get).toHaveBeenCalledTimes(1);
        });

        it('harus langsung memanggil router.get jika update(immediate=true)', () => {
            const filter = createTestFilter(300);
            filter.update('status', 'active', true);

            expect(router.get).toHaveBeenCalledTimes(1);
            expect(router.get).toHaveBeenCalledWith(
                '/admin/users',
                expect.objectContaining({ status: 'active' }),
                expect.any(Object)
            );
        });

        it('harus mereset timer debounce jika update() dipanggil berulang kali (Anti-Spam)', () => {
            const filter = createTestFilter(300);

            filter.update('search', 'b');
            vi.advanceTimersByTime(100);

            filter.update('search', 'bu');
            vi.advanceTimersByTime(100);

            filter.update('search', 'bud');
            vi.advanceTimersByTime(100);

            filter.update('search', 'budi');
            
            expect(router.get).not.toHaveBeenCalled();

            vi.advanceTimersByTime(300);
            expect(router.get).toHaveBeenCalledTimes(1);
        });

        it('harus memanggil router.get dengan query yang benar dari buildQuery()', () => {
            const filter = createTestFilter(300);
            filter.update('search', 'budi', true);

            expect(router.get).toHaveBeenCalledWith(
                '/admin/users',
                {
                    search: 'budi',
                    status: '',
                    page: 1,
                },
                {
                    preserveState: true,
                    preserveScroll: true,
                    replace: true,
                }
            );
        });
    });

    // ==========================================
    // 3. CLEAR FUNCTION
    // ==========================================
    describe('clear() Function', () => {
        it('harus mereset semua state ke string kosong dan memanggil router', () => {
            const filter = createTestFilter();
            
            filter.update('search', 'budi');
            filter.update('status', 'active');
            filter.update('page', 3);

            filter.clear();

            expect(filter.values.search).toBe('');
            expect(filter.values.status).toBe('');
            expect(filter.values.page).toBe(''); 

            expect(router.get).toHaveBeenCalledWith(
                '/admin/users',
                {},
                expect.any(Object)
            );
        });
    });

    // ==========================================
    // 4. UPDATE SEARCH
    // ==========================================
    describe('updateSearch() Function', () => {
        it('harus memicu debounce saat mengetik di search bar', () => {
            const filter = createTestFilter(300);
            filter.updateSearch('budi');

            expect(filter.values.search).toBe('budi');
            expect(router.get).not.toHaveBeenCalled();

            vi.advanceTimersByTime(300);
            expect(router.get).toHaveBeenCalledTimes(1);
        });
    });
});