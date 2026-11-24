/**
 * ============================================================
 * Admin Reports — Mitra Performance Page — Component Test
 * ============================================================
 *
 * Fokus pengujian:
 *   1. Isolation & Mocking  → Inertia, API getMitraReport, menu,
 *                             & child components di-mock anchor-aware.
 *   2. Props & Rendering    → header, export buttons URL, tabs,
 *                             filter, summary, table.
 *   3. Interaction          → apply filter → getMitraReport.
 *   4. Reactivity           → URL export buttons berubah saat startDate
 *                             atau endDate diubah.
 * ============================================================
 */

import { render, screen, fireEvent } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { tick } from 'svelte';
import Mitra from '../Mitra.svelte';

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
    getMitraReport: vi.fn(),
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
// 10. MOCK — MITRA SUMMARY
// ============================================================
vi.mock('@/components/admin/reports/MitraSummary.svelte', () => ({
    default: (
        anchor: Node,
        props: { totalMitra: number; totalTrip: number },
    ) => {
        const el = document.createElement('div');
        el.setAttribute('data-testid', 'mitra-summary');
        el.setAttribute('data-total-mitra', String(props.totalMitra));
        el.setAttribute('data-total-trip', String(props.totalTrip));
        anchor.parentNode?.insertBefore(el, anchor);
        return {};
    },
}));

// ============================================================
// 11. MOCK — MITRA TABLE
// ============================================================
vi.mock('@/components/admin/reports/MitraTable.svelte', () => ({
    default: (anchor: Node, props: { mitras: any[] }) => {
        const el = document.createElement('div');
        el.setAttribute('data-testid', 'mitra-table');
        el.setAttribute('data-mitras-count', String(props.mitras?.length ?? 0));
        anchor.parentNode?.insertBefore(el, anchor);
        return {};
    },
}));

// ============================================================
// 12. STATIC IMPORT
// ============================================================
import { getMitraReport } from '@/api/admin';

// ============================================================
// 13. HELPERS
// ============================================================
async function syncUI(): Promise<void> {
    await tick();
    mockUpdaters.forEach((fn) => fn());
}

// ============================================================
// 14. FIXTURES
// ============================================================
const makeMitras = (count = 1) =>
    Array.from({ length: count }, (_, i) => ({
        id: i + 1,
        name: `Mitra ${i + 1}`,
        email: `mitra${i + 1}@test.com`,
        total_trip: 5 + i,
        total_revenue: 5000000 * (i + 1),
    }));

const makeProps = (overrides = {}) => ({
    data: {
        total_mitra: 3,
        total_trip_completed: 15,
        mitras: makeMitras(3),
    },
    filters: {
        start_date: '2026-01-01',
        end_date: '2026-06-30',
    },
    ...overrides,
});

const renderPage = (props = makeProps()) => render(Mitra, { props });

// ============================================================
// 15. TEST SUITE
// ============================================================
describe('Admin Reports — Mitra Performance Page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        mockUpdaters.clear();
    });

    // ----------------------------------------------------------
    // 15.1 RENDERING — Header & Tabs
    // ----------------------------------------------------------
    describe('Rendering — Header', () => {
        it('menampilkan judul & deskripsi halaman', () => {
            renderPage();

            expect(
                screen.getByRole('heading', {
                    level: 1,
                    name: 'Kinerja Mitra',
                }),
            ).toBeInTheDocument();
            expect(
                screen.getByText(/Evaluasi performa pemandu wisata Anda/i),
            ).toBeInTheDocument();
        });

        it('merender ReportTabs dengan active="mitra"', () => {
            renderPage();

            expect(screen.getByTestId('report-tabs')).toHaveAttribute(
                'data-active',
                'mitra',
            );
        });
    });

    // ----------------------------------------------------------
    // 15.2 RENDERING — Export Buttons
    // ----------------------------------------------------------
    describe('Rendering — Export Buttons', () => {
        it('merender ExportButtons dengan URL dari startDate & endDate', () => {
            renderPage();

            const exportBtns = screen.getByTestId('export-buttons');
            expect(exportBtns.getAttribute('data-pdf-url')).toBe(
                '/admin/reports/mitra/export-pdf?start_date=2026-01-01&end_date=2026-06-30',
            );
            expect(exportBtns.getAttribute('data-excel-url')).toBe(
                '/admin/reports/mitra/export-excel?start_date=2026-01-01&end_date=2026-06-30',
            );
        });
    });

    // ----------------------------------------------------------
    // 15.3 RENDERING — Sub Components
    // ----------------------------------------------------------
    describe('Rendering — Sub Components', () => {
        it('merender ReportFilter', () => {
            renderPage();
            expect(screen.getByTestId('report-filter')).toBeInTheDocument();
        });

        it('meneruskan total_mitra & total_trip_completed ke MitraSummary', () => {
            renderPage();

            const summary = screen.getByTestId('mitra-summary');
            expect(summary).toHaveAttribute('data-total-mitra', '3');
            expect(summary).toHaveAttribute('data-total-trip', '15');
        });

        it('meneruskan mitras ke MitraTable', () => {
            renderPage();
            expect(screen.getByTestId('mitra-table')).toHaveAttribute(
                'data-mitras-count',
                '3',
            );
        });
    });

    // ----------------------------------------------------------
    // 15.4 INTERACTION — Apply Filter
    // ----------------------------------------------------------
    describe('Interaction — Apply Filter', () => {
        it('memanggil getMitraReport dengan startDate & endDate saat ini', async () => {
            renderPage();

            await fireEvent.click(
                screen.getByRole('button', { name: 'Apply' }),
            );

            expect(getMitraReport).toHaveBeenCalledOnce();
            expect(getMitraReport).toHaveBeenCalledWith(
                { start_date: '2026-01-01', end_date: '2026-06-30' },
                { preserveState: true },
            );
        });

        it('memanggil getMitraReport dengan tanggal yang diubah user', async () => {
            renderPage();

            await fireEvent.input(screen.getByLabelText('Start Date'), {
                target: { value: '2026-02-01' },
            });
            await fireEvent.input(screen.getByLabelText('End Date'), {
                target: { value: '2026-03-31' },
            });
            await syncUI();

            await fireEvent.click(
                screen.getByRole('button', { name: 'Apply' }),
            );

            expect(getMitraReport).toHaveBeenCalledWith(
                { start_date: '2026-02-01', end_date: '2026-03-31' },
                { preserveState: true },
            );
        });
    });

    // ----------------------------------------------------------
    // 15.5 REACTIVITY — Export URL Update
    // ----------------------------------------------------------
    describe('Reactivity — Export URL Update', () => {
        it('URL export berubah saat startDate/endDate diubah', async () => {
            renderPage();

            const exportBtns = screen.getByTestId('export-buttons');
            expect(exportBtns.getAttribute('data-pdf-url')).toContain(
                'start_date=2026-01-01',
            );

            await fireEvent.input(screen.getByLabelText('Start Date'), {
                target: { value: '2026-03-01' },
            });
            await fireEvent.input(screen.getByLabelText('End Date'), {
                target: { value: '2026-09-30' },
            });
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
    // 15.6 EDGE CASE
    // ----------------------------------------------------------
    describe('Edge Cases', () => {
        it('handle mitras kosong', () => {
            renderPage(
                makeProps({
                    data: {
                        total_mitra: 0,
                        total_trip_completed: 0,
                        mitras: [],
                    },
                }),
            );

            expect(screen.getByTestId('mitra-table')).toHaveAttribute(
                'data-mitras-count',
                '0',
            );
        });

        it('handle total 0', () => {
            renderPage(
                makeProps({
                    data: {
                        total_mitra: 0,
                        total_trip_completed: 0,
                        mitras: [],
                    },
                }),
            );

            const summary = screen.getByTestId('mitra-summary');
            expect(summary).toHaveAttribute('data-total-mitra', '0');
            expect(summary).toHaveAttribute('data-total-trip', '0');
        });

        it('handle tanggal sama (start = end)', async () => {
            renderPage(
                makeProps({
                    filters: {
                        start_date: '2026-01-01',
                        end_date: '2026-01-01',
                    },
                }),
            );

            await fireEvent.click(
                screen.getByRole('button', { name: 'Apply' }),
            );

            expect(getMitraReport).toHaveBeenCalledWith(
                { start_date: '2026-01-01', end_date: '2026-01-01' },
                { preserveState: true },
            );
        });
    });
});
