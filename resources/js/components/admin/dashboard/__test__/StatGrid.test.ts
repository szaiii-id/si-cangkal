import { render, screen } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import StatGrid from '../StatsGrid.svelte';

// ==========================================
// MOCK INERTIA
// ==========================================
vi.mock('@inertiajs/svelte', () => ({
    inertia: vi.fn(),
}));

// ==========================================
// MOCK DATA
// ==========================================
const createMockStats = (overrides = {}) => ({
    total_revenue: 12500000,
    users: { mitra: 15 },
    bookings: { total: 120 },
    trips: { total: 30 },
    ...overrides,
});

describe('StatGrid Component', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    // ==========================================
    // 1. RENDERING DASAR
    // ==========================================
    describe('Rendering', () => {
        it('harus merender semua label statistik', () => {
            render(StatGrid, { props: { stats: createMockStats() } });

            expect(screen.getByText('Total Pendapatan')).toBeInTheDocument();
            expect(screen.getByText('Total Mitra')).toBeInTheDocument();
            expect(screen.getByText('Total Booking')).toBeInTheDocument();
            expect(screen.getByText('Total Trip')).toBeInTheDocument();
        });

        it('harus merender nilai total_revenue yang terformat', () => {
            render(StatGrid, { props: { stats: createMockStats() } });
            expect(screen.getByText(/Rp 12.500.000/)).toBeInTheDocument();
        });

        it('harus merender nilai users.mitra', () => {
            render(StatGrid, { props: { stats: createMockStats() } });
            expect(screen.getByText('15')).toBeInTheDocument();
        });

        it('harus merender nilai bookings.total', () => {
            render(StatGrid, { props: { stats: createMockStats() } });
            expect(screen.getByText('120')).toBeInTheDocument();
        });

        it('harus merender nilai trips.total', () => {
            render(StatGrid, { props: { stats: createMockStats() } });
            expect(screen.getByText('30')).toBeInTheDocument();
        });
    });

    // ==========================================
    // 2. TAUTAN DETAIL
    // ==========================================
    describe('Detail Links', () => {
        it('harus merender tautan "Detail" untuk Mitra', () => {
            render(StatGrid, { props: { stats: createMockStats() } });

            const link = screen.getByLabelText('Lihat detail Total Mitra');
            expect(link).toBeInTheDocument();
            expect(link).toHaveAttribute('href', '/admin/mitras');
        });

        it('harus merender tautan "Detail" untuk Booking', () => {
            render(StatGrid, { props: { stats: createMockStats() } });

            const link = screen.getByLabelText('Lihat detail Total Booking');
            expect(link).toBeInTheDocument();
            expect(link).toHaveAttribute('href', '/admin/bookings');
        });

        it('harus merender tautan "Detail" untuk Trip', () => {
            render(StatGrid, { props: { stats: createMockStats() } });

            const link = screen.getByLabelText('Lihat detail Total Trip');
            expect(link).toBeInTheDocument();
            expect(link).toHaveAttribute('href', '/admin/trips');
        });
    });

    // ==========================================
    // 3. AKSESIBILITAS (A11Y)
    // ==========================================
    describe('Accessibility', () => {
        it('ikon SVG dekoratif harus disembunyikan dari screen reader', () => {
            const { container } = render(StatGrid, {
                props: { stats: createMockStats() },
            });

            const svgs = container.querySelectorAll('svg');
            svgs.forEach((svg) => {
                expect(svg).toHaveAttribute('aria-hidden', 'true');
            });
        });
    });
});
