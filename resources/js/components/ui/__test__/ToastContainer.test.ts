import { render, screen, waitFor } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import ToastContainer from '../ToastContainer.svelte';
import type { ToastItem } from '@/utils/toast.svelte';

// ==========================================
// 1. MOCK FISIK UNTUK TOAST.SVELTE
// ==========================================
vi.mock('../Toast.svelte', () => import('../__mocks__/Toast.svelte'));

// ==========================================
// 2. MOCK TOAST STORE
// ==========================================
let subscriberCallback: ((items: ToastItem[]) => void) | null = null;

vi.mock('@/utils/toast.svelte', () => ({
    subscribeToasts: vi.fn((cb) => {
        subscriberCallback = cb;
        cb([]); // Panggil awal dengan array kosong
        return () => { subscriberCallback = null; };
    }),
    dismissToast: vi.fn(),
}));

import { dismissToast } from '@/utils/toast.svelte';

describe('ToastContainer Component', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    // ==========================================
    // 1. RENDERING DASAR
    // ==========================================
    describe('Rendering', () => {
        it('TIDAK boleh merender apa pun jika toasts kosong', () => {
            const { container } = render(ToastContainer);
            
            const region = container.querySelector('[role="region"]');
            expect(region).not.toBeInTheDocument();
        });
    });

    // ==========================================
    // 2. MENAMPILKAN TOAST
    // ==========================================
    describe('Showing Toasts', () => {
        it('harus merender toast saat subscriber menerima data baru', async () => {
            render(ToastContainer);

            const mockToasts: ToastItem[] = [
                { id: '1', message: 'Toast 1', variant: 'success', duration: 5000, createdAt: Date.now() }
            ];
            
            subscriberCallback?.(mockToasts);

            await waitFor(() => {
                expect(screen.getByTestId('toast-item-1')).toBeInTheDocument();
                expect(screen.getByText('Toast 1')).toBeInTheDocument();
            });
        });

        it('harus merender multiple toast secara bersamaan', async () => {
            render(ToastContainer);

            const mockToasts: ToastItem[] = [
                { id: '1', message: 'Toast 1', variant: 'success', duration: 5000, createdAt: Date.now() },
                { id: '2', message: 'Toast 2', variant: 'error', duration: 5000, createdAt: Date.now() },
            ];
            
            subscriberCallback?.(mockToasts);

            await waitFor(() => {
                expect(screen.getByTestId('toast-item-1')).toBeInTheDocument();
                expect(screen.getByTestId('toast-item-2')).toBeInTheDocument();
            });
        });

        it('harus memanggil dismissToast saat tombol X diklik (dari Toast)', async () => {
            render(ToastContainer);

            const mockToasts: ToastItem[] = [
                { id: '1', message: 'Toast 1', variant: 'success', duration: 5000, createdAt: Date.now() }
            ];
            
            subscriberCallback?.(mockToasts);

            await waitFor(() => {
                const dismissBtn = screen.getByTestId('toast-dismiss-1');
                dismissBtn.click();
            });

            expect(dismissToast).toHaveBeenCalledWith('1');
        });
    });

    // ==========================================
    // 3. AKSESIBILITAS
    // ==========================================
    describe('Accessibility', () => {
        it('harus memiliki role="region" dan aria-live="polite"', async () => {
            render(ToastContainer);

            subscriberCallback?.([
                { id: '1', message: 'Toast 1', variant: 'info', duration: 5000, createdAt: Date.now() }
            ]);

            await waitFor(() => {
                const region = screen.getByRole('region', { name: /Notifikasi/i });
                expect(region).toBeInTheDocument();
                expect(region).toHaveAttribute('aria-live', 'polite');
            });
        });
    });
});