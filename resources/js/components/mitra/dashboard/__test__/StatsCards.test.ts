import { render, screen } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import StatsCards from '../StatsCards.svelte';

// ==========================================
// MOCK INERTIA
// ==========================================
vi.mock('@inertiajs/svelte', () => ({
    inertia: vi.fn(),
}));

describe('StatsCards Component', () => {
    const defaultProps = {
        totalRevenue: 12500000,
        totalPackages: 5,
        totalTrips: 12,
        totalBookings: 30,
    };

    beforeEach(() => {
        vi.clearAllMocks();
    });

    // ==========================================
    // 1. RENDERING DASAR
    // ==========================================
    describe('Rendering', () => {
        it('harus merender semua label kartu', () => {
            render(StatsCards, { props: defaultProps });

            expect(screen.getByText('Total Pendapatan')).toBeInTheDocument();
            expect(screen.getByText('Paket Wisata')).toBeInTheDocument();
            expect(screen.getByText('Trip')).toBeInTheDocument();
            expect(screen.getByText('Booking Masuk')).toBeInTheDocument();
        });

        it('harus merender nilai totalRevenue yang terformat', () => {
            render(StatsCards, { props: defaultProps });
            expect(screen.getByText(/Rp 12.500.000/)).toBeInTheDocument();
        });

        it('harus merender nilai totalPackages', () => {
            render(StatsCards, { props: defaultProps });
            expect(screen.getByText('5')).toBeInTheDocument();
        });

        it('harus merender nilai totalTrips', () => {
            render(StatsCards, { props: defaultProps });
            expect(screen.getByText('12')).toBeInTheDocument();
        });

        it('harus merender nilai totalBookings', () => {
            render(StatsCards, { props: defaultProps });
            expect(screen.getByText('30')).toBeInTheDocument();
        });
    });

    // ==========================================
    // 2. TAUTAN
    // ==========================================
    describe('Links', () => {
        it('harus merender tautan "Kelola Paket"', () => {
            render(StatsCards, { props: defaultProps });
            
            const link = screen.getByLabelText('Kelola Paket (Paket Wisata)');
            expect(link).toBeInTheDocument();
            expect(link).toHaveAttribute('href', '/mitra/packages');
        });

        it('harus merender tautan "Lihat Trip"', () => {
            render(StatsCards, { props: defaultProps });
            
            const link = screen.getByLabelText('Lihat Trip (Trip)');
            expect(link).toBeInTheDocument();
            expect(link).toHaveAttribute('href', '/mitra/trips');
        });

        it('harus merender tautan "Lihat Booking"', () => {
            render(StatsCards, { props: defaultProps });
            
            const link = screen.getByLabelText('Lihat Booking (Booking Masuk)');
            expect(link).toBeInTheDocument();
            expect(link).toHaveAttribute('href', '/mitra/bookings');
        });
    });

    // ==========================================
    // 3. AKSESIBILITAS (A11Y)
    // ==========================================
    describe('Accessibility', () => {
        it('ikon SVG dekoratif harus disembunyikan dari screen reader', () => {
            const { container } = render(StatsCards, { props: defaultProps });
            
            const svgs = container.querySelectorAll('svg');
            svgs.forEach((svg) => {
                expect(svg.closest('[aria-hidden="true"]')).toBeInTheDocument();
            });
        });
    });
});