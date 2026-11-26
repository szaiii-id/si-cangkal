/**
 * ============================================================
 * Admin Bookings — Index Page — Component Test
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
    getBookings: vi.fn(),
}));

// ============================================================
// 4. MOCK — MENUS
// ============================================================
vi.mock('@/utils/menus', () => ({
    adminMenu: [],
}));

// ============================================================
// 5. MOCK — BOOKING RULES
// ============================================================
vi.mock('@/utils/bookingRules', () => ({
    BOOKING_STATUS_ORDER: [
        'pending_payment',
        'payment_verified',
        'confirmed',
        'in_progress',
        'completed',
        'cancelled',
    ],
}));

// ============================================================
// 6. MOCK — ENUMS (bookingStatusShort)
// ============================================================
vi.mock('@/utils/enums', () => ({
    bookingStatusShort: vi.fn((status: string) => {
        const map: Record<string, string> = {
            pending_payment: 'Menunggu Bayar',
            payment_verified: 'Bayar Terverifikasi',
            confirmed: 'Terkonfirmasi',
            in_progress: 'Berlangsung',
            completed: 'Selesai',
            cancelled: 'Dibatalkan',
        };
        return map[status] ?? status;
    }),
}));

// ============================================================
// 7. MOCK — DASHBOARD LAYOUT (pass-through)
// ============================================================
vi.mock('@/components/layout/DashboardLayout.svelte', () => ({
    default: (anchor: Node, props: { children?: (anchor: Node) => void }) => {
        props.children?.(anchor);
        return {};
    },
}));

// ============================================================
// 8. MOCK — PAGE HEADER (render title/desc/actions)
// ============================================================
vi.mock('@/global/PageHeader.svelte', () => ({
    default: (
        anchor: Node,
        props: {
            title: string;
            description?: string;
            actions?: (anchor: Node) => void;
        },
    ) => {
        const el = document.createElement('div');
        el.setAttribute('data-testid', 'page-header');
        el.innerHTML = `<h1>${props.title}</h1><p>${props.description ?? ''}</p>`;
        anchor.parentNode?.insertBefore(el, anchor);
        props.actions?.(anchor);
        return {};
    },
}));

// ============================================================
// 9. MOCK — BOOKING FILTER BAR (reactive, callbacks)
// ============================================================
vi.mock('@/components/admin/bookings/BookingFilterBar.svelte', () => ({
    default: (
        anchor: Node,
        props: {
            search: string;
            onSearch: () => void;
            onClearSearch: () => void;
            statusTabs: Array<{ value: string; label: string; count: number }>;
            selectedStatus: string;
            onStatusChange: (status: string) => void;
            showDateRange?: boolean;
            dateFrom: string;
            dateTo: string;
            onApplyDate: () => void;
            onClearAll: () => void;
            hasActiveFilters: boolean;
        },
    ) => {
        const el = document.createElement('div');
        el.setAttribute('data-testid', 'booking-filter-bar');
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
            searchInput.placeholder = 'Cari kode pesanan, nama pelanggan…';
            searchInput.value = props.search ?? '';
            searchInput.oninput = (e) => {
                props.search = (e.target as HTMLInputElement).value;
                props.onSearch();
            };
            el.appendChild(searchInput);

            // Clear search button
            const clearBtn = document.createElement('button');
            clearBtn.type = 'button';
            clearBtn.textContent = 'Hapus Pencarian';
            clearBtn.onclick = () => props.onClearSearch();
            el.appendChild(clearBtn);

            // Date range (jika showDateRange)
            if (props.showDateRange) {
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

                const applyBtn = document.createElement('button');
                applyBtn.type = 'button';
                applyBtn.textContent = 'Terapkan';
                applyBtn.onclick = () => props.onApplyDate();
                el.appendChild(applyBtn);
            }

            // Clear all
            if (props.hasActiveFilters) {
                const clearAll = document.createElement('button');
                clearAll.type = 'button';
                clearAll.textContent = 'Hapus Semua Filter';
                clearAll.onclick = () => props.onClearAll();
                el.appendChild(clearAll);
            }
        };

        update();
        mockUpdaters.add(update);
        return {};
    },
}));

// ============================================================
// 10. MOCK — BOOKING TABLE (reactive, minimal)
// ============================================================
vi.mock('@/components/admin/bookings/BookingTable.svelte', () => ({
    default: (
        anchor: Node,
        props: {
            bookings: {
                data: Array<{ id: number; kode: string; status: string }>;
            };
        },
    ) => {
        const el = document.createElement('div');
        el.setAttribute('data-testid', 'booking-table');
        anchor.parentNode?.insertBefore(el, anchor);

        const update = () => {
            el.innerHTML = '';
            const rows = (props.bookings?.data ?? [])
                .map(
                    (b) =>
                        `<tr data-testid="booking-row-${b.id}" data-booking-kode="${b.kode}" data-booking-status="${b.status}">
                            <td>${b.kode}</td>
                            <td>${b.status}</td>
                        </tr>`,
                )
                .join('');
            el.innerHTML = `<table data-testid="booking-table-inner"><tbody>${rows}</tbody></table>`;
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
import { getBookings } from '@/api/admin';

// ============================================================
// 14. FIXTURES
// ============================================================
const makeBooking = (overrides: Record<string, unknown> = {}) =>
    ({
        id: 1,
        package_id: 1,
        user_id: 100,
        mitra_id: 200,
        kode: 'BK-001',
        status: 'pending_payment',
        tanggal_keberangkatan: '2026-02-01',
        jumlah_peserta: 2,
        total_harga: '1000000',
        catatan: null,
        payment_proof_path: null,
        expired_at: null,
        paid_at: null,
        confirmed_at: null,
        cancelled_at: null,
        created_at: '2026-01-15T00:00:00Z',
        updated_at: '2026-01-15T00:00:00Z',
        package: { nama: 'Paket Bali' },
        client: { name: 'Budi', email: 'budi@test.com' },
        ...overrides,
    }) as any;

const makeBookings = (
    data = [makeBooking()],
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
    is_today: '' as string | number | boolean,
    sort: 'created_at',
    direction: 'desc',
};

const DEFAULT_SORT = {
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
    bookings = makeBookings(),
    filters: Record<string, unknown> = DEFAULT_FILTERS,
    statusCounts = DEFAULT_STATUS_COUNTS,
) =>
    render(Index, {
        props: { bookings, filters, statusCounts } as any,
    });

// ============================================================
// 15. TEST SUITE
// ============================================================
describe('Admin Bookings — Index Page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        mockUpdaters.clear();
        vi.useFakeTimers();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    // ----------------------------------------------------------
    // 15.1 RENDERING — Header & Today Toggle
    // ----------------------------------------------------------
    describe('Rendering — Header', () => {
        it('menampilkan judul & deskripsi halaman', () => {
            renderPage();

            expect(
                screen.getByRole('heading', {
                    level: 1,
                    name: 'Daftar Pesanan',
                }),
            ).toBeInTheDocument();
            expect(
                screen.getByText(
                    'Pantau semua pesanan yang masuk dari pelanggan.',
                ),
            ).toBeInTheDocument();
        });

        it('menampilkan tombol "Pesanan Hari Ini" saat isToday=false', () => {
            renderPage();

            expect(
                screen.getByRole('button', { name: /Pesanan Hari Ini/i }),
            ).toBeInTheDocument();
        });

        it('menampilkan tombol "Menampilkan Hari Ini" saat isToday=true', () => {
            renderPage(makeBookings(), {
                ...DEFAULT_FILTERS,
                is_today: 1,
            });

            expect(
                screen.getByRole('button', { name: /Menampilkan Hari Ini/i }),
            ).toBeInTheDocument();
        });
    });

    // ----------------------------------------------------------
    // 15.2 RENDERING — Status Tabs (derived counts)
    // ----------------------------------------------------------
    describe('Rendering — Status Tabs', () => {
        it('menampilkan tab "Semua" dengan total semua status', () => {
            renderPage(makeBookings(), DEFAULT_FILTERS, {
                ...DEFAULT_STATUS_COUNTS,
                pending_payment: 2,
                confirmed: 3,
            });

            expect(
                screen.getByRole('tab', { name: /^Semua \(5\)/ }),
            ).toBeInTheDocument();
        });

        it('TIDAK menampilkan tab status yang count-nya 0', () => {
            renderPage(makeBookings(), DEFAULT_FILTERS, {
                ...DEFAULT_STATUS_COUNTS,
                pending_payment: 1,
                // sisanya 0
            });

            expect(
                screen.getByRole('tab', { name: /Menunggu Bayar \(1\)/ }),
            ).toBeInTheDocument();
            expect(
                screen.queryByRole('tab', { name: /Terkonfirmasi/ }),
            ).not.toBeInTheDocument();
        });

        it('menampilkan tab status yang count-nya 0 jika dia selected', () => {
            renderPage(
                makeBookings(),
                { ...DEFAULT_FILTERS, status: 'confirmed' },
                {
                    ...DEFAULT_STATUS_COUNTS,
                    // semua 0
                },
            );

            expect(
                screen.getByRole('tab', { name: /Terkonfirmasi \(0\)/ }),
            ).toBeInTheDocument();
        });
    });

    // ----------------------------------------------------------
    // 15.3 RENDERING — Table / Empty / Pagination
    // ----------------------------------------------------------
    describe('Rendering — Content', () => {
        it('merender BookingTable + Pagination saat data tersedia', () => {
            renderPage();

            expect(screen.getByTestId('booking-table')).toBeInTheDocument();
            expect(screen.getByTestId('pagination')).toBeInTheDocument();
            expect(screen.queryByTestId('empty-state')).not.toBeInTheDocument();
        });

        it('merender EmptyState saat data kosong tanpa filter', () => {
            renderPage(makeBookings([]));

            const emptyState = screen.getByTestId('empty-state');
            expect(emptyState).toHaveTextContent('Belum ada pesanan');
            expect(emptyState).toHaveTextContent(
                'Pesanan dari pelanggan akan muncul di sini.',
            );
            expect(
                screen.queryByTestId('booking-table'),
            ).not.toBeInTheDocument();
            expect(screen.queryByTestId('pagination')).not.toBeInTheDocument();
        });

        it('merender EmptyState pencarian saat filter aktif', () => {
            renderPage(makeBookings([]), {
                ...DEFAULT_FILTERS,
                search: 'budi',
            });

            const emptyState = screen.getByTestId('empty-state');
            expect(emptyState).toHaveTextContent('Tidak ada pesanan ditemukan');
            expect(emptyState).toHaveTextContent(
                'Coba ubah filter atau kata kunci pencarian Anda.',
            );
            expect(
                screen.getByRole('button', { name: 'Reset Filter' }),
            ).toBeInTheDocument();
        });
    });

    // ----------------------------------------------------------
    // 15.4 INTERACTION — Search Debounce
    // ----------------------------------------------------------
    describe('Interaction — Search Debounce', () => {
        it('tidak memanggil getBookings sebelum 300ms', async () => {
            renderPage();

            const input = screen.getByPlaceholderText(
                /Cari kode pesanan, nama pelanggan/i,
            );
            await fireEvent.input(input, { target: { value: 'budi' } });

            expect(getBookings).not.toHaveBeenCalled();
        });

        it('memanggil getBookings setelah 300ms dengan query search saja', async () => {
            renderPage();

            const input = screen.getByPlaceholderText(
                /Cari kode pesanan, nama pelanggan/i,
            );
            await fireEvent.input(input, { target: { value: 'budi' } });

            await vi.advanceTimersByTimeAsync(300);

            expect(getBookings).toHaveBeenCalledOnce();
            expect(getBookings).toHaveBeenCalledWith(
                { search: 'budi', ...DEFAULT_SORT },
                { preserveState: true, preserveScroll: true, replace: true },
            );
        });
    });

    // ----------------------------------------------------------
    // 15.5 INTERACTION — Status Change
    // ----------------------------------------------------------
    describe('Interaction — Status Change', () => {
        it('klik tab status memanggil getBookings dengan query status', async () => {
            renderPage(makeBookings(), DEFAULT_FILTERS, {
                ...DEFAULT_STATUS_COUNTS,
                pending_payment: 2,
            });

            await fireEvent.click(
                screen.getByRole('tab', { name: /Menunggu Bayar/ }),
            );

            expect(getBookings).toHaveBeenCalledWith(
                { status: 'pending_payment', ...DEFAULT_SORT },
                { preserveState: true, preserveScroll: true, replace: true },
            );
        });
    });

    // ----------------------------------------------------------
    // 15.6 INTERACTION — Date Range
    // ----------------------------------------------------------
    describe('Interaction — Date Range', () => {
        it('klik Terapkan memanggil getBookings dengan tanggal_dari & tanggal_sampai', async () => {
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

            expect(getBookings).toHaveBeenCalledWith(
                {
                    tanggal_dari: '2026-02-01',
                    tanggal_sampai: '2026-02-28',
                    ...DEFAULT_SORT,
                },
                { preserveState: true, preserveScroll: true, replace: true },
            );
        });
    });

    // ----------------------------------------------------------
    // 15.7 INTERACTION — Today Toggle
    // ----------------------------------------------------------
    describe('Interaction — Today Toggle', () => {
        it('klik toggle today memanggil getBookings dengan is_today=1', async () => {
            renderPage();

            await fireEvent.click(
                screen.getByRole('button', { name: /Pesanan Hari Ini/i }),
            );

            expect(getBookings).toHaveBeenCalledWith(
                { is_today: 1, ...DEFAULT_SORT },
                { preserveState: true, preserveScroll: true, replace: true },
            );
        });

        it('toggle today dari aktif → nonaktif mengirim query kosong', async () => {
            renderPage(makeBookings(), { ...DEFAULT_FILTERS, is_today: 1 });

            await fireEvent.click(
                screen.getByRole('button', { name: /Menampilkan Hari Ini/i }),
            );

            expect(getBookings).toHaveBeenCalledWith(
                { ...DEFAULT_SORT },
                { preserveState: true, preserveScroll: true, replace: true },
            );
        });

        it('mengaktifkan today mereset tanggal_dari & tanggal_sampai', async () => {
            renderPage(makeBookings(), {
                ...DEFAULT_FILTERS,
                tanggal_dari: '2026-02-01',
                tanggal_sampai: '2026-02-28',
            });

            await fireEvent.click(
                screen.getByRole('button', { name: /Pesanan Hari Ini/i }),
            );

            expect(getBookings).toHaveBeenCalledWith(
                { is_today: 1, ...DEFAULT_SORT },
                { preserveState: true, preserveScroll: true, replace: true },
            );
        });
    });

    // ----------------------------------------------------------
    // 15.8 INTERACTION — Clear Filters
    // ----------------------------------------------------------
    describe('Interaction — Clear Filters', () => {
        it('klik "Hapus Semua Filter" memanggil getBookings dengan query kosong', async () => {
            renderPage(makeBookings(), {
                ...DEFAULT_FILTERS,
                search: 'budi',
                status: 'confirmed',
                is_today: 1,
            });

            await fireEvent.click(
                screen.getByRole('button', { name: 'Hapus Semua Filter' }),
            );

            expect(getBookings).toHaveBeenCalledWith(
                {},
                { preserveState: true, preserveScroll: true, replace: true },
            );
        });

        it('klik "Reset Filter" di EmptyState memanggil clearFilters', async () => {
            renderPage(makeBookings([]), { ...DEFAULT_FILTERS, search: 'xyz' });

            await fireEvent.click(
                screen.getByRole('button', { name: 'Reset Filter' }),
            );

            expect(getBookings).toHaveBeenCalledWith(
                {},
                { preserveState: true, preserveScroll: true, replace: true },
            );
        });
    });

    // ----------------------------------------------------------
    // 15.9 REACTIVITY — Has Active Filters
    // ----------------------------------------------------------
    describe('Reactivity — Has Active Filters', () => {
        it('filter bar menerima hasActiveFilters=true saat search terisi', () => {
            renderPage(makeBookings(), { ...DEFAULT_FILTERS, search: 'budi' });

            // "Hapus Semua Filter" muncul hanya jika hasActiveFilters
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
    // 15.10 A11Y
    // ----------------------------------------------------------
    describe('Accessibility', () => {
        it('status tabs punya role="tablist" dan aria-selected', () => {
            renderPage(
                makeBookings(),
                { ...DEFAULT_FILTERS, status: 'pending_payment' },
                {
                    ...DEFAULT_STATUS_COUNTS,
                    pending_payment: 1,
                },
            );

            const tablist = screen.getByRole('tablist');
            expect(tablist).toBeInTheDocument();
            expect(
                screen.getByRole('tab', { name: /Menunggu Bayar/ }),
            ).toHaveAttribute('aria-selected', 'true');
        });
    });
});
