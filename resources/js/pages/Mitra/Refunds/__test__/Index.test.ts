/**
 * ============================================================
 * Mitra Refunds — Index Page — Component Test
 * ============================================================
 */

import { render, screen, fireEvent } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { tick } from 'svelte';
import type { MitraRefundIndexProps } from '@/types/props/mitra';
import Index from '../Index.svelte';

// ============================================================
// 1. HOISTED MOCK REGISTRY
// ============================================================
const mockUpdaters = vi.hoisted(() => new Set<() => void>());
const mockApi = vi.hoisted(() => ({
    getRefunds: vi.fn(),
    approveRefund: vi.fn(),
    rejectRefund: vi.fn(),
}));

// ============================================================
// 2. MOCK DEPENDENCIES
// ============================================================
vi.mock('@/api/mitra', () => mockApi);

vi.mock('@/utils/enums', () => ({
    refundStatusLabel: vi.fn((s: string) => s),
}));

vi.mock('@/utils/formatCurrency', () => ({
    formatCurrency: vi.fn((v: string | number) => `Rp ${v}`),
}));

vi.mock('@/utils/toast.svelte', () => ({
    showToast: vi.fn(),
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
        el.innerHTML = `<h1>${props.title}</h1><p>${props.description}</p>`;
        anchor.parentNode?.insertBefore(el, anchor);
        return {};
    },
}));

vi.mock('@/global/FilterBar.svelte', () => ({
    default: (anchor: Node, props: any) => {
        const el = document.createElement('div');
        el.setAttribute('data-testid', 'filter-bar');
        anchor.parentNode?.insertBefore(el, anchor);

        const update = () => {
            el.innerHTML = `
                <input type="text" aria-label="Search" value="${props.search || ''}" />
                <button type="button" data-action="clear">Hapus Filter</button>
                <button type="button" data-action="status-pending">Pending</button>
            `;

            (el.querySelector('input') as HTMLInputElement).oninput = (e) => props.onSearch((e.target as HTMLInputElement).value);
            (el.querySelector('[data-action="clear"]') as HTMLButtonElement).onclick = () => props.onClear();
            (el.querySelector('[data-action="status-pending"]') as HTMLButtonElement).onclick = () => props.onStatusChange('PENDING');
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

// ✅ FIX: Jangan render apa pun saat open=false, agar tidak duplikasi data-testid
vi.mock('@/global/ConfirmModal.svelte', () => ({
    default: (anchor: Node, props: any) => {
        const el = document.createElement('div');
        // data-testid hanya diset saat open (supaya unik & bisa di-query)
        anchor.parentNode?.insertBefore(el, anchor);

        const update = () => {
            el.innerHTML = '';
            if (!props.open) {
                el.removeAttribute('data-testid');
                return;
            }

            el.setAttribute('data-testid', 'confirm-modal');
            el.innerHTML = `
                <h2>${props.title}</h2>
                <p>${props.description || ''}</p>
                <button type="button" data-action="confirm">${props.confirmLabel}</button>
            `;
            (el.querySelector('button') as HTMLButtonElement).onclick = () => props.onConfirm();
        };

        update();
        mockUpdaters.add(update);
        return {};
    },
}));

vi.mock('@/components/mitra/refunds/RefundCard.svelte', () => ({
    default: (anchor: Node, props: any) => {
        const el = document.createElement('div');
        el.setAttribute('data-testid', 'refund-card');
        anchor.parentNode?.insertBefore(el, anchor);

        const update = () => {
            el.innerHTML = `
                <span>Refund ${props.refund.id}</span>
                <button type="button" data-action="approve">Approve</button>
                <button type="button" data-action="reject">Reject</button>
            `;

            (el.querySelector('[data-action="approve"]') as HTMLButtonElement).onclick = () => props.onApprove(props.refund);
            (el.querySelector('[data-action="reject"]') as HTMLButtonElement).onclick = () => props.onReject(props.refund);
        };

        update();
        mockUpdaters.add(update);
        return {};
    },
}));

// ============================================================
// 4. HELPERS
// ============================================================
async function syncUI() {
    await tick();
    mockUpdaters.forEach(fn => fn());
}

// ============================================================
// 5. FIXTURES
// ============================================================
const makeRefund = (overrides: Record<string, unknown> = {}) => ({
    id: 1,
    booking_id: 10,
    mitra_id: 1,
    xendit_refund_id: null,
    amount: '500000',
    currency: 'IDR',
    status: 'PENDING' as const,
    reason: 'CANCELLATION' as const,
    failure_code: null,
    catatan: null,
    meta: null,
    created_at: '2026-01-01T00:00:00Z',
    updated_at: '2026-01-01T00:00:00Z',
    booking: {
        id: 10,
        kode: 'BK-01',
        status: 'refund_requested',
        total_harga: '500000',
        client: { id: 100, name: 'Budi', email: 'budi@test.com' },
    },
    ...overrides,
});

const makeProps = (overrides: Record<string, unknown> = {}): MitraRefundIndexProps =>
    ({
        refunds: {
            data: [makeRefund()],
            current_page: 1,
            last_page: 1,
            total: 1,
            per_page: 15,
            from: 1,
            to: 1,
            links: [],
        },
        filters: { search: '', status: '' },
        statusCounts: { PENDING: 1, SUCCEEDED: 0, FAILED: 0, CANCELLED: 0 },
        ...overrides,
    }) as unknown as MitraRefundIndexProps;

// ============================================================
// 6. TEST SUITE
// ============================================================
describe('Mitra Refunds — Index Page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        mockUpdaters.clear();
        vi.useFakeTimers();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    describe('Rendering', () => {
        it('merender PageHeader dan FilterBar', () => {
            render(Index, { props: makeProps() });
            expect(screen.getByTestId('page-header')).toHaveTextContent('Pengembalian Dana (Refund)');
            expect(screen.getByTestId('filter-bar')).toBeInTheDocument();
        });

        it('merender EmptyState jika data kosong', () => {
            render(Index, {
                props: makeProps({
                    refunds: { data: [], total: 0, current_page: 1, last_page: 1, per_page: 15, from: 0, to: 0, links: [] },
                }),
            });
            expect(screen.getByTestId('empty-state')).toBeInTheDocument();
        });

        it('merender RefundCard dan Pagination jika ada data', () => {
            render(Index, { props: makeProps() });
            expect(screen.getByTestId('refund-card')).toBeInTheDocument();
            expect(screen.getByTestId('pagination')).toBeInTheDocument();
        });
    });

    describe('Interaction — Filter & Search', () => {
        it('memanggil getRefunds saat search debounce selesai', async () => {
            render(Index, { props: makeProps() });
            const input = screen.getByLabelText('Search');

            await fireEvent.input(input, { target: { value: 'budi' } });
            expect(mockApi.getRefunds).not.toHaveBeenCalled();

            await vi.advanceTimersByTimeAsync(300);
            expect(mockApi.getRefunds).toHaveBeenCalledWith(
                expect.objectContaining({ search: 'budi' }),
                expect.any(Object)
            );
        });

        it('memanggil getRefunds saat filter status diubah', async () => {
            render(Index, { props: makeProps() });
            await fireEvent.click(screen.getByText('Pending'));

            expect(mockApi.getRefunds).toHaveBeenCalledWith(
                expect.objectContaining({ status: 'PENDING' }),
                expect.any(Object)
            );
        });
    });

    describe('Interaction — Approve Flow', () => {
        it('membuka ConfirmModal saat tombol Approve di card diklik', async () => {
            render(Index, { props: makeProps() });

            await fireEvent.click(screen.getByText('Approve'));
            await syncUI();

            const modal = screen.getByTestId('confirm-modal');
            expect(modal).toHaveTextContent('Setujui Pengembalian?');
        });

        it('memanggil approveRefund saat modal dikonfirmasi', async () => {
            mockApi.approveRefund.mockImplementation((_id, _data, opts) => {
                opts.onSuccess();
                opts.onFinish();
            });

            render(Index, { props: makeProps() });
            await fireEvent.click(screen.getByText('Approve'));
            await syncUI();

            await fireEvent.click(screen.getByText('Ya, Setujui'));
            await syncUI();

            expect(mockApi.approveRefund).toHaveBeenCalledWith(
                1,
                { catatan: null },
                expect.any(Object)
            );
        });
    });

    describe('Interaction — Reject Flow', () => {
        it('membuka ConfirmModal saat tombol Reject di card diklik', async () => {
            render(Index, { props: makeProps() });

            await fireEvent.click(screen.getByText('Reject'));
            await syncUI();

            const modal = screen.getByTestId('confirm-modal');
            expect(modal).toHaveTextContent('Tolak Pengembalian?');
        });
    });
});