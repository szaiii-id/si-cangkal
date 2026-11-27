/**
 * ============================================================
 * Client Bookings — Index Page — Component Test
 * ============================================================
 *
 * Fokus pengujian:
 *   1. Isolation & Mocking  → Inertia (router), enums, countdown,
 *                             format helpers, ClientLayout, Pagination.
 *   2. Props & Rendering    → header, search, tabs, list cards,
 *                             countdown badge, empty state.
 *   3. Interaction          → search debounce, tab change, clear filters.
 *   4. Reactivity           → hasActiveFilters derived, conditional empty state.
 * ============================================================
 */

import { render, screen, fireEvent } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import Index from '../Index.svelte';

// ============================================================
// 1. HOISTED MOCK REGISTRY
// ============================================================
const mockRouterGet = vi.hoisted(() => vi.fn());

// ============================================================
// 2. MOCK — INERTIA
// ============================================================
vi.mock('@inertiajs/svelte', () => ({
    inertia: vi.fn(),
    router: { get: mockRouterGet },
}));

// ============================================================
// 3. MOCK — ENUMS
// ============================================================
vi.mock('@/utils/enums', () => ({
    bookingStatusBadge: vi.fn((s: string) => `badge-${s}`),
    bookingStatusLabel: vi.fn((s: string) => {
        const map: Record<string, string> = {
            pending_payment: 'Menunggu Bayar',
            payment_verified: 'Bayar Terverifikasi',
            confirmed: 'Terkonfirmasi',
            completed: 'Selesai',
            cancelled: 'Dibatalkan',
        };
        return map[s] ?? s;
    }),
    bookingStatusShort: vi.fn((s: string) => {
        const map: Record<string, string> = {
            pending_payment: 'Menunggu',
            confirmed: 'Terkonfirmasi',
        };
        return map[s] ?? s;
    }),
}));

// ============================================================
// 4. MOCK — BOOKING RULES & COUNTDOWN
// ============================================================
vi.mock('@/utils/bookingRules', () => ({
    BOOKING_STATUS_ORDER: [
        'pending_payment',
        'payment_verified',
        'confirmed',
        'completed',
        'cancelled',
    ],
    isPendingPayment: vi.fn((s: string) => s === 'pending_payment'),
}));

vi.mock('@/utils/countdown', () => ({
    formatBookingCountdown: vi.fn((expiredAt: string | null) => {
        if (!expiredAt) return null;
        return {
            isExpired: false,
            isExpiringSoon: true,
            label: '1 jam 30 menit',
        };
    }),
}));

// ============================================================
// 5. MOCK — FORMAT HELPERS
// ============================================================
vi.mock('@/utils/formatCurrency', () => ({
    formatCurrency: vi.fn(
        (v: string | number) => `Rp ${Number(v).toLocaleString('id-ID')}`,
    ),
}));

vi.mock('@/utils/formatDate', () => ({
    formatDate: vi.fn((iso: string) => `Date: ${iso}`),
}));

// ============================================================
// 6. MOCK — CHILD COMPONENTS
// ============================================================
vi.mock('@/components/layout/ClientLayout.svelte', () => ({
    default: (anchor: Node, props: { children?: (anchor: Node) => void }) => {
        props.children?.(anchor);
        return {};
    },
}));

vi.mock('@/global/Pagination.svelte', () => ({
    default: (
        anchor: Node,
        props: { meta: { total: number }; label: string },
    ) => {
        const el = document.createElement('div');
        el.setAttribute('data-testid', 'pagination');
        el.textContent = `Total ${props.meta.total} ${props.label}`;
        anchor.parentNode?.insertBefore(el, anchor);
        return {};
    },
}));

// ============================================================
// 7. FIXTURES
// ============================================================
const makeBooking = (overrides = {}) => ({
    id: 1,
    kode: 'BK-001',
    status: 'pending_payment',
    tanggal_keberangkatan: '2026-02-01',
    jumlah_peserta: 2,
    total_harga: '1500000',
    expired_at: '2026-01-20T12:00:00Z',
    created_at: '2026-01-15T10:00:00Z',
    package: {
        id: 1,
        nama: 'Paket Bali',
        kode: 'PKT-001',
        timezone: 'Asia/Jakarta',
    },
    client: { id: '1', name: 'Budi', email: 'budi@test.com' },
    ...overrides,
});

const makeBookings = (data: any[], overrides = {}) => ({
    current_page: 1,
    last_page: 1,
    per_page: 15,
    total: data.length,
    from: data.length ? 1 : null,
    to: data.length ? data.length : null,
    links: [],
    data,
    ...overrides,
});

const DEFAULT_FILTERS = {
    search: '',
    status: '',
    sort: 'created_at',
    direction: 'desc',
};

const DEFAULT_STATUS_COUNTS = {
    pending_payment: 0,
    payment_verified: 0,
    confirmed: 0,
    in_progress: 0,
    completed: 0,
    cancelled: 0,
};

const renderPage = (
    bookings = makeBookings([makeBooking()]),
    filters = DEFAULT_FILTERS,
    statusCounts = { ...DEFAULT_STATUS_COUNTS, pending_payment: 1 },
) =>
    render(Index, {
        props: { bookings, filters, statusCounts },
    });

// ============================================================
// 8. TEST SUITE
// ============================================================
describe('Client Bookings — Index Page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        vi.useFakeTimers();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    // ----------------------------------------------------------
    // 8.1 RENDERING — Header
    // ----------------------------------------------------------
    describe('Rendering — Header', () => {
        it('menampilkan judul & deskripsi', () => {
            renderPage();
            expect(
                screen.getByRole('heading', { level: 1, name: 'Pesanan Saya' }),
            ).toBeInTheDocument();
            expect(
                screen.getByText(
                    'Pantau status perjalanan dan unduh tiket perjalanan Anda di sini.',
                ),
            ).toBeInTheDocument();
        });
    });

    // ----------------------------------------------------------
    // 8.2 RENDERING — Status Tabs
    // ----------------------------------------------------------
    describe('Rendering — Status Tabs', () => {
        it('menampilkan tab "Semua" dengan total semua status', () => {
            renderPage(makeBookings([makeBooking()]), DEFAULT_FILTERS, {
                ...DEFAULT_STATUS_COUNTS,
                pending_payment: 2,
                confirmed: 1,
            });

            const allTab = screen.getByRole('button', { name: /Semua 3/i });
            expect(allTab).toBeInTheDocument();
        });

        it('menampilkan tab status dengan count > 0', () => {
            renderPage(makeBookings([makeBooking()]), DEFAULT_FILTERS, {
                ...DEFAULT_STATUS_COUNTS,
                pending_payment: 1,
            });
            expect(
                screen.getByRole('button', { name: /Menunggu 1/i }),
            ).toBeInTheDocument();
        });

        it('tab status aktif diberi class bg-dark', () => {
            renderPage(
                makeBookings([makeBooking()]),
                {
                    ...DEFAULT_FILTERS,
                    status: 'pending_payment',
                },
                {
                    ...DEFAULT_STATUS_COUNTS,
                    pending_payment: 1,
                },
            );

            const activeTab = screen.getByRole('button', {
                name: /Menunggu 1/i,
            });
            expect(activeTab).toHaveClass('bg-dark');
        });
    });

    // ----------------------------------------------------------
    // 8.3 RENDERING — Search Input
    // ----------------------------------------------------------
    describe('Rendering — Search', () => {
        it('menampilkan input search dengan placeholder', () => {
            renderPage();
            expect(
                screen.getByPlaceholderText(
                    'Cari kode pesanan atau nama paket…',
                ),
            ).toBeInTheDocument();
        });

        it('menampilkan tombol "Hapus pencarian" jika search terisi', () => {
            renderPage(makeBookings([makeBooking()]), {
                ...DEFAULT_FILTERS,
                search: 'bali',
            });
            expect(
                screen.getByLabelText('Hapus pencarian'),
            ).toBeInTheDocument();
        });

        it('TIDAK menampilkan tombol "Hapus pencarian" jika search kosong', () => {
            renderPage();
            expect(
                screen.queryByLabelText('Hapus pencarian'),
            ).not.toBeInTheDocument();
        });
    });

    // ----------------------------------------------------------
    // 8.4 RENDERING — Booking Card
    // ----------------------------------------------------------
    describe('Rendering — Booking Card', () => {
        it('menampilkan data booking di card', () => {
            renderPage();

            expect(screen.getByText('BK-001')).toBeInTheDocument();
            expect(screen.getByText('Paket Bali')).toBeInTheDocument();
            expect(screen.getByText('2 orang')).toBeInTheDocument();
            expect(screen.getByText('Rp 1.500.000')).toBeInTheDocument();
        });

        it('menampilkan badge status', () => {
            renderPage();
            expect(screen.getByText('Menunggu Bayar')).toBeInTheDocument();
        });

        it('menampilkan fallback "Paket dihapus" saat package null', () => {
            renderPage(makeBookings([makeBooking({ package: null })]));
            expect(screen.getByText('Paket dihapus')).toBeInTheDocument();
        });

        it('menampilkan countdown jika status pending_payment & expired_at ada', () => {
            renderPage();
            expect(
                screen.getByText(/Sisa waktu: 1 jam 30 menit/i),
            ).toBeInTheDocument();
        });

        it('TIDAK menampilkan countdown jika status bukan pending_payment', () => {
            renderPage(makeBookings([makeBooking({ status: 'confirmed' })]));
            expect(screen.queryByText(/Sisa waktu/i)).not.toBeInTheDocument();
        });
    });

    // ----------------------------------------------------------
    // 8.5 RENDERING — Empty State
    // ----------------------------------------------------------
    describe('Rendering — Empty State', () => {
        it('menampilkan empty state default saat data kosong', () => {
            renderPage(makeBookings([]));

            expect(screen.getByText('Belum ada pesanan')).toBeInTheDocument();
            expect(
                screen.getByText(
                    'Mulai jelajahi paket wisata dan buat pesanan pertama Anda.',
                ),
            ).toBeInTheDocument();
            expect(
                screen.getByRole('link', { name: /Cari Paket Wisata/i }),
            ).toBeInTheDocument();
        });

        it('menampilkan empty state filter saat ada active filters', () => {
            renderPage(makeBookings([]), { ...DEFAULT_FILTERS, search: 'xyz' });

            expect(
                screen.getByText('Tidak ada pesanan ditemukan'),
            ).toBeInTheDocument();
            expect(
                screen.getByRole('button', { name: 'Hapus Filter' }),
            ).toBeInTheDocument();
        });
    });

    // ----------------------------------------------------------
    // 8.6 INTERACTION — Search Debounce
    // ----------------------------------------------------------
    describe('Interaction — Search Debounce', () => {
        it('tidak memanggil router.get sebelum 300ms', async () => {
            renderPage();

            const input = screen.getByPlaceholderText(
                'Cari kode pesanan atau nama paket…',
            );
            await fireEvent.input(input, { target: { value: 'bali' } });

            expect(mockRouterGet).not.toHaveBeenCalled();
        });

        it('memanggil router.get setelah 300ms dengan payload benar', async () => {
            renderPage();

            const input = screen.getByPlaceholderText(
                'Cari kode pesanan atau nama paket…',
            );
            await fireEvent.input(input, { target: { value: 'bali' } });

            await vi.advanceTimersByTimeAsync(300);

            expect(mockRouterGet).toHaveBeenCalledOnce();
            expect(mockRouterGet).toHaveBeenCalledWith(
                '/client/bookings',
                expect.objectContaining({ search: 'bali' }),
                { preserveState: true, preserveScroll: true, replace: true },
            );
        });
    });

    // ----------------------------------------------------------
    // 8.7 INTERACTION — Status Change
    // ----------------------------------------------------------
    describe('Interaction — Status Change', () => {
        it('klik tab status memanggil router.get dengan status', async () => {
            renderPage();

            await fireEvent.click(
                screen.getByRole('button', { name: /Menunggu 1/i }),
            );

            expect(mockRouterGet).toHaveBeenCalledWith(
                '/client/bookings',
                expect.objectContaining({ status: 'pending_payment' }),
                expect.any(Object),
            );
        });
    });

    // ----------------------------------------------------------
    // 8.8 INTERACTION — Clear Filters
    // ----------------------------------------------------------
    describe('Interaction — Clear Filters', () => {
        it('klik "Hapus pencarian" mereset search', async () => {
            renderPage(makeBookings([makeBooking()]), {
                ...DEFAULT_FILTERS,
                search: 'bali',
            });

            await fireEvent.click(screen.getByLabelText('Hapus pencarian'));

            // Memicu handleSearch('') → debounce 300ms
            await vi.advanceTimersByTimeAsync(300);

            expect(mockRouterGet).toHaveBeenCalledWith(
                '/client/bookings',
                expect.objectContaining({ search: '' }),
                expect.any(Object),
            );
        });

        it('klik "Hapus Filter" memanggil router.get dengan payload kosong', async () => {
            renderPage(makeBookings([]), { ...DEFAULT_FILTERS, search: 'xyz' });

            await fireEvent.click(
                screen.getByRole('button', { name: 'Hapus Filter' }),
            );

            expect(mockRouterGet).toHaveBeenCalledWith(
                '/client/bookings',
                {},
                { preserveState: true, preserveScroll: true, replace: true },
            );
        });
    });

    // ----------------------------------------------------------
    // 8.9 RENDERING — Pagination
    // ----------------------------------------------------------
    describe('Rendering — Pagination', () => {
        it('merender pagination saat data ada', () => {
            renderPage();
            expect(screen.getByTestId('pagination')).toHaveTextContent(
                'Total 1 pesanan',
            );
        });

        it('TIDAK merender pagination saat data kosong', () => {
            renderPage(makeBookings([]));
            expect(screen.queryByTestId('pagination')).not.toBeInTheDocument();
        });
    });
});
