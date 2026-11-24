import { render, screen } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import RecentBookings from '../RecentBookings.svelte';

// ==========================================
// MOCK INERTIA
// ==========================================
vi.mock('@inertiajs/svelte', () => ({
    inertia: vi.fn(),
}));

// ==========================================
// MOCK DATA
// ==========================================
const createMockBooking = (overrides: Record<string, unknown> = {}) => ({
    id: 1,
    paket: 'Paket Bali',
    client: 'Budi',
    status: 'confirmed',
    total: 1500000,
    ...overrides,
});

describe('RecentBookings Component', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    // ==========================================
    // 1. RENDERING DASAR
    // ==========================================
    describe('Rendering', () => {
        it('harus merender judul "Pesanan Terbaru"', () => {
            render(RecentBookings, {
                props: { bookings: [createMockBooking()] },
            });
            expect(screen.getByText('Pesanan Terbaru')).toBeInTheDocument();
        });

        it('harus merender tautan "Lihat Semua"', () => {
            render(RecentBookings, {
                props: { bookings: [createMockBooking()] },
            });

            const link = screen.getByLabelText('Lihat semua pesanan');
            expect(link).toBeInTheDocument();
            expect(link).toHaveAttribute('href', '/admin/bookings');
        });

        it('harus merender data pesanan (paket, client, status, total)', () => {
            render(RecentBookings, {
                props: { bookings: [createMockBooking()] },
            });

            expect(screen.getByText('Paket Bali')).toBeInTheDocument();
            expect(screen.getByText('Budi')).toBeInTheDocument();
            expect(screen.getByText(/Rp 1.500.000/)).toBeInTheDocument();
        });
    });

    // ==========================================
    // 2. EMPTY STATE
    // ==========================================
    describe('Empty State', () => {
        it('harus menampilkan pesan kosong jika bookings kosong', () => {
            render(RecentBookings, { props: { bookings: [] } });
            expect(
                screen.getByText('Belum ada pesanan terbaru saat ini.'),
            ).toBeInTheDocument();
        });
    });

    // ==========================================
    // 3. FORMATTING
    // ==========================================
    describe('Formatting', () => {
        it('harus memformat total menjadi Rupiah', () => {
            render(RecentBookings, {
                props: { bookings: [createMockBooking({ total: 2500000 })] },
            });
            expect(screen.getByText(/Rp 2.500.000/)).toBeInTheDocument();
        });
    });

    // ==========================================
    // 4. AKSESIBILITAS (A11Y)
    // ==========================================
    describe('Accessibility', () => {
        it('ikon SVG dekoratif harus disembunyikan dari screen reader', () => {
            const { container } = render(RecentBookings, {
                props: { bookings: [createMockBooking()] },
            });

            const svgs = container.querySelectorAll('svg');
            svgs.forEach((svg) => {
                expect(svg).toHaveAttribute('aria-hidden', 'true');
            });
        });

        it('empty state harus memiliki role="status"', () => {
            render(RecentBookings, { props: { bookings: [] } });

            const emptyState = screen.getByRole('status');
            expect(emptyState).toHaveTextContent(
                'Belum ada pesanan terbaru saat ini.',
            );
        });
    });
});
