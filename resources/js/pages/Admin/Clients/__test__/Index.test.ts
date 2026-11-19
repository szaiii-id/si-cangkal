/**
 * ============================================================
 * Admin Clients — Index Page — Component Test
 * ============================================================
 *
 * Fokus pengujian (sesuai ketentuan):
 *   1. Isolation & Mocking  → Inertia, API getClients, menu, formatDate,
 *                             & child components di-mock (anchor-aware).
 *   2. Props & Rendering    → header + total count, tabel row per client,
 *                             kolom username, kolom tanggal, mode read-only.
 *   3. Interaction          → search debounce, clear search, EmptyState.
 *   4. Reactivity           → search state → EmptyState context berubah.
 *
 * Strategi:
 *   - Anchor-aware mock untuk DashboardLayout, PageHeader, SearchInput,
 *     EmptyState, Pagination (insertBefore anchor.parentNode).
 *   - `mockUpdaters` + `syncUI()` memastikan DOM mencerminkan state.
 *   - A11y-first query: `getByRole('table')`, `getByRole('columnheader')`,
 *     `getByRole('link')`, `getByPlaceholderText`.
 *   - Tabel dirender langsung oleh komponen (tidak di-mock) sehingga
 *     query row/cell natural — tidak perlu data-testid.
 * ============================================================
 */

import { render, screen, fireEvent, within } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
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
    getClients: vi.fn(),
}));

// ============================================================
// 4. MOCK — MENUS
// ============================================================
vi.mock('@/utils/menus', () => ({
    adminMenu: [],
}));

// ============================================================
// 5. MOCK — FORMAT DATE (deterministik)
// ============================================================
vi.mock('@/utils/formatDate', () => ({
    formatDate: vi.fn((iso: string) => {
        const d = new Date(iso);
        return d.toLocaleDateString('id-ID', {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
        });
    }),
}));

// ============================================================
// 6. MOCK — DASHBOARD LAYOUT (anchor-aware, pass-through children)
// ============================================================
vi.mock('@/components/layout/DashboardLayout.svelte', () => ({
    default: (anchor: Node, props: { children?: (anchor: Node) => void }) => {
        props.children?.(anchor);
        return {};
    },
}));

// ============================================================
// 7. MOCK — PAGE HEADER (render title/desc/actions snippet)
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
// 8. MOCK — SEARCH INPUT (reactive via mockUpdaters)
// ============================================================
vi.mock('@/global/SearchInput.svelte', () => ({
    default: (
        anchor: Node,
        props: {
            value: string;
            placeholder?: string;
            oninput?: (val: string) => void;
            onclear?: () => void;
        },
    ) => {
        const wrapper = document.createElement('div');
        const input = document.createElement('input');
        input.type = 'search';
        input.placeholder = props.placeholder ?? '';
        input.value = props.value ?? '';
        input.oninput = (e) => {
            const val = (e.target as HTMLInputElement).value;
            props.value = val;
            props.oninput?.(val);
        };

        const clearBtn = document.createElement('button');
        clearBtn.type = 'button';
        clearBtn.textContent = 'Hapus Pencarian';
        clearBtn.onclick = () => {
            props.value = '';
            props.onclear?.();
        };

        wrapper.append(input, clearBtn);
        anchor.parentNode?.insertBefore(wrapper, anchor);

        const update = () => {
            input.value = props.value ?? '';
        };
        mockUpdaters.add(update);
        return {};
    },
}));

// ============================================================
// 9. MOCK — EMPTY STATE (reactive, conditional clear button)
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
// 10. MOCK — PAGINATION (statis)
// ============================================================
vi.mock('@/global/Pagination.svelte', () => ({
    default: (anchor: Node, props: { meta: { total: number }; label: string }) => {
        const el = document.createElement('div');
        el.setAttribute('data-testid', 'pagination');
        el.textContent = `Total ${props.meta.total} ${props.label}`;
        anchor.parentNode?.insertBefore(el, anchor);
        return {};
    },
}));

// ============================================================
// 11. STATIC IMPORT (setelah vi.mock)
// ============================================================
import { getClients } from '@/api/admin';
import { formatDate } from '@/utils/formatDate';

// ============================================================
// 12. HELPERS
// ============================================================
async function syncUI(): Promise<void> {
    await tick();
    mockUpdaters.forEach((fn) => fn());
}

// ============================================================
// 13. FIXTURES
// ============================================================
const makeClient = (overrides = {}) => ({
    id: 1,
    name: 'Client Satu',
    username: 'clientsatu',
    email: 'client1@test.com',
    created_at: '2026-01-15T00:00:00Z',
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

const SAMPLE_CLIENTS = [
    makeClient({ id: 1, name: 'Budi Santoso', username: 'budisan', email: 'budi@test.com' }),
    makeClient({ id: 2, name: 'Siti Aminah', username: 'sitin', email: 'siti@test.com' }),
    makeClient({ id: 3, name: 'Andi Wijaya', username: null, email: 'andi@test.com' }),
];

// ============================================================
// 14. TEST SUITE
// ============================================================
describe('Admin Clients — Index Page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        mockUpdaters.clear();
        vi.useFakeTimers();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    // ----------------------------------------------------------
    // 14.1 RENDERING — Header
    // ----------------------------------------------------------
    describe('Rendering — Header', () => {
        it('menampilkan judul & deskripsi halaman', () => {
            render(Index, {
                clients: makePagination(SAMPLE_CLIENTS),
                filters: { search: '', per_page: 15 },
            });

            expect(screen.getByRole('heading', { level: 1, name: 'Daftar Client' })).toBeInTheDocument();
            expect(
                screen.getByText(/Client yang mendaftar mandiri\. Read-only/i),
            ).toBeInTheDocument();
        });

        it('menampilkan total client terdaftar di actions snippet', () => {
            render(Index, {
                clients: makePagination(SAMPLE_CLIENTS),
                filters: { search: '', per_page: 15 },
            });

            expect(screen.getByText('3')).toBeInTheDocument();
            expect(screen.getByText('Client Terdaftar')).toBeInTheDocument();
        });
    });

    // ----------------------------------------------------------
    // 14.2 RENDERING — Read-only Banner
    // ----------------------------------------------------------
    describe('Rendering — Read-only Banner', () => {
        it('menampilkan banner "Mode Read-only"', () => {
            render(Index, {
                clients: makePagination(SAMPLE_CLIENTS),
                filters: { search: '', per_page: 15 },
            });
            expect(screen.getByText('Mode Read-only')).toBeInTheDocument();
        });
    });

    // ----------------------------------------------------------
    // 14.3 RENDERING — Table
    // ----------------------------------------------------------
    describe('Rendering — Table', () => {
        it('merender kolom Client, Username, Terdaftar', () => {
            render(Index, {
                clients: makePagination(SAMPLE_CLIENTS),
                filters: { search: '', per_page: 15 },
            });

            expect(screen.getByRole('columnheader', { name: 'Client' })).toBeInTheDocument();
            expect(screen.getByRole('columnheader', { name: 'Username' })).toBeInTheDocument();
            expect(screen.getByRole('columnheader', { name: 'Terdaftar' })).toBeInTheDocument();
        });

        it('merender satu row per client', () => {
            render(Index, {
                clients: makePagination(SAMPLE_CLIENTS),
                filters: { search: '', per_page: 15 },
            });

            // Header row + 3 body row = 4 total <tr>
            expect(screen.getAllByRole('row')).toHaveLength(4);
        });

        it('menampilkan nama, email, username client dengan benar', () => {
            render(Index, {
                clients: makePagination(SAMPLE_CLIENTS),
                filters: { search: '', per_page: 15 },
            });

            const rows = screen.getAllByRole('row').slice(1); // skip header

            expect(within(rows[0]).getByText('Budi Santoso')).toBeInTheDocument();
            expect(within(rows[0]).getByText('budi@test.com')).toBeInTheDocument();
            expect(within(rows[0]).getByText('budisan')).toBeInTheDocument();

            expect(within(rows[1]).getByText('Siti Aminah')).toBeInTheDocument();
            expect(within(rows[1]).getByText('siti@test.com')).toBeInTheDocument();
            expect(within(rows[1]).getByText('sitin')).toBeInTheDocument();
        });

        it('menampilkan "-" italic untuk username null', () => {
            render(Index, {
                clients: makePagination(SAMPLE_CLIENTS),
                filters: { search: '', per_page: 15 },
            });

            const andiRow = screen.getByText('Andi Wijaya').closest('tr')!;
            const dash = within(andiRow).getByText('-');
            expect(dash).toHaveClass('italic');
        });

        it('menampilkan inisial pertama nama di avatar', () => {
            render(Index, {
                clients: makePagination(SAMPLE_CLIENTS),
                filters: { search: '', per_page: 15 },
            });

            expect(screen.getByText('B')).toBeInTheDocument();
            expect(screen.getByText('S')).toBeInTheDocument();
            expect(screen.getByText('A')).toBeInTheDocument();
        });

        it('memanggil formatDate untuk setiap client', () => {
            render(Index, {
                clients: makePagination(SAMPLE_CLIENTS),
                filters: { search: '', per_page: 15 },
            });

            expect(formatDate).toHaveBeenCalledTimes(3);
            expect(formatDate).toHaveBeenCalledWith('2026-01-15T00:00:00Z');
        });
    });

    // ----------------------------------------------------------
    // 14.4 RENDERING — Empty State
    // ----------------------------------------------------------
    describe('Rendering — EmptyState', () => {
        it('menampilkan EmptyState default tanpa filter', () => {
            render(Index, {
                clients: makePagination([]),
                filters: { search: '', per_page: 15 },
            });

            const emptyState = screen.getByTestId('empty-state');
            expect(emptyState).toHaveTextContent('Belum ada client');
            expect(emptyState).toHaveTextContent(
                'Client yang mendaftar mandiri akan muncul di sini.',
            );
            expect(screen.queryByRole('button', { name: 'Reset Filter' })).not.toBeInTheDocument();
        });

        it('menampilkan EmptyState pencarian saat ada filter aktif', () => {
            render(Index, {
                clients: makePagination([]),
                filters: { search: 'tidakada', per_page: 15 },
            });

            const emptyState = screen.getByTestId('empty-state');
            expect(emptyState).toHaveTextContent('Tidak ada hasil ditemukan');
            expect(emptyState).toHaveTextContent(
                'Tidak ditemukan client dengan kata kunci "tidakada".',
            );
            expect(screen.getByRole('button', { name: 'Reset Filter' })).toBeInTheDocument();
        });

        it('TIDAK merender table & pagination saat empty', () => {
            render(Index, {
                clients: makePagination([]),
                filters: { search: '', per_page: 15 },
            });

            expect(screen.queryByRole('table')).not.toBeInTheDocument();
            expect(screen.queryByTestId('pagination')).not.toBeInTheDocument();
        });
    });

    // ----------------------------------------------------------
    // 14.5 INTERACTION — Search Debounce
    // ----------------------------------------------------------
    describe('Interaction — Search Debounce', () => {
        it('tidak memanggil getClients sebelum 300ms', async () => {
            render(Index, {
                clients: makePagination(SAMPLE_CLIENTS),
                filters: { search: '', per_page: 15 },
            });

            const input = screen.getByPlaceholderText(/Cari nama, email, atau username/i);
            await fireEvent.input(input, { target: { value: 'budi' } });

            expect(getClients).not.toHaveBeenCalled();
        });

        it('memanggil getClients setelah 300ms dengan payload benar', async () => {
            render(Index, {
                clients: makePagination(SAMPLE_CLIENTS),
                filters: { search: '', per_page: 15 },
            });

            const input = screen.getByPlaceholderText(/Cari nama, email, atau username/i);
            await fireEvent.input(input, { target: { value: 'budi' } });

            await vi.advanceTimersByTimeAsync(299);
            expect(getClients).not.toHaveBeenCalled();

            await vi.advanceTimersByTimeAsync(1);
            expect(getClients).toHaveBeenCalledOnce();
            expect(getClients).toHaveBeenCalledWith(
                { search: 'budi', per_page: 15 },
                { preserveState: true, preserveScroll: true, replace: true },
            );
        });

        it('debounce meringkas panggilan saat user mengetik cepat', async () => {
            render(Index, {
                clients: makePagination(SAMPLE_CLIENTS),
                filters: { search: '', per_page: 15 },
            });

            const input = screen.getByPlaceholderText(/Cari nama, email, atau username/i);
            await fireEvent.input(input, { target: { value: 'b' } });
            await fireEvent.input(input, { target: { value: 'bu' } });
            await fireEvent.input(input, { target: { value: 'bud' } });
            await fireEvent.input(input, { target: { value: 'budi' } });

            await vi.advanceTimersByTimeAsync(300);

            expect(getClients).toHaveBeenCalledOnce();
            expect(getClients).toHaveBeenCalledWith(
                expect.objectContaining({ search: 'budi' }),
                expect.any(Object),
            );
        });
    });

    // ----------------------------------------------------------
    // 14.6 INTERACTION — Clear Search
    // ----------------------------------------------------------
    describe('Interaction — Clear Search', () => {
        it('klik clear langsung memanggil getClients tanpa debounce', async () => {
            render(Index, {
                clients: makePagination(SAMPLE_CLIENTS),
                filters: { search: 'admin', per_page: 15 },
            });

            await fireEvent.click(screen.getByRole('button', { name: 'Hapus Pencarian' }));

            expect(getClients).toHaveBeenCalledOnce();
            expect(getClients).toHaveBeenCalledWith(
                { search: '', per_page: 15 },
                { preserveState: true, preserveScroll: true, replace: true },
            );
        });

        it('klik "Reset Filter" di EmptyState memanggil clearSearch', async () => {
            render(Index, {
                clients: makePagination([]),
                filters: { search: 'tidakada', per_page: 15 },
            });

            await fireEvent.click(screen.getByRole('button', { name: 'Reset Filter' }));

            expect(getClients).toHaveBeenCalledWith(
                { search: '', per_page: 15 },
                { preserveState: true, preserveScroll: true, replace: true },
            );
        });
    });

    // ----------------------------------------------------------
    // 14.7 REACTIVITY — Search state → EmptyState context
    // ----------------------------------------------------------
    describe('Reactivity — Search Context', () => {
        it('EmptyState berubah dari "Belum ada client" → "Tidak ada hasil" saat search diisi', async () => {
            render(Index, {
                clients: makePagination([]),
                filters: { search: '', per_page: 15 },
            });

            // Awal: default
            expect(screen.getByTestId('empty-state')).toHaveTextContent('Belum ada client');

            // Ketik search
            const input = screen.getByPlaceholderText(/Cari nama, email, atau username/i);
            await fireEvent.input(input, { target: { value: 'xyz' } });
            await syncUI();

            // Context berubah
            expect(screen.getByTestId('empty-state')).toHaveTextContent('Tidak ada hasil ditemukan');
            expect(screen.getByTestId('empty-state')).toHaveTextContent(
                'Tidak ditemukan client dengan kata kunci "xyz".',
            );
        });
    });

    // ----------------------------------------------------------
    // 14.8 A11Y
    // ----------------------------------------------------------
    describe('Accessibility', () => {
        it('table punya semantic columnheader', () => {
            render(Index, {
                clients: makePagination(SAMPLE_CLIENTS),
                filters: { search: '', per_page: 15 },
            });

            const headers = screen.getAllByRole('columnheader');
            expect(headers).toHaveLength(3);
        });

        it('search input punya placeholder deskriptif', () => {
            render(Index, {
                clients: makePagination(SAMPLE_CLIENTS),
                filters: { search: '', per_page: 15 },
            });

            expect(
                screen.getByPlaceholderText(/Cari nama, email, atau username/i),
            ).toBeInTheDocument();
        });
    });
});