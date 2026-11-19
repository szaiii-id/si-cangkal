/**
 * ============================================================
 * Admin Trips — Show Page (Detail Perjalanan) — Component Test
 * ============================================================
 *
 * Fokus pengujian:
 *   1. Isolation & Mocking  → Inertia, menu, formatCurrency,
 *                             & child components (TripHeader,
 *                             TripMitraCard, TripItinerary,
 *                             BackButton, DashboardLayout) di-mock
 *                             anchor-aware.
 *   2. Props & Rendering    → header, itinerary, mitra card,
 *                             info pesanan (conditional).
 *   3. Interaction          → link ke detail booking.
 *   4. Reactivity           → snapshot_package fallback.
 *
 * Strategi:
 *   - Anchor-aware mock via `insertBefore(anchor.parentNode)`.
 *   - A11y-first query: `getByRole('heading')`, `getByRole('link')`.
 * ============================================================
 */

import { render, screen } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import Show from '../Show.svelte';

// ============================================================
// 1. HOISTED MOCK REGISTRY
// ============================================================
const mockUpdaters = vi.hoisted(() => new Set<() => void>());

// ============================================================
// 2. MOCK — INERTIA
// ============================================================
vi.mock('@inertiajs/svelte', () => ({
    inertia: vi.fn(),
}));

// ============================================================
// 3. MOCK — MENUS
// ============================================================
vi.mock('@/utils/menus', () => ({
    adminMenu: [],
}));

// ============================================================
// 4. MOCK — FORMAT CURRENCY (deterministik)
// ============================================================
vi.mock('@/utils/formatCurrency', () => ({
    formatCurrency: vi.fn(
        (v: string | number) => `Rp ${Number(v).toLocaleString('id-ID')}`,
    ),
}));

// ============================================================
// 5. MOCK — DASHBOARD LAYOUT
// ============================================================
vi.mock('@/components/layout/DashboardLayout.svelte', () => ({
    default: (anchor: Node, props: { children?: (anchor: Node) => void }) => {
        props.children?.(anchor);
        return {};
    },
}));

// ============================================================
// 6. MOCK — BACK BUTTON
// ============================================================
vi.mock('@/global/BackButton.svelte', () => ({
    default: (anchor: Node, props: { href: string; label?: string }) => {
        const link = document.createElement('a');
        link.href = props.href;
        link.textContent = props.label ?? 'Kembali';
        link.setAttribute('data-testid', 'back-button');
        anchor.parentNode?.insertBefore(link, anchor);
        return {};
    },
}));

// ============================================================
// 7. MOCK — TRIP HEADER (render info minimal)
// ============================================================
vi.mock('@/components/admin/trips/TripHeader.svelte', () => ({
    default: (
        anchor: Node,
        props: { trip: { kode: string; status: string }; snapshot?: { nama?: string } },
    ) => {
        const el = document.createElement('div');
        el.setAttribute('data-testid', 'trip-header');
        el.setAttribute('data-trip-kode', props.trip.kode);
        el.setAttribute('data-trip-status', props.trip.status);
        el.setAttribute(
            'data-snapshot-nama',
            props.snapshot?.nama ?? '(empty)',
        );
        anchor.parentNode?.insertBefore(el, anchor);
        return {};
    },
}));

// ============================================================
// 8. MOCK — TRIP MITRA CARD (render kondisional)
// ============================================================
vi.mock('@/components/admin/trips/TripMitraCard.svelte', () => ({
    default: (
        anchor: Node,
        props: {
            mitra:
                | { id: number; name: string; email: string }
                | null
                | undefined;
        },
    ) => {
        const el = document.createElement('div');
        el.setAttribute('data-testid', 'trip-mitra-card');
        el.setAttribute(
            'data-has-mitra',
            props.mitra ? 'true' : 'false',
        );
        if (props.mitra) {
            el.textContent = `Mitra: ${props.mitra.name}`;
        } else {
            el.textContent = 'Data mitra tidak ditemukan';
        }
        anchor.parentNode?.insertBefore(el, anchor);
        return {};
    },
}));

// ============================================================
// 9. MOCK — TRIP ITINERARY (render jumlah days)
// ============================================================
vi.mock('@/components/admin/trips/TripItinerary.svelte', () => ({
    default: (
        anchor: Node,
        props: { days: Array<{ id: number; hari_ke: number; judul: string }> },
    ) => {
        const el = document.createElement('div');
        el.setAttribute('data-testid', 'trip-itinerary');
        el.setAttribute('data-days-count', String(props.days?.length ?? 0));
        el.textContent = `${props.days?.length ?? 0} hari`;
        anchor.parentNode?.insertBefore(el, anchor);
        return {};
    },
}));

// ============================================================
// 10. STATIC IMPORT
// ============================================================
import { formatCurrency } from '@/utils/formatCurrency';

// ============================================================
// 11. FIXTURES
// ============================================================
const makeTrip = (overrides: Record<string, unknown> = {}) =>
    ({
        id: 1,
        mitra_id: 200,
        package_id: 1,
        booking_id: 10,
        kode: 'TRIP-001',
        status: 'scheduled',
        tanggal_mulai: '2026-02-01',
        tanggal_selesai: '2026-02-03',
        jumlah_peserta: 2,
        timezone: 'Asia/Jakarta',
        created_at: '2026-01-01T00:00:00Z',
        updated_at: '2026-01-01T00:00:00Z',
        snapshot_package: { nama: 'Paket Bali (Snapshot)' },
        package: {
            id: 1,
            nama: 'Paket Bali 3H2M',
            kode: 'PKT-BALI-001',
            slug: 'paket-bali',
            timezone: 'Asia/Jakarta',
            cover_image: null,
        },
        booking: {
            id: 10,
            kode: 'BK-2026-001',
            tanggal_keberangkatan: '2026-02-01',
            jumlah_peserta: 2,
            total_harga: '1500000',
            status: 'confirmed',
            client: {
                id: 100,
                name: 'Budi Santoso',
                email: 'budi@test.com',
                username: 'budisan',
            },
        },
        mitra: {
            id: 200,
            name: 'PT Mitra Jaya',
            email: 'mitra@test.com',
            username: 'mitrajaya',
            timezone: 'Asia/Jakarta',
        },
        days: [
            { id: 1, hari_ke: 1, judul: 'Kedatangan', deskripsi: 'Arrive di Bali', tanggal: '2026-02-01', status: 'pending' },
            { id: 2, hari_ke: 2, judul: 'Explore Ubud', deskripsi: 'Tour', tanggal: '2026-02-02', status: 'pending' },
            { id: 3, hari_ke: 3, judul: 'Kembali', deskripsi: 'Check out', tanggal: '2026-02-03', status: 'pending' },
        ],
        ...overrides,
    }) as any;

const renderPage = (trip = makeTrip()) => render(Show, { props: { trip } });

// ============================================================
// 12. TEST SUITE
// ============================================================
describe('Admin Trips — Show Page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        mockUpdaters.clear();
    });

    // ----------------------------------------------------------
    // 12.1 RENDERING — Header & Back Button
    // ----------------------------------------------------------
    describe('Rendering — Header', () => {
        it('menampilkan BackButton dengan href benar', () => {
            renderPage();

            const backBtn = screen.getByTestId('back-button');
            expect(backBtn).toHaveAttribute('href', '/admin/trips');
            expect(backBtn).toHaveTextContent('Kembali ke Jadwal Perjalanan');
        });

        it('merender TripHeader dengan trip & snapshot', () => {
            renderPage();

            const header = screen.getByTestId('trip-header');
            expect(header).toHaveAttribute('data-trip-kode', 'TRIP-001');
            expect(header).toHaveAttribute('data-trip-status', 'scheduled');
            expect(header).toHaveAttribute(
                'data-snapshot-nama',
                'Paket Bali (Snapshot)',
            );
        });
    });

    // ----------------------------------------------------------
    // 12.2 RENDERING — Itinerary
    // ----------------------------------------------------------
    describe('Rendering — TripItinerary', () => {
        it('merender TripItinerary dengan jumlah days benar', () => {
            renderPage();

            expect(screen.getByTestId('trip-itinerary')).toHaveAttribute(
                'data-days-count',
                '3',
            );
        });

        it('render TripItinerary dengan days kosong', () => {
            renderPage(makeTrip({ days: [] }));

            expect(screen.getByTestId('trip-itinerary')).toHaveAttribute(
                'data-days-count',
                '0',
            );
        });
    });

    // ----------------------------------------------------------
    // 12.3 RENDERING — Mitra Card
    // ----------------------------------------------------------
    describe('Rendering — TripMitraCard', () => {
        it('merender TripMitraCard dengan data mitra', () => {
            renderPage();

            const card = screen.getByTestId('trip-mitra-card');
            expect(card).toHaveAttribute('data-has-mitra', 'true');
            expect(card).toHaveTextContent('PT Mitra Jaya');
        });

        it('merender placeholder saat mitra null', () => {
            renderPage(makeTrip({ mitra: null }));

            const card = screen.getByTestId('trip-mitra-card');
            expect(card).toHaveAttribute('data-has-mitra', 'false');
            expect(card).toHaveTextContent('Data mitra tidak ditemukan');
        });
    });

    // ----------------------------------------------------------
    // 12.4 RENDERING — Info Pesanan (conditional)
    // ----------------------------------------------------------
    describe('Rendering — Info Pesanan', () => {
        it('merender section Info Pesanan saat booking ada', () => {
            renderPage();

            expect(screen.getByText('Info Pesanan')).toBeInTheDocument();
            expect(screen.getByText('BK-2026-001')).toBeInTheDocument();
            expect(screen.getByText('Budi Santoso')).toBeInTheDocument();
        });

        it('memanggil formatCurrency dengan total_harga benar', () => {
            renderPage();

            expect(formatCurrency).toHaveBeenCalledWith('1500000');
        });

        it('menampilkan link "Lihat Detail Pesanan" saat booking ada', () => {
            renderPage();

            const link = screen.getByRole('link', {
                name: 'Lihat Detail Pesanan',
            });
            expect(link).toHaveAttribute('href', '/admin/bookings/10');
        });

        it('TIDAK merender Info Pesanan saat booking null', () => {
            renderPage(makeTrip({ booking: null }));

            expect(screen.queryByText('Info Pesanan')).not.toBeInTheDocument();
            expect(
                screen.queryByRole('link', { name: 'Lihat Detail Pesanan' }),
            ).not.toBeInTheDocument();
        });

        it('menampilkan "-" saat client null dalam booking', () => {
            renderPage(
                makeTrip({
                    booking: { ...makeTrip().booking, client: null },
                }),
            );

            expect(screen.getByText('Pemesan')).toBeInTheDocument();
            expect(screen.getAllByText('-')[0]).toBeInTheDocument();
        });
    });

    // ----------------------------------------------------------
    // 12.5 REACTIVITY — snapshot fallback
    // ----------------------------------------------------------
    describe('Reactivity — Snapshot Fallback', () => {
        it('meneruskan snapshot kosong saat snapshot_package undefined', () => {
            const trip = makeTrip();
            delete (trip as any).snapshot_package;

            renderPage(trip);

            expect(screen.getByTestId('trip-header')).toHaveAttribute(
                'data-snapshot-nama',
                '(empty)',
            );
        });

        it('meneruskan snapshot lengkap saat snapshot_package terisi', () => {
            renderPage(
                makeTrip({
                    snapshot_package: { nama: 'Snapshot Custom' },
                }),
            );

            expect(screen.getByTestId('trip-header')).toHaveAttribute(
                'data-snapshot-nama',
                'Snapshot Custom',
            );
        });
    });

    // ----------------------------------------------------------
    // 12.6 A11Y
    // ----------------------------------------------------------
    describe('Accessibility', () => {
        it('heading "Info Pesanan" pakai h3', () => {
            renderPage();

            expect(
                screen.getByRole('heading', { level: 3, name: 'Info Pesanan' }),
            ).toBeInTheDocument();
        });

        it('link "Lihat Detail Pesanan" punya href valid', () => {
            renderPage();

            const link = screen.getByRole('link', {
                name: 'Lihat Detail Pesanan',
            });
            expect(link.getAttribute('href')).toMatch(/^\/admin\/bookings\/\d+$/);
        });
    });

    // ----------------------------------------------------------
    // 12.7 EDGE CASE
    // ----------------------------------------------------------
    describe('Edge Cases', () => {
        it('handle trip dengan semua relasi null', () => {
            renderPage(
                makeTrip({
                    package: null,
                    booking: null,
                    mitra: null,
                    days: [],
                }),
            );

            expect(screen.getByTestId('trip-header')).toBeInTheDocument();
            expect(screen.getByTestId('trip-mitra-card')).toHaveAttribute(
                'data-has-mitra',
                'false',
            );
            expect(screen.getByTestId('trip-itinerary')).toHaveAttribute(
                'data-days-count',
                '0',
            );
            expect(screen.queryByText('Info Pesanan')).not.toBeInTheDocument();
        });

        it('handle total_harga string yang besar', () => {
            renderPage(
                makeTrip({
                    booking: {
                        ...makeTrip().booking,
                        total_harga: '999999999',
                    },
                }),
            );

            expect(formatCurrency).toHaveBeenCalledWith('999999999');
        });
    });
});