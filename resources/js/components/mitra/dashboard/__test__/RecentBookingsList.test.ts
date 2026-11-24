import { render, screen } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import RecentBookingsList from '../RecentBookingsList.svelte';

// ==========================================
// MOCK INERTIA
// ==========================================
vi.mock('@inertiajs/svelte', () => ({
    inertia: vi.fn(),
}));

// ==========================================
// MOCK DATA
// ==========================================
const createMockBooking = (overrides = {}) => ({
    id: 1,
    kode: 'BK-001',
    client: 'Budi',
    paket: 'Paket Bali',
    total: 1500000,
    status: 'confirmed',
    tanggal: '2026-10-20',
    ...overrides,
});

describe('RecentBookingsList Component', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    // ==========================================
    // 1. RENDERING DASAR
    // ==========================================
    describe('Rendering', () => {
        it('harus merender judul "Pesanan Terbaru"', () => {
            render(RecentBookingsList, {
                props: { bookings: [createMockBooking()] },
            });
            expect(
                screen.getByRole('heading', { name: 'Pesanan Terbaru' }),
            ).toBeInTheDocument();
        });

        it('harus merender tautan "Lihat Semua"', () => {
            render(RecentBookingsList, {
                props: { bookings: [createMockBooking()] },
            });

            const link = screen.getByLabelText('Lihat semua pesanan');
            expect(link).toBeInTheDocument();
            expect(link).toHaveAttribute('href', '/mitra/bookings');
        });

        it('harus merender data pesanan (kode, client, paket, total, status) beserta tautan detail', () => {
            render(RecentBookingsList, {
                props: { bookings: [createMockBooking()] },
            });

            expect(screen.getByText('BK-001')).toBeInTheDocument();
            expect(screen.getByText(/Budi — Paket Bali/)).toBeInTheDocument();
            expect(screen.getByText(/Rp\s?1\.500\.000/)).toBeInTheDocument();
            expect(screen.getByText('confirmed')).toBeInTheDocument();

            const detailLink = screen.getByRole('link', { name: /BK-001/i });
            expect(detailLink).toHaveAttribute('href', '/mitra/bookings/1');
        });
    });

    // ==========================================
    // 2. EMPTY STATE
    // ==========================================
    describe('Empty State', () => {
        it('harus menampilkan pesan kosong jika bookings kosong', () => {
            render(RecentBookingsList, { props: { bookings: [] } });
            expect(
                screen.getByText('Belum ada pesanan terbaru.'),
            ).toBeInTheDocument();
        });
    });

    // ==========================================
    // 3. FORMATTING
    // ==========================================
    describe('Formatting', () => {
        it('harus memformat total menjadi Rupiah', () => {
            render(RecentBookingsList, {
                props: { bookings: [createMockBooking({ total: 2500000 })] },
            });
            expect(screen.getByText(/Rp\s?2\.500\.000/)).toBeInTheDocument();
        });
    });

    // ==========================================
    // 4. AKSESIBILITAS (A11Y)
    // ==========================================
    describe('Accessibility', () => {
        it('setiap kartu pesanan harus dapat diakses sebagai tautan menuju detail pesanan masing-masing', () => {
            render(RecentBookingsList, {
                props: {
                    bookings: [
                        createMockBooking({ id: 1, kode: 'BK-001' }),
                        createMockBooking({ id: 2, kode: 'BK-002' }),
                    ],
                },
            });

            const firstLink = screen.getByRole('link', { name: /BK-001/i });
            const secondLink = screen.getByRole('link', { name: /BK-002/i });

            expect(firstLink).toHaveAttribute('href', '/mitra/bookings/1');
            expect(secondLink).toHaveAttribute('href', '/mitra/bookings/2');
        });

        it('empty state harus memiliki role="status"', () => {
            render(RecentBookingsList, { props: { bookings: [] } });

            const emptyState = screen.getByRole('status');
            expect(emptyState).toHaveTextContent('Belum ada pesanan terbaru.');
        });
    });
});
