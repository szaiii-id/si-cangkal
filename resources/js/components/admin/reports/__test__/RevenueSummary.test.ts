import { render, screen } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import RevenueSummary from '../RevenueSummary.svelte';

describe('RevenueSummary Component', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    // ==========================================
    // 1. RENDERING DASAR
    // ==========================================
    describe('Rendering', () => {
        it('harus merender label "Total Pendapatan"', () => {
            render(RevenueSummary, { props: { totalRevenue: 1500000 } });
            expect(screen.getByText('Total Pendapatan')).toBeInTheDocument();
        });

        it('harus merender nilai totalRevenue yang terformat', () => {
            render(RevenueSummary, { props: { totalRevenue: 1500000 } });
            expect(screen.getByText(/Rp 1.500.000/)).toBeInTheDocument();
        });
    });

    // ==========================================
    // 2. FORMATTING
    // ==========================================
    describe('Formatting', () => {
        it('harus memformat angka besar dengan titik ribuan', () => {
            render(RevenueSummary, { props: { totalRevenue: 12500000 } });
            expect(screen.getByText(/Rp 12.500.000/)).toBeInTheDocument();
        });

        it('harus menampilkan "Rp 0" jika totalRevenue=0', () => {
            render(RevenueSummary, { props: { totalRevenue: 0 } });
            expect(screen.getByText(/Rp 0/)).toBeInTheDocument();
        });
    });

    // ==========================================
    // 3. EDGE CASES
    // ==========================================
    describe('Edge Cases', () => {
        it('harus merender tanpa error jika totalRevenue tidak diberikan', () => {
            render(RevenueSummary, { props: {} });
            // Karena default = 0, harus menampilkan "Rp 0"
            expect(screen.getByText(/Rp 0/)).toBeInTheDocument();
        });
    });

    // ==========================================
    // 4. AKSESIBILITAS (A11Y)
    // ==========================================
    describe('Accessibility', () => {
        it('ikon SVG dekoratif harus disembunyikan dari screen reader', () => {
            const { container } = render(RevenueSummary, {
                props: { totalRevenue: 1500000 },
            });

            const svg = container.querySelector('svg');
            expect(svg?.closest('[aria-hidden="true"]')).toBeInTheDocument();
        });
    });
});
