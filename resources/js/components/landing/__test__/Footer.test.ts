import { render, screen } from '@testing-library/svelte';
import { describe, it, expect, vi } from 'vitest';
import Footer from '../Footer.svelte';

// ==========================================
// MOCK INERTIA (untuk use:inertia)
// ==========================================
vi.mock('@inertiajs/svelte', () => ({
    inertia: vi.fn(),
}));

describe('Footer Component', () => {
    // ==========================================
    // 1. RENDERING DASAR
    // ==========================================
    describe('Rendering', () => {
        it('harus merender brand SI-CANGKAL', () => {
            render(Footer);
            // "SI-CANGKAL" mungkin muncul di beberapa tempat, gunakan getAllByText
            const brands = screen.getAllByText(/SI-CANGKAL/i);
            expect(brands.length).toBeGreaterThan(0);
        });

        it('harus merender deskripsi perusahaan', () => {
            render(Footer);
            expect(screen.getByText(/Sistem Implementasi Capaian Akselerasi/i)).toBeInTheDocument();
        });

        it('harus merender informasi hak cipta', () => {
            render(Footer);
            expect(screen.getByText(/© 2026 SI-CANGKAL/i)).toBeInTheDocument();
        });
    });

    // ==========================================
    // 2. NAVIGASI CEPAT
    // ==========================================
    describe('Navigasi Cepat', () => {
        it('harus merender semua tautan navigasi dengan href yang benar', () => {
            render(Footer);

            const links = [
                { label: 'Beranda', href: '#beranda' },
                { label: 'Paket Wisata', href: '#trip' },
                { label: 'Visi & Misi', href: '#visimisi' },
                { label: 'Kontak', href: '#contact' },
            ];

            links.forEach((link) => {
                const element = screen.getByRole('link', { name: link.label });
                expect(element).toBeInTheDocument();
                expect(element).toHaveAttribute('href', link.href);
            });
        });
    });

    // ==========================================
    // 3. LEGAL & KEBIJAKAN
    // ==========================================
    describe('Legal & Kebijakan', () => {
        it('harus merender semua tautan legal dengan href yang benar', () => {
            render(Footer);

            const legalLinks = [
                { label: 'Kebijakan Privasi', href: '/legal/privacy' },
                { label: 'Syarat & Ketentuan', href: '/legal/terms' },
                { label: 'Kebijakan Refund', href: '/legal/refund-policy' },
                { label: 'Kebijakan Cookie', href: '/legal/cookie-policy' },
                { label: 'Aturan Mitra', href: '/legal/mitra-rules' },
                { label: 'Penggunaan Wajar', href: '/legal/acceptable-use' },
            ];

            legalLinks.forEach((link) => {
                const element = screen.getByRole('link', { name: link.label });
                expect(element).toBeInTheDocument();
                expect(element).toHaveAttribute('href', link.href);
            });
        });
    });

    // ==========================================
    // 4. AKSESIBILITAS (A11Y)
    // ==========================================
    describe('Accessibility', () => {
        it('harus memiliki elemen <nav> dengan aria-labelledby', () => {
            render(Footer);

            // Ada dua <nav> (navigasi & legal)
            const navs = screen.getAllByRole('navigation');
            expect(navs.length).toBe(2);

            // Pastikan masing-masing memiliki aria-labelledby
            expect(navs[0]).toHaveAttribute('aria-labelledby', 'footer-nav-heading');
            expect(navs[1]).toHaveAttribute('aria-labelledby', 'footer-legal-heading');
        });
    });
});