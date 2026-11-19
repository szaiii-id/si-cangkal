import { render, screen, fireEvent } from '@testing-library/svelte';
import { describe, it, expect, vi } from 'vitest';
import Toast from '../Toast.svelte';
import type { ToastItem } from '@/utils/toast.svelte';

// ==========================================
// MOCK DATA
// ==========================================
const createMockToast = (overrides: Partial<ToastItem> = {}): ToastItem => ({
    id: 'toast-123',
    message: 'Pesan notifikasi',
    variant: 'success',
    duration: 5000,
    createdAt: Date.now(),
    ...overrides,
});

describe('Toast Component', () => {
    // ==========================================
    // 1. RENDERING & VARIANTS
    // ==========================================
    describe('Rendering', () => {
        it('harus merender pesan notifikasi', () => {
            render(Toast, { props: { toast: createMockToast({ message: 'Berhasil disimpan!' }), onDismiss: vi.fn() } });
            expect(screen.getByText('Berhasil disimpan!')).toBeInTheDocument();
        });

        it('harus memiliki role="alert" dan aria-live="polite" (a11y)', () => {
            render(Toast, { props: { toast: createMockToast(), onDismiss: vi.fn() } });
            const alert = screen.getByRole('alert');
            expect(alert).toHaveAttribute('aria-live', 'polite');
        });

        it('harus menerapkan class warna untuk variant "success" (hijau)', () => {
            const { container } = render(Toast, { 
                props: { toast: createMockToast({ variant: 'success' }), onDismiss: vi.fn() } 
            });
            const alert = container.firstElementChild;
            expect(alert).toHaveClass('bg-green-50');
        });

        it('harus menerapkan class warna untuk variant "error" (merah)', () => {
            const { container } = render(Toast, { 
                props: { toast: createMockToast({ variant: 'error' }), onDismiss: vi.fn() } 
            });
            const alert = container.firstElementChild;
            expect(alert).toHaveClass('bg-red-50');
        });
    });

    // ==========================================
    // 2. INTERAKSI CLOSE
    // ==========================================
    describe('Interaction', () => {
        it('harus memanggil onDismiss dengan ID saat tombol close diklik', async () => {
            const onDismiss = vi.fn();
            render(Toast, { 
                props: { toast: createMockToast({ id: 'toast-abc' }), onDismiss } 
            });

            const closeBtn = screen.getByRole('button', { name: /Tutup notifikasi/i });
            await fireEvent.click(closeBtn);

            expect(onDismiss).toHaveBeenCalledWith('toast-abc');
        });
    });

    // ==========================================
    // 3. ICON & A11Y
    // ==========================================
    describe('Accessibility', () => {
        it('ikon SVG harus disembunyikan dari screen reader', () => {
            const { container } = render(Toast, { props: { toast: createMockToast(), onDismiss: vi.fn() } });
            const svgs = container.querySelectorAll('svg');
            
            svgs.forEach((svg) => {
                expect(svg).toHaveAttribute('aria-hidden', 'true');
            });
        });
    });
});