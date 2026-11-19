import { render, screen } from '@testing-library/svelte';
import { describe, it, expect, vi } from 'vitest';
import { createRawSnippet } from 'svelte';
import AuthLayout from '../AuthLayout.svelte';

// ==========================================
// MOCK DEPENDENCIES (Mengarah ke file fisik)
// ==========================================
vi.mock('@/components/ui/ToastContainer.svelte', () => import('@/components/ui/__mocks__/ToastContainer.svelte'));

describe('AuthLayout Component', () => {
    // Helper untuk membuat snippet children
    const mockChildren = createRawSnippet(() => ({
        render: () => '<div data-testid="mock-children">Form Login</div>',
    }));

    // ==========================================
    // 1. RENDERING DASAR
    // ==========================================
    describe('Rendering', () => {
        it('harus merender children (konten form)', () => {
            render(AuthLayout, {
                props: { title: 'Login', children: mockChildren },
            });

            expect(screen.getByTestId('mock-children')).toBeInTheDocument();
            expect(screen.getByText('Form Login')).toBeInTheDocument();
        });

        it('harus merender ToastContainer', () => {
            render(AuthLayout, {
                props: { title: 'Login', children: mockChildren },
            });

            // Sekarang ToastContainer akan merender div dengan data-testid
            expect(screen.getByTestId('toast-container')).toBeInTheDocument();
        });
    });

    // ==========================================
    // 2. HEAD TITLE (Meta)
    // ==========================================
    describe('Document Title', () => {
        it('harus mengubah document.title menjadi "{title} | SI-CANGKAL"', () => {
            render(AuthLayout, {
                props: { title: 'Masuk Akun', children: mockChildren },
            });

            expect(document.title).toBe('Masuk Akun | SI-CANGKAL');
        });

        it('harus menggunakan fallback "Autentikasi" jika title tidak diberikan', () => {
            render(AuthLayout, {
                props: { children: mockChildren },
            });

            expect(document.title).toBe('Autentikasi | SI-CANGKAL');
        });
    });

    // ==========================================
    // 3. AKSESIBILITAS (A11Y)
    // ==========================================
    describe('Accessibility', () => {
        it('efek background dekoratif harus disembunyikan dari screen reader', () => {
            const { container } = render(AuthLayout, {
                props: { title: 'Login', children: mockChildren },
            });

            const decorativeDiv = container.querySelector('[aria-hidden="true"]');
            expect(decorativeDiv).toBeInTheDocument();
            expect(decorativeDiv).toHaveClass('pointer-events-none');
        });
    });
});