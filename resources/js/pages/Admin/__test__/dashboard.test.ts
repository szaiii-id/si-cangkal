/**
 * ============================================================
 * Admin Dashboard — Component Test
 * ============================================================
 *
 * Path: resources/js/pages/Admin/__test__/Dashboard.test.ts
 * Import: import Dashboard from '../Dashboard.svelte';
 */

import { render, screen } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import Dashboard from '../Dashboard.svelte';

// ============================================================
// 1. MOCK — INERTIA (page.auth)
// ============================================================
const mockPage = vi.hoisted(() => ({
    props: {
        auth: {
            user: { id: 1, name: 'Super Admin', role: 'admin' },
        },
    },
}));

vi.mock('@inertiajs/svelte', () => ({
    page: mockPage,
    inertia: vi.fn(),
}));

// ============================================================
// 2. MOCK — MENUS
// ============================================================
vi.mock('@/utils/menus', () => ({
    adminMenu: [],
}));

// ============================================================
// 3. MOCK — AREA CHART
// ============================================================
vi.mock('@faintshadow/flarecharts', () => ({
    AreaChart: (anchor: Node, props: { series: any[] }) => {
        const el = document.createElement('div');
        el.setAttribute('data-testid', 'area-chart');
        el.setAttribute('data-series-count', String(props.series?.length ?? 0));
        el.setAttribute(
            'data-data-points',
            String(props.series?.[0]?.data?.length ?? 0),
        );
        anchor.parentNode?.insertBefore(el, anchor);
        return {};
    },
}));

// ============================================================
// 4. MOCK — DASHBOARD LAYOUT
// ============================================================
vi.mock('@/components/layout/DashboardLayout.svelte', () => ({
    default: (anchor: Node, props: { children?: (anchor: Node) => void }) => {
        props.children?.(anchor);
        return {};
    },
}));

// ============================================================
// 5. MOCK — STATS GRID
// ============================================================
vi.mock('@/components/admin/dashboard/StatsGrid.svelte', () => ({
    default: (anchor: Node, props: { stats: any }) => {
        const el = document.createElement('div');
        el.setAttribute('data-testid', 'stats-grid');
        el.setAttribute(
            'data-total-revenue',
            String(props.stats?.total_revenue ?? 0),
        );
        el.setAttribute(
            'data-total-users',
            String(
                (props.stats?.users?.admin ?? 0) +
                    (props.stats?.users?.mitra ?? 0) +
                    (props.stats?.users?.client ?? 0),
            ),
        );
        anchor.parentNode?.insertBefore(el, anchor);
        return {};
    },
}));

// ============================================================
// 6. MOCK — RECENT BOOKINGS
// ============================================================
vi.mock('@/components/admin/dashboard/RecentBookings.svelte', () => ({
    default: (anchor: Node, props: { bookings: any[] }) => {
        const el = document.createElement('div');
        el.setAttribute('data-testid', 'recent-bookings');
        el.setAttribute(
            'data-bookings-count',
            String(props.bookings?.length ?? 0),
        );
        anchor.parentNode?.insertBefore(el, anchor);
        return {};
    },
}));

// ============================================================
// 7. FIXTURES
// ============================================================
const makeStats = (overrides = {}) => ({
    users: { admin: 2, mitra: 5, client: 100 },
    packages: { total: 20, aktif: 15 },
    trips: { total: 50, aktif: 10 },
    bookings: { total: 200 },
    total_revenue: 50000000,
    ...overrides,
});

const makeBookings = (count = 3) =>
    Array.from({ length: count }, (_, i) => ({
        id: i + 1,
        kode: `BK-${String(i + 1).padStart(3, '0')}`,
        client: `Client ${i + 1}`,
        mitra: `Mitra ${i + 1}`,
        paket: `Paket ${i + 1}`,
        total: 1000000 * (i + 1),
        status: 'PAID',
        tanggal: '2026-01-15',
    }));

const makeMonthlyRevenue = (count = 6) =>
    Array.from({ length: count }, (_, i) => ({
        bulan: `2026-0${i + 1}`,
        total: 5000000 * (i + 1),
    }));

const renderPage = (overrides: Record<string, unknown> = {}) =>
    render(Dashboard, {
        props: {
            stats: makeStats(),
            recent_bookings: makeBookings(3),
            monthly_revenue: makeMonthlyRevenue(6),
            ...overrides,
        } as any,
    });

// ============================================================
// 8. TEST SUITE
// ============================================================
describe('Admin Dashboard', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        mockPage.props.auth = {
            user: { id: 1, name: 'Super Admin', role: 'admin' },
        };
    });

    // ----------------------------------------------------------
    // 8.1 RENDERING — Welcome Header
    // ----------------------------------------------------------
    describe('Rendering — Welcome', () => {
        it('menampilkan nama user dari page.auth', () => {
            renderPage();
            expect(screen.getByRole('heading', { level: 1 })).toHaveTextContent(
                'Halo, Super Admin',
            );
        });

        it('menampilkan "Admin" sebagai fallback jika user null', () => {
            mockPage.props.auth = { user: null } as any;
            renderPage();
            expect(screen.getByRole('heading', { level: 1 })).toHaveTextContent(
                'Halo, Admin',
            );
        });

        it('menampilkan deskripsi halaman', () => {
            renderPage();
            expect(
                screen.getByText(
                    'Ini ringkasan performa bisnis Anda hari ini.',
                ),
            ).toBeInTheDocument();
        });
    });

    // ----------------------------------------------------------
    // 8.2 RENDERING — Actions
    // ----------------------------------------------------------
    describe('Rendering — Actions', () => {
        it('menampilkan link "Lihat Laporan" ke /admin/reports', () => {
            renderPage();
            const link = screen.getByRole('link', { name: /Lihat Laporan/i });
            expect(link).toHaveAttribute('href', '/admin/reports');
        });

        it('menampilkan link "Detail Laporan" ke /admin/reports', () => {
            renderPage();
            const links = screen.getAllByRole('link', {
                name: /Detail Laporan/i,
            });
            expect(links.length).toBeGreaterThan(0);
            expect(links[0]).toHaveAttribute('href', '/admin/reports');
        });
    });

    // ----------------------------------------------------------
    // 8.3 RENDERING — StatsGrid
    // ----------------------------------------------------------
    describe('Rendering — StatsGrid', () => {
        it('meneruskan stats ke StatsGrid', () => {
            renderPage();

            const statsGrid = screen.getByTestId('stats-grid');
            expect(statsGrid).toHaveAttribute('data-total-revenue', '50000000');
            expect(statsGrid).toHaveAttribute('data-total-users', '107');
        });

        it('handle stats kosong (0 semua)', () => {
            renderPage({
                stats: {
                    users: { admin: 0, mitra: 0, client: 0 },
                    packages: { total: 0, aktif: 0 },
                    trips: { total: 0, aktif: 0 },
                    bookings: { total: 0 },
                    total_revenue: 0,
                },
            });

            const statsGrid = screen.getByTestId('stats-grid');
            expect(statsGrid).toHaveAttribute('data-total-revenue', '0');
            expect(statsGrid).toHaveAttribute('data-total-users', '0');
        });
    });

    // ----------------------------------------------------------
    // 8.4 RENDERING — Chart
    // ----------------------------------------------------------
    describe('Rendering — Revenue Chart', () => {
        it('menampilkan heading "Tren Pendapatan"', () => {
            renderPage();
            expect(
                screen.getByRole('heading', {
                    level: 3,
                    name: 'Tren Pendapatan',
                }),
            ).toBeInTheDocument();
        });

        it('meneruskan monthly_revenue ke AreaChart', () => {
            renderPage();

            const chart = screen.getByTestId('area-chart');
            expect(chart).toHaveAttribute('data-series-count', '1');
            expect(chart).toHaveAttribute('data-data-points', '6');
        });

        it('handle monthly_revenue kosong (0 data points)', () => {
            renderPage({ monthly_revenue: [] });

            const chart = screen.getByTestId('area-chart');
            expect(chart).toHaveAttribute('data-data-points', '0');
        });
    });

    // ----------------------------------------------------------
    // 8.5 RENDERING — RecentBookings
    // ----------------------------------------------------------
    describe('Rendering — RecentBookings', () => {
        it('meneruskan recent_bookings ke RecentBookings', () => {
            renderPage();

            expect(screen.getByTestId('recent-bookings')).toHaveAttribute(
                'data-bookings-count',
                '3',
            );
        });

        it('handle recent_bookings kosong', () => {
            renderPage({ recent_bookings: [] });

            expect(screen.getByTestId('recent-bookings')).toHaveAttribute(
                'data-bookings-count',
                '0',
            );
        });

        it('handle recent_bookings kosong saat tidak ada booking terbaru', () => {
            render(Dashboard, {
                props: {
                    stats: makeStats(),
                    recent_bookings: [],
                    monthly_revenue: [],
                } as any,
            });

            expect(screen.getByTestId('recent-bookings')).toHaveAttribute(
                'data-bookings-count',
                '0',
            );
        });
    });

    // ----------------------------------------------------------
    // 8.6 REACTIVITY
    // ----------------------------------------------------------
    describe('Reactivity', () => {
        it('chart data point count mengikuti length monthly_revenue', () => {
            renderPage({ monthly_revenue: makeMonthlyRevenue(3) });

            expect(screen.getByTestId('area-chart')).toHaveAttribute(
                'data-data-points',
                '3',
            );
        });

        it('chart data point count 12 (tahun penuh)', () => {
            renderPage({ monthly_revenue: makeMonthlyRevenue(12) });

            expect(screen.getByTestId('area-chart')).toHaveAttribute(
                'data-data-points',
                '12',
            );
        });
    });

    // ----------------------------------------------------------
    // 8.7 EDGE CASE
    // ----------------------------------------------------------
    describe('Edge Cases', () => {
        it('handle props kosong (empty arrays)', () => {
            render(Dashboard, {
                props: {
                    stats: makeStats(),
                    recent_bookings: [],
                    monthly_revenue: [],
                } as any,
            });

            expect(screen.getByTestId('recent-bookings')).toHaveAttribute(
                'data-bookings-count',
                '0',
            );
            expect(screen.getByTestId('area-chart')).toHaveAttribute(
                'data-data-points',
                '0',
            );
        });

        it('handle user tanpa nama (fallback ke Admin)', () => {
            mockPage.props.auth = {
                user: { id: 1, name: '', role: 'admin' },
            } as any;
            renderPage();

            expect(screen.getByRole('heading', { level: 1 })).toHaveTextContent(
                'Halo,',
            );
        });
    });
});
