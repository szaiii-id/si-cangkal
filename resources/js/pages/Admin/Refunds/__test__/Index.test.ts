/**
 * ============================================================
 * Admin Refunds — Index Page — Component Test
 * ============================================================
 *
 * Fokus pengujian:
 *   1. Isolation & Mocking  → Inertia, API getRefunds, menu,
 *                             refundStatusLabel, & child components
 *                             (PageHeader, RefundFilterBar, RefundCard,
 *                             EmptyState, Pagination) di-mock.
 *   2. Props & Rendering    → header, status tabs (derived counts),
 *                             grid card, pagination, empty state.
 *   3. Interaction          → search debounce, change status,
 *                             clear filters.
 *   4. Reactivity           → hasActiveFilters derived.
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
    getRefunds: vi.fn(),
}));

// ============================================================
// 4. MOCK — MENUS
// ============================================================
vi.mock('@/utils/menus', () => ({
    adminMenu: [],
}));

// ============================================================
// 5. MOCK — ENUMS (refundStatusLabel)
// ============================================================
vi.mock('@/utils/enums', () => ({
    refundStatusLabel: vi.fn((s: string) => {
        const map: Record<string, string> = {
            PENDING: 'Menunggu',
            SUCCEEDED: 'Berhasil',
            FAILED: 'Gagal',
            CANCELLED: 'Dibatalkan',
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
// 8. MOCK — REFUND FILTER BAR
// ============================================================
vi.mock('@/components/admin/refunds/RefundFilterBar.svelte', () => ({
    default: (
        anchor: Node,
        props: {
            search: string;
            onSearch: () => void;
            statusTabs: Array<{ value: string; label: string; count: number }>;
            selectedStatus: string;
            onStatusChange: (status: string) => void;
            onClear: () => void;
            hasActiveFilters: boolean;
        },
    ) => {
        const el = document.createElement('div');
        el.setAttribute('data-testid', 'refund-filter-bar');
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
            searchInput.placeholder = 'Cari kode pesanan atau nama pelanggan…';
            searchInput.value = props.search ?? '';
            searchInput.oninput = (e) => {
                props.search = (e.target as HTMLInputElement).value;
                props.onSearch();
            };
            el.appendChild(searchInput);

            // Clear all
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
// 9. MOCK — REFUND CARD
// ============================================================
vi.mock('@/components/admin/refunds/RefundCard.svelte', () => ({
    default: (
        anchor: Node,
        props: {
            refund: { id: number; status: string; amountRupiah: string };
        },
    ) => {
        const el = document.createElement('div');
        el.setAttribute('data-testid', `refund-card-${props.refund.id}`);
        el.setAttribute('data-refund-status', props.refund.status);
        el.textContent = `${props.refund.status} - ${props.refund.amountRupiah}`;
        anchor.parentNode?.insertBefore(el, anchor);
        return {};
    },
}));

// ============================================================
// 10. MOCK — EMPTY STATE
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
// 11. MOCK — PAGINATION
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
// 12. STATIC IMPORT
// ============================================================
import { getRefunds } from '@/api/admin';

// ============================================================
// 14. FIXTURES (Sesuai RefundResource)
// ============================================================
const makeRefund = (overrides = {}) => ({
    id: 1,
    booking_id: '10',
    mitra_id: '100',
    amount: '500000',
    amountRupiah: 'Rp 500.000',
    currency: 'IDR',
    reason: 'CANCELLATION',
    reasonLabel: 'Pembatalan',
    status: 'PENDING',
    statusLabel: 'Menunggu',
    isFinal: false,
    failure_code: null,
    refund_fee_amount: null,
    refundFeeRupiah: null,
    catatan: null,
    created_at: '2026-01-15T10:30:00Z',
    booking: {
        id: 10,
        kode: 'BK-001',
        total_harga: '1500000',
        status: 'confirmed',
    },
    mitra: { id: '100', name: 'PT Mitra Jaya', email: 'mitra@test.com' },
    mitraInitiator: {
        id: '100',
        name: 'PT Mitra Jaya',
        email: 'mitra@test.com',
    },
    ...overrides,
});

const makePagination = (data: any[], overrides = {}) => ({
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

const SAMPLE_REFUNDS = [
    makeRefund({ id: 1, status: 'PENDING', amountRupiah: 'Rp 500.000' }),
    makeRefund({ id: 2, status: 'SUCCEEDED', amountRupiah: 'Rp 300.000' }),
];

const DEFAULT_FILTERS = { search: '', status: '' };
const DEFAULT_STATUS_COUNTS = {
    PENDING: 1,
    SUCCEEDED: 1,
    FAILED: 0,
    CANCELLED: 0,
};

const renderPage = (
    refunds = makePagination(SAMPLE_REFUNDS),
    filters = DEFAULT_FILTERS,
    statusCounts = DEFAULT_STATUS_COUNTS,
) =>
    render(Index, {
        props: { refunds, filters, statusCounts },
    });

// ============================================================
// 15. TEST SUITE
// ============================================================
describe('Admin Refunds — Index Page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        mockUpdaters.clear();
        vi.useFakeTimers();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    // ----------------------------------------------------------
    // 15.1 RENDERING — Header & Tabs
    // ----------------------------------------------------------
    describe('Rendering', () => {
        it('menampilkan judul & deskripsi halaman', () => {
            renderPage();

            expect(
                screen.getByRole('heading', {
                    level: 1,
                    name: 'Pengembalian Dana',
                }),
            ).toBeInTheDocument();
            expect(
                screen.getByText(/Pantau semua pengembalian dana/i),
            ).toBeInTheDocument();
        });

        it('menampilkan tab "Semua" dengan total semua status', () => {
            renderPage(makePagination(SAMPLE_REFUNDS), DEFAULT_FILTERS, {
                PENDING: 2,
                SUCCEEDED: 1,
                FAILED: 1,
                CANCELLED: 0,
            });

            expect(
                screen.getByRole('tab', { name: /^Semua \(4\)/ }),
            ).toBeInTheDocument();
        });

        it('TIDAK menampilkan tab status yang count-nya 0', () => {
            renderPage(makePagination(SAMPLE_REFUNDS), DEFAULT_FILTERS, {
                PENDING: 1,
                SUCCEEDED: 0,
                FAILED: 0,
                CANCELLED: 0,
            });

            expect(
                screen.getByRole('tab', { name: /Menunggu \(1\)/ }),
            ).toBeInTheDocument();
            expect(
                screen.queryByRole('tab', { name: /Berhasil/ }),
            ).not.toBeInTheDocument();
        });
    });

    // ----------------------------------------------------------
    // 15.2 RENDERING — Grid & Pagination
    // ----------------------------------------------------------
    describe('Rendering — Grid', () => {
        it('merender RefundCard untuk setiap refund (menggunakan RefundResource)', () => {
            renderPage();

            expect(screen.getByTestId('refund-card-1')).toBeInTheDocument();
            expect(screen.getByTestId('refund-card-2')).toBeInTheDocument();
            expect(screen.getByTestId('refund-card-1')).toHaveTextContent(
                'Rp 500.000',
            );
        });

        it('merender pagination saat data tersedia', () => {
            renderPage();
            expect(screen.getByTestId('pagination')).toHaveTextContent(
                'Total 2 pengembalian',
            );
        });
    });

    // ----------------------------------------------------------
    // 15.3 RENDERING — EmptyState
    // ----------------------------------------------------------
    describe('Rendering — EmptyState', () => {
        it('merender EmptyState default tanpa filter', () => {
            renderPage(makePagination([]));

            expect(screen.getByTestId('empty-state')).toHaveTextContent(
                'Belum ada pengembalian',
            );
            expect(
                screen.queryByRole('button', { name: 'Reset Filter' }),
            ).not.toBeInTheDocument();
        });

        it('merender EmptyState search context saat filter aktif', () => {
            renderPage(makePagination([]), {
                ...DEFAULT_FILTERS,
                search: 'budi',
            });

            expect(screen.getByTestId('empty-state')).toHaveTextContent(
                'Tidak ada pengembalian ditemukan',
            );
            expect(
                screen.getByRole('button', { name: 'Reset Filter' }),
            ).toBeInTheDocument();
        });

        it('TIDAK merender Pagination saat empty', () => {
            renderPage(makePagination([]));
            expect(screen.queryByTestId('pagination')).not.toBeInTheDocument();
        });
    });

    // ----------------------------------------------------------
    // 15.4 INTERACTION — Search Debounce
    // ----------------------------------------------------------
    describe('Interaction — Search Debounce', () => {
        it('tidak memanggil getRefunds sebelum 300ms', async () => {
            renderPage();

            const input = screen.getByPlaceholderText(
                /Cari kode pesanan atau nama pelanggan/i,
            );
            await fireEvent.input(input, { target: { value: 'budi' } });

            expect(getRefunds).not.toHaveBeenCalled();
        });

        it('memanggil getRefunds setelah 300ms dengan query search', async () => {
            renderPage();

            const input = screen.getByPlaceholderText(
                /Cari kode pesanan atau nama pelanggan/i,
            );
            await fireEvent.input(input, { target: { value: 'budi' } });

            await vi.advanceTimersByTimeAsync(300);

            expect(getRefunds).toHaveBeenCalledOnce();
            expect(getRefunds).toHaveBeenCalledWith(
                { search: 'budi', status: '' },
                { preserveState: true, preserveScroll: true, replace: true },
            );
        });
    });

    // ----------------------------------------------------------
    // 15.5 INTERACTION — Change Status
    // ----------------------------------------------------------
    describe('Interaction — Change Status', () => {
        it('klik tab status memanggil getRefunds dengan status', async () => {
            renderPage();

            await fireEvent.click(
                screen.getByRole('tab', { name: /Menunggu/ }),
            );

            expect(getRefunds).toHaveBeenCalledWith(
                { search: '', status: 'PENDING' },
                { preserveState: true, preserveScroll: true, replace: true },
            );
        });
    });

    // ----------------------------------------------------------
    // 15.6 INTERACTION — Clear Filters
    // ----------------------------------------------------------
    describe('Interaction — Clear Filters', () => {
        it('klik "Hapus Semua Filter" memanggil getRefunds dengan query kosong', async () => {
            renderPage(makePagination(SAMPLE_REFUNDS), {
                search: 'budi',
                status: 'PENDING',
            });

            await fireEvent.click(
                screen.getByRole('button', { name: 'Hapus Semua Filter' }),
            );

            expect(getRefunds).toHaveBeenCalledWith(
                {},
                { preserveState: true, preserveScroll: true, replace: true },
            );
        });

        it('klik "Reset Filter" di EmptyState memanggil clearFilters', async () => {
            renderPage(makePagination([]), {
                ...DEFAULT_FILTERS,
                search: 'xyz',
            });

            await fireEvent.click(
                screen.getByRole('button', { name: 'Reset Filter' }),
            );

            expect(getRefunds).toHaveBeenCalledWith(
                {},
                { preserveState: true, preserveScroll: true, replace: true },
            );
        });
    });
});
