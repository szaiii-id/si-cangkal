/**
 * ============================================================
 * Admin Trips — Index Page — Component Test
 * ============================================================
 *
 * Fokus pengujian:
 *   1. Isolation & Mocking  → Inertia, API getTrips, menu, enums
 *                             (tripStatusShort), & child components
 *                             (PageHeader, TripFilterBar, TripTable,
 *                             EmptyState, Pagination, BackButton) di-mock
 *                             anchor-aware.
 *   2. Props & Rendering    → header (title/desc conditional packageId),
 *                             back button (hanya jika packageId),
 *                             status tabs (derived counts), content.
 *   3. Interaction          → search debounce, status change, date range,
 *                             clear filters (preserve packageId).
 *   4. Reactivity           → hasActiveFilters derived, buildQuery.
 *
 * Strategi:
 *   - TripFilterBar di-mock dengan input yang mem-bind props + callback.
 *   - TripTable di-mock minimal — render row per trip.
 *   - `mockUpdaters` sinkronisasi.
 *   - A11y-first query.
 * ============================================================
 */

import { render, screen, fireEvent } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import Index from '../Index.svelte';

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
// 3. MOCK — API ADMIN
// ============================================================
vi.mock('@/api/admin', () => ({
    getTrips: vi.fn(),
}));

// ============================================================
// 4. MOCK — MENUS
// ============================================================
vi.mock('@/utils/menus', () => ({
    adminMenu: [],
}));

// ============================================================
// 5. MOCK — ENUMS (tripStatusShort)
// ============================================================
vi.mock('@/utils/enums', () => ({
    tripStatusShort: vi.fn((s: string) => {
        const map: Record<string, string> = {
            scheduled: 'Terjadwal',
            active: 'Berlangsung',
            completed: 'Selesai',
            cancelled: 'Dibatalkan',
        };
        return map[s] ?? s;
    }),
}));

// ============================================================
// 6. MOCK — DASHBOARD LAYOUT
// ============================================================
vi.mock('@/components/layout/DashboardLayout.svelte', () => ({
    default: (anchor: Node, props: { children?: (anchor: Node) => void }) => {
        props.children?.(anchor);
        return {};
    },
}));

// ============================================================
// 7. MOCK — PAGE HEADER
// ============================================================
vi.mock('@/global/PageHeader.svelte', () => ({
    default: (anchor: Node, props: { title: string; description?: string }) => {
        const el = document.createElement('div');
        el.setAttribute('data-testid', 'page-header');
        el.innerHTML = `<h1>${props.title}</h1><p>${props.description ?? ''}</p>`;
        anchor.parentNode?.insertBefore(el, anchor);
        return {};
    },
}));

// ============================================================
// 8. MOCK — BACK BUTTON
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
// 9. MOCK — TRIP FILTER BAR (reactive)
// ============================================================
vi.mock('@/components/admin/trips/TripFilterBar.svelte', () => ({
    default: (
        anchor: Node,
        props: {
            search: string;
            onSearch: () => void;
            statusTabs: Array<{ value: string; label: string; count: number }>;
            selectedStatus: string;
            onStatusChange: (status: string) => void;
            dateFrom: string;
            dateTo: string;
            onApplyDate: () => void;
            onClear: () => void;
            hasActiveFilters: boolean;
        },
    ) => {
        const el = document.createElement('div');
        el.setAttribute('data-testid', 'trip-filter-bar');
        anchor.parentNode?.insertBefore(el, anchor);

        const update = () => {
            el.innerHTML = '';

            // Status tabs
            const tabsEl = document.createElement('div');
            tabsEl.setAttribute('role', 'tablist');
            props.statusTabs.forEach((tab) => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.setAttribute('role', 'tab');
                btn.setAttribute(
                    'aria-selected',
                    String(props.selectedStatus === tab.value),
                );
                btn.textContent = `${tab.label} (${tab.count})`;
                btn.onclick = () => props.onStatusChange(tab.value);
                tabsEl.appendChild(btn);
            });
            el.appendChild(tabsEl);

            // Search input
            const searchInput = document.createElement('input');
            searchInput.type = 'search';
            searchInput.placeholder =
                'Cari kode trip, paket, atau nama pelanggan…';
            searchInput.value = props.search ?? '';
            searchInput.oninput = (e) => {
                props.search = (e.target as HTMLInputElement).value;
                props.onSearch();
            };
            el.appendChild(searchInput);

            // Date range inputs (selalu di-mock terbuka)
            const dateFrom = document.createElement('input');
            dateFrom.type = 'date';
            dateFrom.setAttribute('aria-label', 'Dari Tanggal');
            dateFrom.value = props.dateFrom ?? '';
            dateFrom.oninput = (e) => {
                props.dateFrom = (e.target as HTMLInputElement).value;
            };
            el.appendChild(dateFrom);

            const dateTo = document.createElement('input');
            dateTo.type = 'date';
            dateTo.setAttribute('aria-label', 'Sampai Tanggal');
            dateTo.value = props.dateTo ?? '';
            dateTo.oninput = (e) => {
                props.dateTo = (e.target as HTMLInputElement).value;
            };
            el.appendChild(dateTo);

            // Apply button
            const applyBtn = document.createElement('button');
            applyBtn.type = 'button';
            applyBtn.textContent = 'Terapkan';
            applyBtn.onclick = () => props.onApplyDate();
            el.appendChild(applyBtn);

            // Clear all (jika hasActiveFilters)
            if (props.hasActiveFilters) {
                const clearBtn = document.createElement('button');
                clearBtn.type = 'button';
                clearBtn.textContent = 'Hapus Semua Filter';
                clearBtn.onclick = () => props.onClear();
                el.appendChild(clearBtn);
            }
        };

        update();
        mockUpdaters.add(update);
        return {};
    },
}));

// ============================================================
// 10. MOCK — TRIP TABLE (reactive, minimal)
// ============================================================
vi.mock('@/components/admin/trips/TripTable.svelte', () => ({
    default: (
        anchor: Node,
        props: {
            trips: {
                data: Array<{ id: number; kode: string; status: string }>;
            };
        },
    ) => {
        const el = document.createElement('div');
        el.setAttribute('data-testid', 'trip-table');
        anchor.parentNode?.insertBefore(el, anchor);

        const update = () => {
            el.innerHTML = '';
            const rows = (props.trips?.data ?? [])
                .map(
                    (t) =>
                        `<tr data-testid="trip-row-${t.id}" data-trip-kode="${t.kode}" data-trip-status="${t.status}">
                            <td>${t.kode}</td>
                            <td>${t.status}</td>
                        </tr>`,
                )
                .join('');
            el.innerHTML = `<table data-testid="trip-table-inner"><tbody>${rows}</tbody></table>`;
        };

        update();
        mockUpdaters.add(update);
        return {};
    },
}));

// ============================================================
// 11. MOCK — EMPTY STATE
// ============================================================
vi.mock('@/global/EmptyState.svelte', () => ({
    default: (
        anchor: Node,
        props: {
            title: string;
            description: string;
            hasFilters?: boolean;
            onClear?: () => void;
        },
    ) => {
        const el = document.createElement('div');
        el.setAttribute('data-testid', 'empty-state');
        anchor.parentNode?.insertBefore(el, anchor);

        const update = () => {
            el.innerHTML = `<h3>${props.title}</h3><p>${props.description}</p>`;
            if (props.hasFilters) {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.textContent = 'Reset Filter';
                btn.onclick = () => props.onClear?.();
                el.appendChild(btn);
            }
        };

        update();
        mockUpdaters.add(update);
        return {};
    },
}));

// ============================================================
// 12. MOCK — PAGINATION
// ============================================================
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
// 13. STATIC IMPORT
// ============================================================
import { getTrips } from '@/api/admin';

// ============================================================
// 14. FIXTURES
// ============================================================
const makeTrip = (overrides: Record<string, unknown> = {}) =>
    ({
        id: 1,
        booking_id: '10',
        package_id: 1,
        mitra_id: '200',
        kode: 'TRIP-001',
        status: 'scheduled',
        tanggal_mulai: '2026-02-01',
        tanggal_selesai: '2026-02-03',
        jumlah_peserta: 2,
        timezone: 'Asia/Jakarta',
        snapshot_package: null,
        created_at: '2026-01-01T00:00:00Z',
        updated_at: '2026-01-01T00:00:00Z',
        package: { nama: 'Paket Bali' },
        booking: { client: { name: 'Budi', email: 'budi@test.com' } },
        mitra: { name: 'PT Mitra' },
        ...overrides,
    }) as any;

const makeTrips = (
    data = [makeTrip()],
    overrides: Record<string, unknown> = {},
) =>
    ({
        current_page: 1,
        last_page: 1,
        per_page: 15,
        total: data.length,
        from: data.length ? 1 : null,
        to: data.length ? data.length : null,
        links: [],
        data,
        ...overrides,
    }) as any;

const DEFAULT_FILTERS = {
    search: '',
    status: '',
    tanggal_dari: '',
    tanggal_sampai: '',
    sort: 'tanggal_mulai',
    direction: 'desc' as const,
    mitra_id: null as string | null,
    package_id: null as string | number | null,
};

const DEFAULT_STATUS_COUNTS = {
    scheduled: 0,
    active: 0,
    completed: 0,
    cancelled: 0,
};

const renderPage = (
    trips = makeTrips(),
    filters: Record<string, unknown> = DEFAULT_FILTERS,
    statusCounts = DEFAULT_STATUS_COUNTS,
) =>
    render(Index, {
        props: { trips, filters, statusCounts } as any,
    });

// ============================================================
// 15. TEST SUITE
// ============================================================
describe('Admin Trips — Index Page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        mockUpdaters.clear();
        vi.useFakeTimers();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    // ----------------------------------------------------------
    // 15.1 RENDERING — Header & Back Button
    // ----------------------------------------------------------
    describe('Rendering — Header', () => {
        it('menampilkan judul "Jadwal Perjalanan"', () => {
            renderPage();
            expect(
                screen.getByRole('heading', {
                    level: 1,
                    name: 'Jadwal Perjalanan',
                }),
            ).toBeInTheDocument();
        });

        it('deskripsi default saat packageId null', () => {
            renderPage();
            expect(
                screen.getByText(/Pantau semua jadwal perjalanan \(trip\)/i),
            ).toBeInTheDocument();
        });

        it('deskripsi conditional saat packageId terisi', () => {
            renderPage(makeTrips(), { ...DEFAULT_FILTERS, package_id: 5 });
            expect(
                screen.getByText(
                    /Menampilkan jadwal untuk paket yang dipilih/i,
                ),
            ).toBeInTheDocument();
        });

        it('TIDAK render back button saat packageId null', () => {
            renderPage();
            expect(screen.queryByTestId('back-button')).not.toBeInTheDocument();
        });

        it('render back button saat packageId terisi', () => {
            renderPage(makeTrips(), { ...DEFAULT_FILTERS, package_id: 5 });

            const backBtn = screen.getByTestId('back-button');
            expect(backBtn).toHaveAttribute('href', '/admin/packages');
            expect(backBtn).toHaveTextContent('Kembali ke Daftar Paket');
        });
    });

    // ----------------------------------------------------------
    // 15.2 RENDERING — Status Tabs
    // ----------------------------------------------------------
    describe('Rendering — Status Tabs', () => {
        it('menampilkan tab "Semua" dengan total semua status', () => {
            renderPage(makeTrips(), DEFAULT_FILTERS, {
                scheduled: 2,
                active: 1,
                completed: 3,
                cancelled: 0,
            });

            expect(
                screen.getByRole('tab', { name: /^Semua \(6\)/ }),
            ).toBeInTheDocument();
        });

        it('TIDAK menampilkan tab status yang count-nya 0', () => {
            renderPage(makeTrips(), DEFAULT_FILTERS, {
                scheduled: 1,
                active: 0,
                completed: 0,
                cancelled: 0,
            });

            expect(
                screen.getByRole('tab', { name: /Terjadwal \(1\)/ }),
            ).toBeInTheDocument();
            expect(
                screen.queryByRole('tab', { name: /Berlangsung/ }),
            ).not.toBeInTheDocument();
        });

        it('menampilkan tab status yang selected meski count=0', () => {
            renderPage(
                makeTrips(),
                { ...DEFAULT_FILTERS, status: 'active' },
                {
                    scheduled: 1,
                    active: 0,
                    completed: 0,
                    cancelled: 0,
                },
            );

            expect(
                screen.getByRole('tab', { name: /Berlangsung \(0\)/ }),
            ).toBeInTheDocument();
        });
    });

    // ----------------------------------------------------------
    // 15.3 RENDERING — Content
    // ----------------------------------------------------------
    describe('Rendering — Content', () => {
        it('merender TripTable + Pagination saat data tersedia', () => {
            renderPage();

            expect(screen.getByTestId('trip-table')).toBeInTheDocument();
            expect(screen.getByTestId('pagination')).toBeInTheDocument();
            expect(screen.queryByTestId('empty-state')).not.toBeInTheDocument();
        });

        it('merender EmptyState default saat data kosong', () => {
            renderPage(makeTrips([]));

            const emptyState = screen.getByTestId('empty-state');
            expect(emptyState).toHaveTextContent('Belum ada jadwal');
            expect(emptyState).toHaveTextContent(
                'Jadwal perjalanan akan otomatis dibuat saat pesanan pelanggan dikonfirmasi.',
            );
            expect(screen.queryByTestId('trip-table')).not.toBeInTheDocument();
            expect(screen.queryByTestId('pagination')).not.toBeInTheDocument();
        });

        it('merender EmptyState pencarian saat filter aktif', () => {
            renderPage(makeTrips([]), { ...DEFAULT_FILTERS, search: 'bali' });

            const emptyState = screen.getByTestId('empty-state');
            expect(emptyState).toHaveTextContent('Tidak ada jadwal ditemukan');
            expect(
                screen.getByRole('button', { name: 'Reset Filter' }),
            ).toBeInTheDocument();
        });
    });

    // ----------------------------------------------------------
    // 15.4 INTERACTION — Search Debounce
    // ----------------------------------------------------------
    describe('Interaction — Search Debounce', () => {
        it('tidak memanggil getTrips sebelum 300ms', async () => {
            renderPage();

            const input = screen.getByPlaceholderText(
                /Cari kode trip, paket, atau nama pelanggan/i,
            );
            await fireEvent.input(input, { target: { value: 'bali' } });

            expect(getTrips).not.toHaveBeenCalled();
        });

        it('memanggil getTrips setelah 300ms dengan buildQuery lengkap', async () => {
            renderPage();

            const input = screen.getByPlaceholderText(
                /Cari kode trip, paket, atau nama pelanggan/i,
            );
            await fireEvent.input(input, { target: { value: 'bali' } });
            await vi.advanceTimersByTimeAsync(300);

            expect(getTrips).toHaveBeenCalledOnce();
            expect(getTrips).toHaveBeenCalledWith(
                {
                    search: 'bali',
                    status: '',
                    tanggal_dari: '',
                    tanggal_sampai: '',
                    sort: 'tanggal_mulai',
                    direction: 'desc',
                },
                { preserveState: true, preserveScroll: true, replace: true },
            );
        });
    });

    // ----------------------------------------------------------
    // 15.5 INTERACTION — Status Change
    // ----------------------------------------------------------
    describe('Interaction — Status Change', () => {
        it('klik tab status memanggil getTrips dengan query status', async () => {
            renderPage(makeTrips(), DEFAULT_FILTERS, {
                scheduled: 2,
                active: 1,
                completed: 0,
                cancelled: 0,
            });

            await fireEvent.click(
                screen.getByRole('tab', { name: /Terjadwal/ }),
            );

            expect(getTrips).toHaveBeenCalledWith(
                expect.objectContaining({ status: 'scheduled' }),
                expect.any(Object),
            );
        });
    });

    // ----------------------------------------------------------
    // 15.6 INTERACTION — Date Range
    // ----------------------------------------------------------
    describe('Interaction — Date Range', () => {
        it('klik Terapkan memanggil getTrips dengan tanggal_dari & tanggal_sampai', async () => {
            renderPage();

            await fireEvent.input(screen.getByLabelText('Dari Tanggal'), {
                target: { value: '2026-02-01' },
            });
            await fireEvent.input(screen.getByLabelText('Sampai Tanggal'), {
                target: { value: '2026-02-28' },
            });
            await fireEvent.click(
                screen.getByRole('button', { name: 'Terapkan' }),
            );

            expect(getTrips).toHaveBeenCalledWith(
                expect.objectContaining({
                    tanggal_dari: '2026-02-01',
                    tanggal_sampai: '2026-02-28',
                }),
                expect.any(Object),
            );
        });
    });

    // ----------------------------------------------------------
    // 15.7 INTERACTION — Clear Filters
    // ----------------------------------------------------------
    describe('Interaction — Clear Filters', () => {
        it('klik "Hapus Semua Filter" memanggil getTrips dengan query kosong', async () => {
            renderPage(makeTrips(), {
                ...DEFAULT_FILTERS,
                search: 'bali',
                status: 'active',
            });

            await fireEvent.click(
                screen.getByRole('button', { name: 'Hapus Semua Filter' }),
            );

            expect(getTrips).toHaveBeenCalledWith(
                {},
                { preserveState: true, preserveScroll: true, replace: true },
            );
        });

        it('preserve package_id saat clear filters', async () => {
            renderPage(makeTrips(), {
                ...DEFAULT_FILTERS,
                search: 'bali',
                package_id: 5,
            });

            await fireEvent.click(
                screen.getByRole('button', { name: 'Hapus Semua Filter' }),
            );

            expect(getTrips).toHaveBeenCalledWith(
                { package_id: 5 }, // ← package_id tetap dipertahankan
                { preserveState: true, preserveScroll: true, replace: true },
            );
        });

        it('klik "Reset Filter" di EmptyState memanggil clearFilters', async () => {
            renderPage(makeTrips([]), { ...DEFAULT_FILTERS, search: 'xyz' });

            await fireEvent.click(
                screen.getByRole('button', { name: 'Reset Filter' }),
            );

            expect(getTrips).toHaveBeenCalledWith(
                {},
                { preserveState: true, preserveScroll: true, replace: true },
            );
        });
    });

    // ----------------------------------------------------------
    // 15.8 REACTIVITY — Has Active Filters
    // ----------------------------------------------------------
    describe('Reactivity — Has Active Filters', () => {
        it('filter bar menerima hasActiveFilters=true saat search terisi', () => {
            renderPage(makeTrips(), { ...DEFAULT_FILTERS, search: 'bali' });

            expect(
                screen.getByRole('button', { name: 'Hapus Semua Filter' }),
            ).toBeInTheDocument();
        });

        it('filter bar menerima hasActiveFilters=false saat semua kosong', () => {
            renderPage();

            expect(
                screen.queryByRole('button', { name: 'Hapus Semua Filter' }),
            ).not.toBeInTheDocument();
        });
    });

    // ----------------------------------------------------------
    // 15.9 A11Y
    // ----------------------------------------------------------
    describe('Accessibility', () => {
        it('status tabs punya role="tablist" dan aria-selected', () => {
            renderPage(
                makeTrips(),
                { ...DEFAULT_FILTERS, status: 'scheduled' },
                {
                    scheduled: 1,
                    active: 0,
                    completed: 0,
                    cancelled: 0,
                },
            );

            expect(screen.getByRole('tablist')).toBeInTheDocument();
            expect(
                screen.getByRole('tab', { name: /Terjadwal/ }),
            ).toHaveAttribute('aria-selected', 'true');
        });

        it('search input punya placeholder deskriptif', () => {
            renderPage();
            expect(
                screen.getByPlaceholderText(
                    /Cari kode trip, paket, atau nama pelanggan/i,
                ),
            ).toBeInTheDocument();
        });
    });
});
