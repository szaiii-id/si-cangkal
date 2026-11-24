/**
 * ============================================================
 * Admin Trips — Overview Page — Component Test
 * ============================================================
 *
 * Halaman grid monitoring paket wisata untuk masuk ke trips.
 * Route: /admin/packages (atau /admin/trips/overview?)
 *
 * Fokus pengujian:
 *   1. Isolation & Mocking  → Inertia, API getPackages, menu,
 *                             formatCurrency, formatDurasi,
 *                             & child components di-mock.
 *   2. Props & Rendering    → grid card per package, cover image /
 *                             placeholder, kode, durasi, mitra, harga.
 *   3. Interaction          → search debounce, clear, klik card → trips.
 *   4. Reactivity           → EmptyState context search.
 * ============================================================
 */

import { render, screen, fireEvent } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import Overview from '../Overview.svelte';

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
    getPackages: vi.fn(),
}));

// ============================================================
// 4. MOCK — MENUS
// ============================================================
vi.mock('@/utils/menus', () => ({
    adminMenu: [],
}));

// ============================================================
// 5. MOCK — FORMAT HELPERS (deterministik)
// ============================================================
vi.mock('@/utils/formatCurrency', () => ({
    formatCurrency: vi.fn(
        (v: string | number) => `Rp ${Number(v).toLocaleString('id-ID')}`,
    ),
}));

vi.mock('@/utils/duration', () => ({
    formatDurasi: vi.fn(
        (hari: number, malam: number) => `${hari}H / ${malam}M`,
    ),
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
// 8. MOCK — SEARCH INPUT (reactive)
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
// 9. MOCK — EMPTY STATE
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
// 10. MOCK — PAGINATION
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
// 11. STATIC IMPORT (setelah vi.mock)
// ============================================================
import { getPackages } from '@/api/admin';
import { formatCurrency } from '@/utils/formatCurrency';
import { formatDurasi } from '@/utils/duration';

// ============================================================
// 13. FIXTURES
// ============================================================
const makePackage = (overrides = {}) => ({
    id: 1,
    kode: 'PKT-BALI-001',
    nama: 'Paket Bali 3H2M',
    slug: 'paket-bali-3h2m',
    deskripsi: 'Wisata Bali 3 hari 2 malam',
    kategori: 'open_trip',
    durasi_hari: 3,
    durasi_malam: 2,
    harga_per_orang: '750000',
    harga_per_rombongan: null,
    max_peserta: 10,
    timezone: 'Asia/Jakarta',
    cover_image: 'packages/bali.jpg',
    status: 'aktif',
    mitra_id: 100,
    mitra: { id: 100, name: 'PT Mitra Jaya' },
    created_at: '2026-01-01T00:00:00Z',
    updated_at: '2026-01-01T00:00:00Z',
    deleted_at: null,
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

const SAMPLE_PACKAGES = [
    makePackage({ id: 1, kode: 'PKT-001', nama: 'Paket Bali' }),
    makePackage({ id: 2, kode: 'PKT-002', nama: 'Paket Lombok' }),
    makePackage({
        id: 3,
        kode: 'PKT-003',
        nama: 'Paket Yogyakarta',
        cover_image: null,
        harga_per_rombongan: '5000000',
    }),
];

const renderPage = (
    packages = makePagination(SAMPLE_PACKAGES),
    filters = { search: '' },
) =>
    render(Overview, {
        props: { packages, filters },
    });

// ============================================================
// 14. TEST SUITE
// ============================================================
describe('Admin Trips — Overview Page', () => {
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
            renderPage();

            expect(
                screen.getByRole('heading', {
                    level: 1,
                    name: 'Monitoring Perjalanan',
                }),
            ).toBeInTheDocument();
            expect(
                screen.getByText(
                    /Pilih paket wisata untuk melihat jadwal perjalanan/i,
                ),
            ).toBeInTheDocument();
        });
    });

    // ----------------------------------------------------------
    // 14.2 RENDERING — Grid Card
    // ----------------------------------------------------------
    describe('Rendering — Grid Card', () => {
        it('merender 3 card untuk 3 paket', () => {
            renderPage();

            // Setiap card adalah <a> dengan href /admin/trips?package_id=...
            const cards = screen.getAllByRole('link');
            // 3 cards + 1 clear button (jika mock render button) → cari by href
            const cardLinks = cards.filter((el) =>
                el.getAttribute('href')?.startsWith('/admin/trips?package_id='),
            );
            expect(cardLinks).toHaveLength(3);
        });

        it('card punya href dengan package_id yang benar', () => {
            renderPage();

            const cardLinks = screen
                .getAllByRole('link')
                .filter((el) =>
                    el
                        .getAttribute('href')
                        ?.startsWith('/admin/trips?package_id='),
                );

            expect(cardLinks[0]).toHaveAttribute(
                'href',
                '/admin/trips?package_id=1',
            );
            expect(cardLinks[1]).toHaveAttribute(
                'href',
                '/admin/trips?package_id=2',
            );
            expect(cardLinks[2]).toHaveAttribute(
                'href',
                '/admin/trips?package_id=3',
            );
        });

        it('menampilkan nama paket & kode', () => {
            renderPage();

            expect(screen.getByText('Paket Bali')).toBeInTheDocument();
            expect(screen.getByText('PKT-001')).toBeInTheDocument();
            expect(screen.getByText('Paket Lombok')).toBeInTheDocument();
            expect(screen.getByText('PKT-002')).toBeInTheDocument();
        });

        it('menampilkan nama mitra', () => {
            renderPage();

            const mitraLabels = screen.getAllByText('PT Mitra Jaya');
            expect(mitraLabels.length).toBe(3);
        });

        it('menampilkan placeholder "Mitra tidak diketahui" saat mitra null', () => {
            renderPage(
                makePagination([
                    makePackage({ id: 1, nama: 'Paket X', mitra: null }),
                ]),
            );

            expect(
                screen.getByText('Mitra tidak diketahui'),
            ).toBeInTheDocument();
        });

        it('merender cover image saat cover_image ada', () => {
            renderPage(
                makePagination([
                    makePackage({
                        id: 1,
                        nama: 'Paket With Image',
                        cover_image: 'packages/bali.jpg',
                    }),
                ]),
            );

            const img = screen.getByAltText(
                'Paket With Image',
            ) as HTMLImageElement;
            expect(img).toBeInTheDocument();
            expect(img.getAttribute('src')).toBe('/storage/packages/bali.jpg');
        });

        it('merender SVG placeholder saat cover_image null', () => {
            renderPage(
                makePagination([
                    makePackage({
                        id: 1,
                        nama: 'Paket No Image',
                        cover_image: null,
                    }),
                ]),
            );

            expect(
                screen.queryByAltText('Paket No Image'),
            ).not.toBeInTheDocument();
            // SVG placeholder ada di DOM (tidak ada alt, ada <svg>)
            expect(
                document.querySelectorAll(
                    'a[href^="/admin/trips?package_id="] svg',
                ).length,
            ).toBeGreaterThan(0);
        });
    });

    // ----------------------------------------------------------
    // 14.3 RENDERING — Harga & Durasi
    // ----------------------------------------------------------
    describe('Rendering — Harga & Durasi', () => {
        it('memanggil formatDurasi dengan durasi_hari & durasi_malam', () => {
            renderPage(
                makePagination([
                    makePackage({ durasi_hari: 4, durasi_malam: 3 }),
                ]),
            );

            expect(formatDurasi).toHaveBeenCalledWith(4, 3);
        });

        it('menampilkan label "Harga per Orang" saat harga_per_rombongan null', () => {
            renderPage(
                makePagination([
                    makePackage({
                        id: 1,
                        harga_per_orang: '750000',
                        harga_per_rombongan: null,
                    }),
                ]),
            );

            expect(screen.getByText('Harga per Orang')).toBeInTheDocument();
        });

        it('menampilkan label "Harga Rombongan" saat harga_per_rombongan ada & > 0', () => {
            renderPage(
                makePagination([
                    makePackage({
                        id: 1,
                        harga_per_orang: '750000',
                        harga_per_rombongan: '5000000',
                    }),
                ]),
            );

            expect(screen.getByText('Harga Rombongan')).toBeInTheDocument();
        });

        it('memakai harga_per_orang saat rombongan null', () => {
            renderPage(
                makePagination([
                    makePackage({
                        id: 1,
                        harga_per_orang: '750000',
                        harga_per_rombongan: null,
                    }),
                ]),
            );

            expect(formatCurrency).toHaveBeenCalledWith('750000');
        });

        it('memakai harga_per_rombongan saat rombongan > 0', () => {
            renderPage(
                makePagination([
                    makePackage({
                        id: 1,
                        harga_per_orang: '750000',
                        harga_per_rombongan: '5000000',
                    }),
                ]),
            );

            expect(formatCurrency).toHaveBeenCalledWith('5000000');
        });

        it('memakai harga_per_orang saat harga_per_rombongan = "0"', () => {
            renderPage(
                makePagination([
                    makePackage({
                        id: 1,
                        harga_per_orang: '750000',
                        harga_per_rombongan: '0',
                    }),
                ]),
            );

            expect(formatCurrency).toHaveBeenCalledWith('750000');
        });
    });

    // ----------------------------------------------------------
    // 14.4 RENDERING — EmptyState
    // ----------------------------------------------------------
    describe('Rendering — EmptyState', () => {
        it('merender EmptyState saat packages kosong', () => {
            renderPage(makePagination([]));

            const emptyState = screen.getByTestId('empty-state');
            expect(emptyState).toHaveTextContent('Tidak ada paket ditemukan');
            expect(emptyState).toHaveTextContent(
                'Belum ada paket wisata aktif saat ini.',
            );
        });

        it('merender EmptyState dengan search context saat filter aktif', () => {
            renderPage(makePagination([]), { search: 'xyz' });

            const emptyState = screen.getByTestId('empty-state');
            expect(emptyState).toHaveTextContent(
                'Tidak ditemukan paket dengan kata kunci "xyz".',
            );
            expect(
                screen.getByRole('button', { name: 'Reset Filter' }),
            ).toBeInTheDocument();
        });
    });

    // ----------------------------------------------------------
    // 14.5 INTERACTION — Search Debounce
    // ----------------------------------------------------------
    describe('Interaction — Search Debounce', () => {
        it('tidak memanggil getPackages sebelum 300ms', async () => {
            renderPage();

            const input = screen.getByPlaceholderText(
                /Cari nama paket wisata/i,
            );
            await fireEvent.input(input, { target: { value: 'bali' } });

            expect(getPackages).not.toHaveBeenCalled();
        });

        it('memanggil getPackages setelah 300ms dengan status "aktif"', async () => {
            renderPage();

            const input = screen.getByPlaceholderText(
                /Cari nama paket wisata/i,
            );
            await fireEvent.input(input, { target: { value: 'bali' } });

            await vi.advanceTimersByTimeAsync(300);

            expect(getPackages).toHaveBeenCalledOnce();
            expect(getPackages).toHaveBeenCalledWith(
                { search: 'bali', status: 'aktif' },
                { preserveState: true, preserveScroll: true, replace: true },
            );
        });

        it('debounce meringkas panggilan saat mengetik cepat', async () => {
            renderPage();

            const input = screen.getByPlaceholderText(
                /Cari nama paket wisata/i,
            );
            await fireEvent.input(input, { target: { value: 'b' } });
            await fireEvent.input(input, { target: { value: 'ba' } });
            await fireEvent.input(input, { target: { value: 'bal' } });
            await fireEvent.input(input, { target: { value: 'bali' } });

            await vi.advanceTimersByTimeAsync(300);

            expect(getPackages).toHaveBeenCalledOnce();
        });
    });

    // ----------------------------------------------------------
    // 14.6 INTERACTION — Clear Search
    // ----------------------------------------------------------
    describe('Interaction — Clear Search', () => {
        it('klik clear langsung memanggil getPackages tanpa debounce', async () => {
            renderPage(makePagination(SAMPLE_PACKAGES), { search: 'bali' });

            await fireEvent.click(
                screen.getByRole('button', { name: 'Hapus Pencarian' }),
            );

            expect(getPackages).toHaveBeenCalledOnce();
            expect(getPackages).toHaveBeenCalledWith(
                { search: '', status: 'aktif' },
                { preserveState: true, preserveScroll: true, replace: true },
            );
        });

        it('klik "Reset Filter" di EmptyState memanggil clearSearch', async () => {
            renderPage(makePagination([]), { search: 'xyz' });

            await fireEvent.click(
                screen.getByRole('button', { name: 'Reset Filter' }),
            );

            expect(getPackages).toHaveBeenCalledWith(
                { search: '', status: 'aktif' },
                expect.any(Object),
            );
        });
    });

    // ----------------------------------------------------------
    // 14.7 A11Y
    // ----------------------------------------------------------
    describe('Accessibility', () => {
        it('cover image punya alt = nama paket', () => {
            renderPage(
                makePagination([
                    makePackage({
                        id: 1,
                        nama: 'Paket Test Alt',
                        cover_image: 'x.jpg',
                    }),
                ]),
            );

            expect(screen.getByAltText('Paket Test Alt')).toBeInTheDocument();
        });

        it('search input punya placeholder deskriptif', () => {
            renderPage();
            expect(
                screen.getByPlaceholderText(/Cari nama paket wisata/i),
            ).toBeInTheDocument();
        });
    });
});
