import { render, screen, fireEvent } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { tick } from 'svelte';
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
    getRevenueReport: vi.fn(),
}));

// ============================================================
// 4. MOCK — MENUS
// ============================================================
vi.mock('@/utils/menus', () => ({
    adminMenu: [],
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
// 6. MOCK — PAGE HEADER (render title & actions)
// ============================================================
vi.mock('@/global/PageHeader.svelte', () => ({
    default: (
        anchor: Node,
        props: { title: string; description?: string; actions?: (anchor: Node) => void },
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
// 7. MOCK — REPORT TABS
// ============================================================
vi.mock('@/components/reports/ReportTabs.svelte', () => ({
    default: (anchor: Node, props: { active: string }) => {
        const el = document.createElement('div');
        el.setAttribute('data-testid', 'report-tabs');
        el.setAttribute('data-active', props.active);
        anchor.parentNode?.insertBefore(el, anchor);
        return {};
    },
}));

// ============================================================
// 8. MOCK — REPORT FILTER (reactive bind startDate/endDate)
// ============================================================
vi.mock('@/components/reports/ReportFilter.svelte', () => ({
    default: (
        anchor: Node,
        props: {
            startDate: string;
            endDate: string;
            onApply: () => void;
        },
    ) => {
        const el = document.createElement('div');
        el.setAttribute('data-testid', 'report-filter');
        anchor.parentNode?.insertBefore(el, anchor);

        const update = () => {
            el.innerHTML = '';

            const startInput = document.createElement('input');
            startInput.type = 'date';
            startInput.setAttribute('aria-label', 'Start Date');
            startInput.value = props.startDate ?? '';
            startInput.oninput = (e) => {
                props.startDate = (e.target as HTMLInputElement).value;
            };
            el.appendChild(startInput);

            const endInput = document.createElement('input');
            endInput.type = 'date';
            endInput.setAttribute('aria-label', 'End Date');
            endInput.value = props.endDate ?? '';
            endInput.oninput = (e) => {
                props.endDate = (e.target as HTMLInputElement).value;
            };
            el.appendChild(endInput);

            const applyBtn = document.createElement('button');
            applyBtn.type = 'button';
            applyBtn.textContent = 'Apply';
            applyBtn.onclick = () => props.onApply();
            el.appendChild(applyBtn);
        };

        update();
        mockUpdaters.add(update);
        return {};
    },
}));

// ============================================================
// 9. MOCK — EXPORT BUTTONS
// ============================================================
vi.mock('@/components/reports/ExportButtons.svelte', () => ({
    default: (anchor: Node, props: { pdfUrl: string; excelUrl: string }) => {
        const el = document.createElement('div');
        el.setAttribute('data-testid', 'export-buttons');
        el.setAttribute('data-pdf-url', props.pdfUrl);
        el.setAttribute('data-excel-url', props.excelUrl);
        anchor.parentNode?.insertBefore(el, anchor);

        const update = () => {
            el.setAttribute('data-pdf-url', props.pdfUrl);
            el.setAttribute('data-excel-url', props.excelUrl);
        };

        mockUpdaters.add(update);
        return {};
    },
}));

// ============================================================
// 10. MOCK — REVENUE SUMMARY
// ============================================================
vi.mock('@/components/admin/reports/RevenueSummary.svelte', () => ({
    default: (anchor: Node, props: { totalRevenue: number }) => {
        const el = document.createElement('div');
        el.setAttribute('data-testid', 'revenue-summary');
        el.setAttribute('data-total', String(props.totalRevenue));
        anchor.parentNode?.insertBefore(el, anchor);
        return {};
    },
}));

// ============================================================
// 11. MOCK — REVENUE CHART
// ============================================================
vi.mock('@/components/admin/reports/RevenueChart.svelte', () => ({
    default: (anchor: Node, props: { monthly_revenue: any[] }) => {
        const el = document.createElement('div');
        el.setAttribute('data-testid', 'revenue-chart');
        el.setAttribute('data-points', String(props.monthly_revenue?.length ?? 0));
        anchor.parentNode?.insertBefore(el, anchor);
        return {};
    },
}));

// ============================================================
// 12. MOCK — REVENUE TABLE
// ============================================================
vi.mock('@/components/admin/reports/RevenueTable.svelte', () => ({
    default: (anchor: Node, props: { transactions: any[] }) => {
        const el = document.createElement('div');
        el.setAttribute('data-testid', 'revenue-table');
        el.setAttribute('data-transactions', String(props.transactions?.length ?? 0));
        anchor.parentNode?.insertBefore(el, anchor);
        return {};
    },
}));

// ============================================================
// 13. STATIC IMPORT
// ============================================================
import { getRevenueReport } from '@/api/admin';

// ============================================================
// 14. HELPERS
// ============================================================
async function syncUI(): Promise<void> {
    await tick();
    mockUpdaters.forEach((fn) => fn());
}

// ============================================================
// 15. FIXTURES
// ============================================================
const makeTransactions = (count = 1) =>
    Array.from({ length: count }, (_, i) => ({
        kode: `TRX-${i + 1}`,
        tanggal: '2026-01-15',
        client: `Client ${i + 1}`,
        mitra: `Mitra ${i + 1}`,
        paket: `Paket ${i + 1}`,
        total: 1000000 * (i + 1),
        status: 'PAID',
    }));

const makeMonthlyRevenue = (count = 3) =>
    Array.from({ length: count }, (_, i) => ({
        bulan: `2026-0${i + 1}`,
        total: 5000000 * (i + 1),
    }));

const makeProps = (overrides = {}) => ({
    data: {
        total_revenue: 15000000,
        transactions: makeTransactions(2),
    },
    monthly_revenue: makeMonthlyRevenue(6),
    filters: {
        start_date: '2026-01-01',
        end_date: '2026-06-30',
    },
    ...overrides,
});

const renderPage = (props = makeProps()) =>
    render(Index, { props });

// ============================================================
// 16. TEST SUITE
// ============================================================
describe('Admin Reports — Revenue Index Page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        mockUpdaters.clear();
    });

    // ----------------------------------------------------------
    // 16.1 RENDERING — Header & Tabs
    // ----------------------------------------------------------
    describe('Rendering — Header', () => {
        it('menampilkan judul & deskripsi halaman', () => {
            renderPage();

            expect(
                screen.getByRole('heading', { level: 1, name: 'Laporan Pendapatan' }),
            ).toBeInTheDocument();
            expect(
                screen.getByText('Pantau performa keuangan dan tren bisnis Anda.'),
            ).toBeInTheDocument();
        });

        it('merender ReportTabs dengan active="pendapatan"', () => {
            renderPage();

            expect(screen.getByTestId('report-tabs')).toHaveAttribute(
                'data-active',
                'pendapatan',
            );
        });
    });

    // ----------------------------------------------------------
    // 16.2 RENDERING — Export Buttons
    // ----------------------------------------------------------
    describe('Rendering — Export Buttons', () => {
        it('merender ExportButtons dengan URL dari startDate & endDate', () => {
            renderPage();

            const exportBtns = screen.getByTestId('export-buttons');
            expect(exportBtns.getAttribute('data-pdf-url')).toBe(
                '/admin/reports/export-pdf?start_date=2026-01-01&end_date=2026-06-30',
            );
            expect(exportBtns.getAttribute('data-excel-url')).toBe(
                '/admin/reports/export-excel?start_date=2026-01-01&end_date=2026-06-30',
            );
        });
    });

    // ----------------------------------------------------------
    // 16.3 RENDERING — Sub Components
    // ----------------------------------------------------------
    describe('Rendering — Sub Components', () => {
        it('merender ReportFilter', () => {
            renderPage();
            expect(screen.getByTestId('report-filter')).toBeInTheDocument();
        });

        it('meneruskan total_revenue ke RevenueSummary', () => {
            renderPage();
            expect(screen.getByTestId('revenue-summary')).toHaveAttribute(
                'data-total',
                '15000000',
            );
        });

        it('meneruskan monthly_revenue ke RevenueChart', () => {
            renderPage();
            expect(screen.getByTestId('revenue-chart')).toHaveAttribute(
                'data-points',
                '6',
            );
        });

        it('meneruskan transactions ke RevenueTable', () => {
            renderPage();
            expect(screen.getByTestId('revenue-table')).toHaveAttribute(
                'data-transactions',
                '2',
            );
        });
    });

    // ----------------------------------------------------------
    // 16.4 INTERACTION — Apply Filter
    // ----------------------------------------------------------
    describe('Interaction — Apply Filter', () => {
        it('memanggil getRevenueReport dengan startDate & endDate saat ini', async () => {
            renderPage();

            await fireEvent.click(screen.getByRole('button', { name: 'Apply' }));

            expect(getRevenueReport).toHaveBeenCalledOnce();
            expect(getRevenueReport).toHaveBeenCalledWith(
                { start_date: '2026-01-01', end_date: '2026-06-30' },
                { preserveState: true },
            );
        });

        it('memanggil getRevenueReport dengan tanggal yang diubah user', async () => {
            renderPage();

            const startInput = screen.getByLabelText('Start Date');
            const endInput = screen.getByLabelText('End Date');

            await fireEvent.input(startInput, { target: { value: '2026-02-01' } });
            await fireEvent.input(endInput, { target: { value: '2026-03-31' } });
            await syncUI();

            await fireEvent.click(screen.getByRole('button', { name: 'Apply' }));

            expect(getRevenueReport).toHaveBeenCalledWith(
                { start_date: '2026-02-01', end_date: '2026-03-31' },
                { preserveState: true },
            );
        });
    });

    // ----------------------------------------------------------
    // 16.5 REACTIVITY — Export URL Update
    // ----------------------------------------------------------
    describe('Reactivity — Export URL Update', () => {
        it('URL export berubah saat startDate/endDate diubah', async () => {
            renderPage();

            const exportBtns = screen.getByTestId('export-buttons');
            expect(exportBtns.getAttribute('data-pdf-url')).toContain(
                'start_date=2026-01-01',
            );

            const startInput = screen.getByLabelText('Start Date');
            const endInput = screen.getByLabelText('End Date');

            await fireEvent.input(startInput, { target: { value: '2026-03-01' } });
            await fireEvent.input(endInput, { target: { value: '2026-09-30' } });
            await syncUI();

            expect(exportBtns.getAttribute('data-pdf-url')).toContain(
                'start_date=2026-03-01',
            );
            expect(exportBtns.getAttribute('data-pdf-url')).toContain(
                'end_date=2026-09-30',
            );
            expect(exportBtns.getAttribute('data-excel-url')).toContain(
                'start_date=2026-03-01',
            );
        });
    });

    // ----------------------------------------------------------
    // 16.6 EDGE CASE
    // ----------------------------------------------------------
    describe('Edge Cases', () => {
        it('handle transactions kosong', () => {
            renderPage(
                makeProps({
                    data: { total_revenue: 0, transactions: [] },
                }),
            );

            expect(screen.getByTestId('revenue-table')).toHaveAttribute(
                'data-transactions',
                '0',
            );
        });

        it('handle monthly_revenue kosong', () => {
            renderPage(makeProps({ monthly_revenue: [] }));

            expect(screen.getByTestId('revenue-chart')).toHaveAttribute(
                'data-points',
                '0',
            );
        });

        it('handle total_revenue 0', () => {
            renderPage(
                makeProps({
                    data: { total_revenue: 0, transactions: [] },
                }),
            );

            expect(screen.getByTestId('revenue-summary')).toHaveAttribute(
                'data-total',
                '0',
            );
        });
    });
});