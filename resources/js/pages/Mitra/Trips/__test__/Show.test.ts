/**
 * ============================================================
 * Mitra Trips — Show Page — Component Test
 * ============================================================
 */

import { render, screen } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import Show from '../Show.svelte';

// ============================================================
// 1. HOISTED MOCK REGISTRY
// ============================================================
const mockApi = vi.hoisted(() => ({
    getTripManifestUrl: vi.fn((id: number) => `https://manifest.test/trip/${id}`),
}));

// ============================================================
// 2. MOCK DEPENDENCIES
// ============================================================
vi.mock('@/api/mitra', () => mockApi);

vi.mock('@/utils/enums', () => ({
    tripStatusBadge: vi.fn((s: string) => `badge-${s}`),
    tripStatusLabel: vi.fn((s: string) => s),
}));

vi.mock('@/utils/menus', () => ({
    mitraMenu: [],
}));

vi.mock('@inertiajs/svelte', () => ({
    inertia: vi.fn(),
}));

// ============================================================
// 3. MOCK CHILD COMPONENTS
// ============================================================
vi.mock('@/components/layout/DashboardLayout.svelte', () => ({
    default: (anchor: Node, props: any) => {
        props.children?.(anchor);
        return {};
    },
}));

vi.mock('@/global/BackButton.svelte', () => ({
    default: (anchor: Node, props: any) => {
        const el = document.createElement('div');
        el.setAttribute('data-testid', 'back-button');
        el.textContent = props.label;
        anchor.parentNode?.insertBefore(el, anchor);
        return {};
    },
}));

vi.mock('@/components/mitra/trips/TripHeaderCard.svelte', () => ({
    default: (anchor: Node, props: any) => {
        const el = document.createElement('div');
        el.setAttribute('data-testid', 'trip-header-card');
        el.setAttribute('data-kode', props.kode);
        el.setAttribute('data-status', props.status);
        el.setAttribute('data-paket', props.namaPaket);
        el.setAttribute('data-manifest', props.manifestUrl);
        el.setAttribute('data-has-client', String(!!props.client));
        anchor.parentNode?.insertBefore(el, anchor);
        return {};
    },
}));

vi.mock('@/components/mitra/trips/TripItineraryList.svelte', () => ({
    default: (anchor: Node, props: any) => {
        const el = document.createElement('div');
        el.setAttribute('data-testid', 'trip-itinerary');
        el.setAttribute('data-days-count', String(props.days?.length ?? 0));
        anchor.parentNode?.insertBefore(el, anchor);
        return {};
    },
}));

vi.mock('@/components/mitra/trips/TripFacilitiesCard.svelte', () => ({
    default: (anchor: Node, props: any) => {
        const el = document.createElement('div');
        el.setAttribute('data-testid', 'trip-facilities');
        el.setAttribute('data-include-count', String(props.include?.length ?? 0));
        el.setAttribute('data-exclude-count', String(props.exclude?.length ?? 0));
        anchor.parentNode?.insertBefore(el, anchor);
        return {};
    },
}));

// ============================================================
// 4. FIXTURES
// ============================================================
const makeTrip = (overrides = {}) => ({
    id: 42,
    mitra_id: 1,
    package_id: 1,
    booking_id: 10,
    kode: 'TRIP-001',
    status: 'scheduled' as const,
    tanggal_mulai: '2026-02-01',
    tanggal_selesai: '2026-02-03',
    jumlah_peserta: 2,
    timezone: 'Asia/Jakarta',
    created_at: '2026-01-15T00:00:00Z',
    updated_at: '2026-01-15T00:00:00Z',
    snapshot_package: {
        nama: 'Paket Bali (Snapshot)',
        facilities: {
            include: ['Hotel', 'Transport'],
            exclude: ['Tiket Pesawat'],
        },
    },
    package: {
        id: 1,
        nama: 'Paket Bali',
        kode: 'PKT-001',
        slug: 'paket-bali',
        timezone: 'Asia/Jakarta',
        cover_image: null,
    },
    booking: {
        id: 10,
        kode: 'BK-001',
        client: { id: 100, name: 'Budi', email: 'budi@test.com' },
    },
    mitra: { id: 1, name: 'PT Mitra Jaya', email: 'mitra@test.com', username: 'mitrajaya' },
    days: [
        { id: 1, trip_id: 42, hari_ke: 1, judul: 'Hari 1', deskripsi: 'Hari pertama', tanggal: '2026-02-01', status: 'pending' as const, created_at: '2026-01-15T00:00:00Z', updated_at: '2026-01-15T00:00:00Z' },
        { id: 2, trip_id: 42, hari_ke: 2, judul: 'Hari 2', deskripsi: 'Hari kedua', tanggal: '2026-02-02', status: 'pending' as const, created_at: '2026-01-15T00:00:00Z', updated_at: '2026-01-15T00:00:00Z' },
    ],
    ...overrides,
});

const renderPage = (trip = makeTrip()) => render(Show, { props: { trip } });

// ============================================================
// 5. TEST SUITE
// ============================================================
describe('Mitra Trips — Show Page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    describe('Rendering', () => {
        it('menampilkan BackButton', () => {
            renderPage();
            expect(screen.getByTestId('back-button')).toHaveTextContent('Kembali ke Jadwal Perjalanan');
        });

        it('merender TripHeaderCard dengan data yang benar', () => {
            renderPage();
            const header = screen.getByTestId('trip-header-card');
            expect(header).toHaveAttribute('data-kode', 'TRIP-001');
            expect(header).toHaveAttribute('data-status', 'scheduled');
            expect(header).toHaveAttribute('data-paket', 'Paket Bali (Snapshot)');
            expect(header).toHaveAttribute('data-has-client', 'true');
        });

        it('memanggil getTripManifestUrl dengan trip.id', () => {
            renderPage();
            expect(mockApi.getTripManifestUrl).toHaveBeenCalledWith(42);
        });

        it('merender TripItineraryList dengan days', () => {
            renderPage();
            expect(screen.getByTestId('trip-itinerary')).toHaveAttribute('data-days-count', '2');
        });

        it('merender TripFacilitiesCard dengan include & exclude dari snapshot', () => {
            renderPage();
            const facilities = screen.getByTestId('trip-facilities');
            expect(facilities).toHaveAttribute('data-include-count', '2');
            expect(facilities).toHaveAttribute('data-exclude-count', '1');
        });

        it('menampilkan mitra (Penyedia Perjalanan) jika ada', () => {
            renderPage();
            expect(screen.getByText('Penyedia Perjalanan')).toBeInTheDocument();
            expect(screen.getByText('PT Mitra Jaya')).toBeInTheDocument();
            expect(screen.getByText('mitra@test.com')).toBeInTheDocument();
        });
    });

    describe('Edge Cases', () => {
        it('handle snapshot_package null (fallback ke trip.package)', () => {
            renderPage(makeTrip({ snapshot_package: null }));
            const header = screen.getByTestId('trip-header-card');
            expect(header).toHaveAttribute('data-paket', 'Paket Bali');
        });

        it('handle facilities snapshot kosong', () => {
            renderPage(makeTrip({
                snapshot_package: { nama: 'Test', facilities: null },
            }));
            const facilities = screen.getByTestId('trip-facilities');
            expect(facilities).toHaveAttribute('data-include-count', '0');
            expect(facilities).toHaveAttribute('data-exclude-count', '0');
        });

        it('handle client null di booking', () => {
            renderPage(makeTrip({
                booking: { id: 10, kode: 'BK-001', client: null },
            }));
            expect(screen.getByTestId('trip-header-card')).toHaveAttribute('data-has-client', 'false');
        });

        it('TIDAK menampilkan mitra jika trip.mitra null', () => {
            renderPage(makeTrip({ mitra: null }));
            expect(screen.queryByText('Penyedia Perjalanan')).not.toBeInTheDocument();
        });

        it('handle days kosong', () => {
            renderPage(makeTrip({ days: [] }));
            expect(screen.getByTestId('trip-itinerary')).toHaveAttribute('data-days-count', '0');
        });
    });
});