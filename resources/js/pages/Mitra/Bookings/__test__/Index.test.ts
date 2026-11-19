/**
 * ============================================================
 * Mitra Bookings — Index Page — Component Test
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
    getBookings: vi.fn(),
}));

// ============================================================
// 2. MOCK DEPENDENCIES
// ============================================================
vi.mock('@/api/mitra', () => mockApi);

vi.mock('@/utils/enums', () => ({
    bookingStatusBadge: vi.fn((s: string) => `badge-${s}`),
    bookingStatusLabel: vi.fn((s: string) => s),
    bookingStatusShort: vi.fn((s: string) => s),
}));

vi.mock('@/utils/formatCurrency', () => ({
    formatCurrency: vi.fn((v: string | number) => `Rp ${v}`),
}));

vi.mock('@/utils/formatDate', () => ({
    formatDate: vi.fn((d: string) => `Date: ${d}`),
    formatDateTime: vi.fn((d: string) => `DateTime: ${d}`),
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
            
            el.querySelector('input')!.oninput = (e) => props.onSearch((e.target as HTMLInputElement).value);
            el.querySelector('[data-action="clear"]')!.onclick = () => props.onClear();
            el.querySelector('[data-action="status-pending"]')!.onclick = () => props.onStatusChange('pending_payment');
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
    default: (anchor: Node, props: any) => {
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
    mockUpdaters.forEach(fn => fn());
}

// ============================================================
// 5. FIXTURES
// ============================================================
const makeBooking = (overrides = {}) => ({
    id: 1,
    kode: 'BK-01',
    status: 'pending_payment',
    tanggal_keberangkatan: '2026-02-01',
    jumlah_peserta: 2,
    total_harga: '1500000',
    expired_at: null,
    created_at: '2026-01-01',
    package: { id: 1, nama: 'Paket Bali', kode: 'PKT-01' },
    client: { id: 10, name: 'Budi', email: 'budi@test.com' },
    ...overrides,
});

const makeProps = (overrides = {}) => ({
    bookings: {
        data: [makeBooking()],
        current_page: 1, last_page: 1, total: 1, per_page: 15, from: 1, to: 1, links: []
    },
    filters: { search: '', status: '', sort: 'created_at', direction: 'desc' },
    statusCounts: {
        pending_payment: 1,
        payment_verified: 0,
        confirmed: 0,
        in_progress: 0,
        completed: 0,
        cancelled: 0,
    },
    ...overrides,
});

// ============================================================
// 6. TEST SUITE
// ============================================================
describe('Mitra Bookings — Index Page', () => {
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
            expect(screen.getByTestId('page-header')).toHaveTextContent('Pesanan Masuk');
            expect(screen.getByTestId('filter-bar')).toBeInTheDocument();
        });

        it('merender EmptyState jika data kosong', () => {
            render(Index, {
                props: makeProps({
                    bookings: { data: [], total: 0, current_page: 1, last_page: 1, per_page: 15, from: 0, to: 0, links: [] },
                }),
            });
            expect(screen.getByTestId('empty-state')).toBeInTheDocument();
        });

        it('merender data booking dan Pagination jika ada', () => {
            render(Index, { props: makeProps() });
            
            // Cek data booking
            expect(screen.getByText('BK-01')).toBeInTheDocument();
            expect(screen.getByText('Paket Bali')).toBeInTheDocument();
            expect(screen.getByText('Budi — budi@test.com')).toBeInTheDocument();
            expect(screen.getByText('Rp 1500000')).toBeInTheDocument();
            expect(screen.getByText('2 peserta')).toBeInTheDocument();
            
            // Cek pagination
            expect(screen.getByTestId('pagination')).toBeInTheDocument();
        });

        it('merender link detail booking dengan href yang benar', () => {
            render(Index, { props: makeProps() });
            const link = screen.getByText('BK-01').closest('a');
            expect(link).toHaveAttribute('href', '/mitra/bookings/1');
        });
    });

    describe('Interaction — Filter & Search', () => {
        it('memanggil getBookings saat search debounce selesai', async () => {
            render(Index, { props: makeProps() });
            const input = screen.getByLabelText('Search');
            
            await fireEvent.input(input, { target: { value: 'budi' } });
            expect(mockApi.getBookings).not.toHaveBeenCalled();

            await vi.advanceTimersByTimeAsync(300);
            expect(mockApi.getBookings).toHaveBeenCalledWith(
                expect.objectContaining({ search: 'budi' }),
                expect.any(Object)
            );
        });

        it('memanggil getBookings saat filter status diubah', async () => {
            render(Index, { props: makeProps() });
            await fireEvent.click(screen.getByText('Pending'));
            
            expect(mockApi.getBookings).toHaveBeenCalledWith(
                expect.objectContaining({ status: 'pending_payment' }),
                expect.any(Object)
            );
        });

        it('memanggil getBookings saat tombol clear filter diklik', async () => {
            render(Index, { 
                props: makeProps({ 
                    filters: { search: 'budi', status: 'pending_payment', sort: 'created_at', direction: 'desc' } 
                }) 
            });
            
            await fireEvent.click(screen.getByText('Hapus Filter'));
            
            expect(mockApi.getBookings).toHaveBeenCalledWith(
                {},
                expect.any(Object)
            );
        });
    });
});