import { render, screen } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import BookingTable from '../BookingTable.svelte';
import type { PaginationMeta } from '@/types';

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
    kode: 'BK-123',
    status: 'pending_payment',
    tanggal_keberangkatan: '2026-10-20',
    jumlah_peserta: 2,
    total_harga: '1500000',
    expired_at: null,
    created_at: '2026-10-16T10:00:00Z',
    package: { nama: 'Paket Bali' },
    client: { name: 'Budi', email: 'budi@test.com' },
    ...overrides,
});

const createMockPagination = (
    data: any[],
): PaginationMeta & { data: any[] } => ({
    current_page: 1,
    last_page: 1,
    from: 1,
    to: data.length,
    total: data.length,
    per_page: 10,
    links: [],
    data,
});

describe('BookingTable Component', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    // ==========================================
    // 1. RENDERING DASAR
    // ==========================================
    describe('Rendering', () => {
        it('harus merender kode booking dan nama paket', () => {
            render(BookingTable, {
                props: {
                    bookings: createMockPagination([createMockBooking()]),
                },
            });

            // Kode muncul di mobile & desktop
            expect(screen.getAllByText('BK-123').length).toBeGreaterThan(0);
            expect(screen.getAllByText('Paket Bali').length).toBeGreaterThan(0);
        });

        it('harus merender nama pelanggan', () => {
            render(BookingTable, {
                props: {
                    bookings: createMockPagination([createMockBooking()]),
                },
            });

            expect(screen.getAllByText('Budi').length).toBeGreaterThan(0);
        });
    });

    // ==========================================
    // 2. BADGE EXPIRED
    // ==========================================
    describe('Expired Badge', () => {
        it('TIDAK menampilkan badge jika status bukan pending_payment', () => {
            render(BookingTable, {
                props: {
                    bookings: createMockPagination([
                        createMockBooking({ status: 'confirmed' }),
                    ]),
                },
            });

            expect(
                screen.queryByText('Hampir expired'),
            ).not.toBeInTheDocument();
            expect(screen.queryByText('Overdue')).not.toBeInTheDocument();
        });

        it('menampilkan badge "Hampir expired" jika countdown < 6 jam', () => {
            const expiredAt = new Date(
                Date.now() + 2 * 60 * 60 * 1000,
            ).toISOString(); // 2 jam dari sekarang
            render(BookingTable, {
                props: {
                    bookings: createMockPagination([
                        createMockBooking({
                            status: 'pending_payment',
                            expired_at: expiredAt,
                        }),
                    ]),
                },
            });

            expect(
                screen.getAllByText('Hampir expired').length,
            ).toBeGreaterThan(0);
        });

        it('menampilkan badge "Overdue" jika sudah kedaluwarsa', () => {
            const expiredAt = new Date(
                Date.now() - 2 * 60 * 60 * 1000,
            ).toISOString(); // 2 jam yang lalu
            render(BookingTable, {
                props: {
                    bookings: createMockPagination([
                        createMockBooking({
                            status: 'pending_payment',
                            expired_at: expiredAt,
                        }),
                    ]),
                },
            });

            expect(screen.getAllByText('Overdue').length).toBeGreaterThan(0);
        });
    });

    // ==========================================
    // 3. FORMATTING
    // ==========================================
    describe('Formatting', () => {
        it('harus memformat total harga menjadi Rupiah', () => {
            render(BookingTable, {
                props: {
                    bookings: createMockPagination([createMockBooking()]),
                },
            });

            expect(screen.getAllByText(/Rp 1.500.000/).length).toBeGreaterThan(
                0,
            );
        });

        it('harus memformat tanggal keberangkatan', () => {
            render(BookingTable, {
                props: {
                    bookings: createMockPagination([createMockBooking()]),
                },
            });

            expect(screen.getAllByText(/20 Okt 2026/).length).toBeGreaterThan(
                0,
            );
        });
    });

    // ==========================================
    // 4. AKSESIBILITAS (A11Y)
    // ==========================================
    describe('Accessibility', () => {
        it('tabel harus memiliki aria-label', () => {
            render(BookingTable, {
                props: {
                    bookings: createMockPagination([createMockBooking()]),
                },
            });

            const table = screen.getByRole('table', {
                name: /Daftar Booking/i,
            });
            expect(table).toBeInTheDocument();
        });
    });
});
