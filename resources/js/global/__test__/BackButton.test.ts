import { render, screen } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import BackButton from '../BackButton.svelte';

// 1. MOCK MODUL INERTIA
vi.mock('@inertiajs/svelte', () => ({
    inertia: vi.fn(),
}));

describe('BackButton Component (Refactored)', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    // ==========================================
    // 1. PROPS & RENDERING
    // ==========================================
    describe('Rendering Props', () => {
        it('harus merender label default "Kembali"', () => {
            render(BackButton, { props: { href: '/admin/packages' } });
            expect(screen.getByText('Kembali')).toBeInTheDocument();
        });

        it('harus merender label kustom', () => {
            render(BackButton, { props: { href: '/admin/packages', label: 'Kembali ke Daftar Paket' } });
            expect(screen.getByText('Kembali ke Daftar Paket')).toBeInTheDocument();
        });

        it('harus menerapkan atribut href yang benar', () => {
            render(BackButton, { props: { href: '/admin/users' } });
            const link = screen.getByRole('link');
            expect(link).toHaveAttribute('href', '/admin/users');
        });

        it('harus menerapkan class tambahan dari props className', () => {
            render(BackButton, { props: { href: '/test', class: 'mb-5 custom-class' } });
            const link = screen.getByRole('link');
            expect(link).toHaveClass('mb-5');
            expect(link).toHaveClass('custom-class');
            // Pastikan class bawaan komponen tetap ada
            expect(link).toHaveClass('inline-flex');
        });
    });

    // ==========================================
    // 2. AKSESIBILITAS (a11y)
    // ==========================================
    describe('Accessibility', () => {
        it('harus memiliki aria-label yang sesuai dengan teks label', () => {
            render(BackButton, { props: { href: '/test', label: 'Kembali ke Beranda' } });
            const link = screen.getByRole('link', { name: 'Kembali ke Beranda' });
            expect(link).toBeInTheDocument();
        });

        it('ikon SVG harus disembunyikan dari screen reader (aria-hidden)', () => {
            const { container } = render(BackButton, { props: { href: '/test' } });
            const svg = container.querySelector('svg');
            expect(svg).toHaveAttribute('aria-hidden', 'true');
        });
    });

    // ==========================================
    // 3. USER INTERACTION
    // ==========================================
    describe('User Interaction', () => {
        it('harus menjadi link yang bisa diakses (enabled)', () => {
            render(BackButton, { props: { href: '/dashboard' } });
            const link = screen.getByRole('link');
            expect(link).toBeEnabled();
        });
    });
});