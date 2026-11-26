import { render, screen } from '@testing-library/svelte';
import { describe, it, expect, vi } from 'vitest';
import { createRawSnippet } from 'svelte';
import LegalLayout from '../LegalLayout.svelte';

// ==========================================
// MOCK INERTIA (untuk use:inertia)
// ==========================================
vi.mock('@inertiajs/svelte', () => ({
    inertia: vi.fn(),
}));

describe('LegalLayout Component', () => {
    // Helper untuk membuat snippet children
    const mockChildren = createRawSnippet(() => ({
        render: () =>
            '<p data-testid="mock-content">Isi Kebijakan Privasi…</p>',
    }));

    const defaultProps = {
        title: 'Kebijakan Privasi',
        lastUpdated: '16 Oktober 2026',
        children: mockChildren,
    };

    // ==========================================
    // 1. RENDERING DASAR
    // ==========================================
    describe('Rendering', () => {
        it('harus merender judul, last updated, dan children', () => {
            render(LegalLayout, { props: defaultProps });

            expect(
                screen.getByRole('heading', { name: 'Kebijakan Privasi' }),
            ).toBeInTheDocument();
            expect(screen.getByText(/16 Oktober 2026/)).toBeInTheDocument();
            expect(screen.getByTestId('mock-content')).toBeInTheDocument();
        });

        it('harus merender tautan "Kembali ke Beranda"', () => {
            render(LegalLayout, { props: defaultProps });

            const backLink = screen.getByLabelText('Kembali ke Beranda');
            expect(backLink).toBeInTheDocument();
            expect(backLink).toHaveAttribute('href', '/');
        });
    });

    // ==========================================
    // 2. DOCUMENT HEAD (Meta)
    // ==========================================
    describe('Document Head', () => {
        it('harus mengubah document.title menjadi "{title} | SI-CANGKAL"', () => {
            render(LegalLayout, { props: defaultProps });
            expect(document.title).toBe('Kebijakan Privasi | SI-CANGKAL');
        });

        it('harus menambahkan meta description', () => {
            render(LegalLayout, {
                props: { ...defaultProps, description: 'Deskripsi Khusus' },
            });

            const metaDescription = document.querySelector(
                'meta[name="description"]',
            );
            expect(metaDescription).toHaveAttribute(
                'content',
                'Deskripsi Khusus',
            );
        });

        it('harus menggunakan meta description default jika tidak diberikan', () => {
            render(LegalLayout, { props: defaultProps });

            const metaDescription = document.querySelector(
                'meta[name="description"]',
            );
            expect(metaDescription).toHaveAttribute(
                'content',
                'Dokumen hukum resmi SI-CANGKAL.',
            );
        });
    });

    // ==========================================
    // 3. AKSESIBILITAS (A11Y)
    // ==========================================
    describe('Accessibility', () => {
        it('artikel harus terhubung dengan judul (aria-labelledby)', () => {
            render(LegalLayout, { props: defaultProps });

            const article = screen.getByRole('article');
            expect(article).toHaveAttribute('aria-labelledby', 'legal-title');
        });

        it('ikon SVG harus disembunyikan dari screen reader', () => {
            const { container } = render(LegalLayout, { props: defaultProps });

            const svg = container.querySelector('svg');
            expect(svg).toHaveAttribute('aria-hidden', 'true');
        });

        it('tanggal harus menggunakan tag <time> untuk aksesibilitas', () => {
            render(LegalLayout, { props: defaultProps });

            const timeElement = screen.getByText('16 Oktober 2026');
            expect(timeElement.tagName).toBe('TIME');
        });
    });
});
