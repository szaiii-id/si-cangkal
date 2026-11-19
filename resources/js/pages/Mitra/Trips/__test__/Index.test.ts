/**
 * ============================================================
 * Mitra Trips — Index Page — Component Test
 * ============================================================
 */

import { render, screen, fireEvent } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { tick } from 'svelte';
import Index from '../Index.svelte';

// ============================================================
// 1. HOISTED MOCK REGISTRY
// ============================================================
const mockUpdaters = vi.hoisted(() => new Set<() => void>());
const mockApi = vi.hoisted(() => ({
    getTrips: vi.fn(),
}));

// ============================================================
// 2. MOCK DEPENDENCIES
// ============================================================
vi.mock('@/api/mitra', () => mockApi);

vi.mock('@/utils/enums', () => ({
    tripStatusBadge: vi.fn((s: string) => `badge-${s}`),
    tripStatusLabel: vi.fn((s: string) => s),
    tripStatusShort: vi.fn((s: string) => s),
}));

vi.mock('@/utils/formatDate', () => ({
    formatDate: vi.fn((d: string) => `Date: ${d}`),
}));

vi.mock('@/utils/duration', () => ({
    formatDurasiFromRange: vi.fn(() => '3H 2M'),
}));

vi.mock('@/utils/timezone', () => ({
    isDateInRange: vi.fn(() => true), // default true untuk "Hari ini"
    todayInTimezone: vi.fn(() => '2026-01-01'),
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

vi.mock('@/global/PageHeader.svelte', () => ({
    default: (anchor: Node, props: any) => {
        const el = document.createElement('div');
        el.setAttribute('data-testid', 'page-header');
        el.innerHTML = `<h1>${props.title}</h1>`;
        anchor.parentNode?.insertBefore(el, anchor);
        return {};
    },
}));

vi.mock('@/global/StatusTabs.svelte', () => ({
    default: (anchor: Node, props: any) => {
        const el = document.createElement('div');
        el.setAttribute('data-testid', 'status-tabs');
        anchor.parentNode?.insertBefore(el, anchor);

        const update = () => {
            el.innerHTML = props.tabs
                .map(
                    (t: any) =>
                        `<button type="button" data-tab="${t.value}">${t.label} (${t.count})</button>`,
                )
                .join('');

            el.querySelectorAll('button').forEach((btn) => {
                btn.onclick = () =>
                    props.onchange?.(btn.getAttribute('data-tab')!);
            });
        };

        update();
        mockUpdaters.add(update);
        return {};
    },
}));

vi.mock('@/global/SearchInput.svelte', () => ({
    default: (anchor: Node, props: any) => {
        const el = document.createElement('div');
        el.setAttribute('data-testid', 'search-input');
        anchor.parentNode?.insertBefore(el, anchor);

        const update = () => {
            el.innerHTML = `
                <input type="text" aria-label="Search" placeholder="${props.placeholder || ''}" value="${props.value || ''}" />
            `;
            (el.querySelector('input') as HTMLInputElement).oninput = (e) =>
                props.oninput?.((e.target as HTMLInputElement).value);
        };

        update();
        mockUpdaters.add(update);
        return {};
    },
}));

vi.mock('@/global/EmptyState.svelte', () => ({
    default: (anchor: Node, props: any) => {
        const el = document.createElement('div');
        el.setAttribute('data-testid', 'empty-state');
        el.textContent = props.title;
        anchor.parentNode?.insertBefore(el, anchor);
        return {};
    },
}));

vi.mock('@/global/Pagination.svelte', () => ({
    default: (anchor: Node) => {
        const el = document.createElement('div');
        el.setAttribute('data-testid', 'pagination');
        anchor.parentNode?.insertBefore(el, anchor);
        return {};
    },
}));

// ============================================================
// 4. HELPERS
// ============================================================
async function syncUI() {
    await tick();
    mockUpdaters.forEach((fn) => fn());
}

// ============================================================
// 5. FIXTURES
// ============================================================
const makeTrip = (overrides = {}) => ({
    id: 1,
    kode: 'TRIP-01',
    status: 'active',
    tanggal_mulai: '2026-01-01',
    tanggal_selesai: '2026-01-03',
    jumlah_peserta: 2,
    timezone: 'Asia/Jakarta',
    package: { id: 1, nama: 'Paket Bali', kode: 'PKT-01' },
    ...overrides,
});

const makeProps = (overrides = {}) => ({
    trips: {
        data: [makeTrip()],
        current_page: 1,
        last_page: 1,
        total: 1,
        per_page: 15,
        from: 1,
        to: 1,
        links: [],
    },
    filters: { search: '', status: '', sort: 'tanggal_mulai', direction: 'desc' },
    statusCounts: { scheduled: 0, active: 1, completed: 0, cancelled: 0 },
    ...overrides,
});

// ============================================================
// 6. TEST SUITE
// ============================================================
describe('Mitra Trips — Index Page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        mockUpdaters.clear();
        vi.useFakeTimers();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    describe('Rendering', () => {
        it('merender PageHeader dan toolbar', () => {
            render(Index, { props: makeProps() });
            expect(screen.getByTestId('page-header')).toHaveTextContent('Jadwal Perjalanan');
            expect(screen.getByTestId('status-tabs')).toBeInTheDocument();
            expect(screen.getByTestId('search-input')).toBeInTheDocument();
        });

        it('merender EmptyState jika data kosong', () => {
            render(Index, {
                props: makeProps({
                    trips: { data: [], current_page: 1, last_page: 1, total: 0, per_page: 15, from: 0, to: 0, links: [] },
                }),
            });
            expect(screen.getByTestId('empty-state')).toBeInTheDocument();
        });

        it('merender data trip dan Pagination jika ada', () => {
            render(Index, { props: makeProps() });
            expect(screen.getByText('TRIP-01')).toBeInTheDocument();
            expect(screen.getByText('Paket Bali')).toBeInTheDocument();
            expect(screen.getByText('2 peserta')).toBeInTheDocument();
            expect(screen.getByTestId('pagination')).toBeInTheDocument();
        });

        it('merender link detail trip dengan href yang benar', () => {
            render(Index, { props: makeProps() });
            const link = screen.getByText('TRIP-01').closest('a');
            expect(link).toHaveAttribute('href', '/mitra/trips/1');
        });

        it('merender badge "Hari ini" jika trip active & today', () => {
            render(Index, {
                props: makeProps({ statusCounts: { scheduled: 0, active: 1, completed: 0, cancelled: 0 } }),
            });
            expect(screen.getByText('Hari ini')).toBeInTheDocument();
        });
    });

    describe('Interaction — Filter & Search', () => {
        it('memanggil getTrips saat search debounce selesai', async () => {
            render(Index, { props: makeProps() });
            const input = screen.getByLabelText('Search');

            await fireEvent.input(input, { target: { value: 'bali' } });
            expect(mockApi.getTrips).not.toHaveBeenCalled();

            await vi.advanceTimersByTimeAsync(300);
            expect(mockApi.getTrips).toHaveBeenCalledWith(
                expect.objectContaining({ search: 'bali' }),
                expect.any(Object),
            );
        });

        it('memanggil getTrips saat status tab berubah', async () => {
            render(Index, {
                props: makeProps({
                    statusCounts: { scheduled: 1, active: 1, completed: 0, cancelled: 0 },
                }),
            });
            await fireEvent.click(screen.getByText(/scheduled/i));

            expect(mockApi.getTrips).toHaveBeenCalledWith(
                expect.objectContaining({ status: 'scheduled' }),
                expect.any(Object),
            );
        });
    });
});