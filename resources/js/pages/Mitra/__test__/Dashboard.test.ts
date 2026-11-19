/**
 * ============================================================
 * Mitra Dashboard — Component Test
 * ============================================================
 *
 * Fokus pengujian:
 *   1. Isolation & Mocking  → Inertia, menu, & child components
 *                             (DashboardLayout, StatsCards,
 *                             RecentBookingsList) di-mock.
 *   2. Props & Rendering    → Welcome header, passing stats & bookings.
 *   3. Edge Cases           → Fallback user name, default stats kosong.
 * ============================================================
 */

import { render, screen } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import Dashboard from '../Dashboard.svelte';

// ============================================================
// 1. HOISTED MOCK REGISTRY
// ============================================================
const mockPage = vi.hoisted(() => ({
    props: {
        auth: {
            user: { id: 1, name: 'Mitra Jaya', role: 'mitra' },
        },
    },
}));

// ============================================================
// 2. MOCK — INERTIA
// ============================================================
vi.mock('@inertiajs/svelte', () => ({
    page: mockPage,
    inertia: vi.fn(),
}));

// ============================================================
// 3. MOCK — MENUS
// ============================================================
vi.mock('@/utils/menus', () => ({
    mitraMenu: [],
}));

// ============================================================
// 4. MOCK — LAYOUT & PAGE HEADER (jika ada)
// ============================================================
vi.mock('@/components/layout/DashboardLayout.svelte', () => ({
    default: (anchor: Node, props: { children?: (anchor: Node) => void }) => {
        props.children?.(anchor);
        return {};
    },
}));

// ============================================================
// 5. MOCK — CHILD COMPONENTS
// ============================================================
vi.mock('@/components/mitra/dashboard/StatsCards.svelte', () => ({
    default: (anchor: Node, props: any) => {
        const el = document.createElement('div');
        el.setAttribute('data-testid', 'stats-cards');
        el.setAttribute('data-revenue', String(props.totalRevenue));
        el.setAttribute('data-packages', String(props.totalPackages));
        el.setAttribute('data-trips', String(props.totalTrips));
        el.setAttribute('data-bookings', String(props.totalBookings));
        anchor.parentNode?.insertBefore(el, anchor);
        return {};
    },
}));

vi.mock('@/components/mitra/dashboard/RecentBookingsList.svelte', () => ({
    default: (anchor: Node, props: any) => {
        const el = document.createElement('div');
        el.setAttribute('data-testid', 'recent-bookings');
        el.setAttribute('data-bookings-count', String(props.bookings?.length ?? 0));
        anchor.parentNode?.insertBefore(el, anchor);
        return {};
    },
}));

// ============================================================
// 6. FIXTURES
// ============================================================
const makeStats = (overrides = {}) => ({
    packages: { draft: 2, aktif: 5, arsip: 1, total: 8 },
    trips: { scheduled: 3, active: 1, completed: 10, total: 14 },
    bookings: { pending: 2, confirmed: 5, total: 7 },
    total_revenue: 12500000,
    ...overrides,
});

const makeBookings = (count = 3) =>
    Array.from({ length: count }, (_, i) => ({
        id: i + 1,
        kode: `BK-${i + 1}`,
        client: `Client ${i + 1}`,
        paket: `Paket ${i + 1}`,
        total: 1000000 * (i + 1),
        status: 'pending',
        tanggal: '2026-01-01',
    }));

const renderPage = (props: any = {}) =>
    render(Dashboard, {
        props: {
            stats: makeStats(),
            recent_bookings: makeBookings(3),
            ...props,
        },
    });

// ============================================================
// 7. TEST SUITE
// ============================================================
describe('Mitra Dashboard', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        mockPage.props.auth = {
            user: { id: 1, name: 'Mitra Jaya', role: 'mitra' },
        };
    });

    // ----------------------------------------------------------
    // 7.1 RENDERING — Header
    // ----------------------------------------------------------
    describe('Rendering — Header', () => {
        it('menampilkan nama user dari page.auth', () => {
            renderPage();
            expect(screen.getByRole('heading', { level: 1 })).toHaveTextContent(
                'Halo, Mitra Jaya',
            );
        });

        it('menampilkan fallback "Mitra" jika user null', () => {
            mockPage.props.auth = { user: null } as any;
            renderPage();
            expect(screen.getByRole('heading', { level: 1 })).toHaveTextContent(
                'Halo, Mitra',
            );
        });

        it('menampilkan deskripsi halaman', () => {
            renderPage();
            expect(
                screen.getByText('Ini ringkasan performa bisnis wisata Anda.'),
            ).toBeInTheDocument();
        });
    });

    // ----------------------------------------------------------
    // 7.2 RENDERING — Child Components
    // ----------------------------------------------------------
    describe('Rendering — Child Components', () => {
        it('merender StatsCards', () => {
            renderPage();
            expect(screen.getByTestId('stats-cards')).toBeInTheDocument();
        });

        it('meneruskan stats ke StatsCards dengan benar', () => {
            renderPage();
            const cards = screen.getByTestId('stats-cards');

            expect(cards).toHaveAttribute('data-revenue', '12500000');
            expect(cards).toHaveAttribute('data-packages', '8');   // stats.packages.total
            expect(cards).toHaveAttribute('data-trips', '14');     // stats.trips.total
            expect(cards).toHaveAttribute('data-bookings', '7');   // stats.bookings.total
        });

        it('merender RecentBookingsList', () => {
            renderPage();
            expect(screen.getByTestId('recent-bookings')).toBeInTheDocument();
        });

        it('meneruskan recent_bookings ke RecentBookingsList', () => {
            renderPage({ recent_bookings: makeBookings(5) });
            expect(screen.getByTestId('recent-bookings')).toHaveAttribute(
                'data-bookings-count',
                '5',
            );
        });
    });

    // ----------------------------------------------------------
    // 7.3 EDGE CASES
    // ----------------------------------------------------------
    describe('Edge Cases', () => {
        it('menggunakan default stats jika props kosong', () => {
            render(Dashboard, { props: {} }); // tanpa stats & recent_bookings

            const cards = screen.getByTestId('stats-cards');
            expect(cards).toHaveAttribute('data-revenue', '0');
            expect(cards).toHaveAttribute('data-packages', '0');
            expect(cards).toHaveAttribute('data-trips', '0');
            expect(cards).toHaveAttribute('data-bookings', '0');

            const bookings = screen.getByTestId('recent-bookings');
            expect(bookings).toHaveAttribute('data-bookings-count', '0');
        });

        it('handle recent_bookings kosong', () => {
            renderPage({ recent_bookings: [] });

            expect(screen.getByTestId('recent-bookings')).toHaveAttribute(
                'data-bookings-count',
                '0',
            );
        });
    });
});