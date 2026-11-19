import { render, screen } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import RevenueChart from '../RevenueChart.svelte';

// ==========================================
// MOCK AreaChart (MENGGUNAKAN FILE FISIK)
// ==========================================
vi.mock('@faintshadow/flarecharts', async () => {
    const { default: AreaChart } = await import('../__mocks__/AreaChart.svelte');
    return { AreaChart };
});

// ==========================================
// MOCK INERTIA
// ==========================================
vi.mock('@inertiajs/svelte', () => ({
    inertia: vi.fn(),
}));

// ==========================================
// MOCK DATA
// ==========================================
const createMockRevenue = (overrides = {}) => ({
    bulan: 'Jan',
    total: 1500000,
    ...overrides,
});

describe('RevenueChart Component', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    // ==========================================
    // 1. RENDERING DASAR
    // ==========================================
    describe('Rendering', () => {
        it('harus merender judul dan deskripsi', () => {
            render(RevenueChart, { props: { monthly_revenue: [createMockRevenue()] } });

            expect(screen.getByRole('heading', { name: 'Tren Pendapatan' })).toBeInTheDocument();
            expect(screen.getByText('Pergerakan pendapatan 6 bulan terakhir')).toBeInTheDocument();
        });

        it('harus merender tautan "Lihat Detail"', () => {
            render(RevenueChart, { props: { monthly_revenue: [createMockRevenue()] } });

            const link = screen.getByLabelText('Lihat detail laporan pendapatan');
            expect(link).toBeInTheDocument();
            expect(link).toHaveAttribute('href', '/admin/reports');
            expect(link).toHaveTextContent('Lihat Detail');
        });

        it('harus merender AreaChart (mock)', () => {
            render(RevenueChart, { props: { monthly_revenue: [createMockRevenue()] } });

            expect(screen.getByTestId('area-chart')).toBeInTheDocument();
        });
    });

    // ==========================================
    // 2. DATA MAPPING
    // ==========================================
    describe('Data Mapping', () => {
        it('harus memetakan monthly_revenue ke format series', () => {
            const monthly_revenue = [
                { bulan: 'Jan', total: 1000000 },
                { bulan: 'Feb', total: 2000000 },
            ];
            render(RevenueChart, { props: { monthly_revenue } });

            const chart = screen.getByTestId('area-chart');
            const seriesData = JSON.parse(chart.getAttribute('data-series')!);

            expect(seriesData).toHaveLength(1);
            expect(seriesData[0].name).toBe('Pendapatan');
            expect(seriesData[0].color).toBe('#F3278C');
            expect(seriesData[0].data).toHaveLength(2);
            expect(seriesData[0].data[0]).toEqual({ x: 'Jan', y: 1000000 });
            expect(seriesData[0].data[1]).toEqual({ x: 'Feb', y: 2000000 });
        });
    });

    // ==========================================
    // 3. AKSESIBILITAS (A11Y)
    // ==========================================
    describe('Accessibility', () => {
        it('tautan detail laporan harus memiliki aria-label yang deskriptif', () => {
            render(RevenueChart, {
                props: { monthly_revenue: [createMockRevenue()] },
            });

            const detailLink = screen.getByRole('link', {
                name: 'Lihat detail laporan pendapatan',
            });
            expect(detailLink).toBeInTheDocument();
            expect(detailLink).toHaveAttribute('href', '/admin/reports');
        });
    });

    // ==========================================
    // 4. EDGE CASES
    // ==========================================
    describe('Edge Cases', () => {
        it('harus merender tanpa error jika monthly_revenue kosong', () => {
            render(RevenueChart, { props: { monthly_revenue: [] } });
            expect(screen.getByText('Tren Pendapatan')).toBeInTheDocument();

            const chart = screen.getByTestId('area-chart');
            const seriesData = JSON.parse(chart.getAttribute('data-series')!);
            expect(seriesData[0].data).toEqual([]);
        });

        it('harus merender tanpa error jika monthly_revenue tidak diberikan', () => {
            render(RevenueChart, { props: {} });
            expect(screen.getByText('Tren Pendapatan')).toBeInTheDocument();
        });
    });
});