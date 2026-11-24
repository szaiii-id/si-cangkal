/**
 * ============================================================
 * Mitra Packages — Index Page — Component Test
 * ============================================================
 */

import { render, screen, fireEvent } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import Index from '../Index.svelte';

// ============================================================
// 1. HOISTED MOCK REGISTRY
// ============================================================
const mockUpdaters = vi.hoisted(() => new Set<() => void>());
const mockApi = vi.hoisted(() => ({
    getPackages: vi.fn(),
}));

// ============================================================
// 2. MOCK DEPENDENCIES
// ============================================================
vi.mock('@/api/mitra', () => mockApi);

vi.mock('@/utils/enums', () => ({
    packageKategoriLabel: vi.fn((k: string) => k),
    packageStatusBadge: vi.fn((s: string) => `badge-${s}`),
    packageStatusLabel: vi.fn((s: string) => s),
    packageStatusShort: vi.fn((s: string) => s),
}));

vi.mock('@/utils/formatCurrency', () => ({
    formatCurrency: vi.fn((v: string | number) => `Rp ${v}`),
}));

vi.mock('@/utils/duration', () => ({
    formatDurasi: vi.fn((h: number, m: number) => `${h}H ${m}M`),
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
        props.actions?.(anchor);
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
                <select aria-label="Kategori">
                    ${(props.kategoriOptions || []).map((o: any) => `<option value="${o.value}">${o.label}</option>`).join('')}
                </select>
                <button type="button" data-action="clear">Hapus Filter</button>
                <button type="button" data-action="status-draft">draft</button>
            `;

            (
                el.querySelector('[aria-label="Search"]') as HTMLInputElement
            ).oninput = (e) =>
                props.onSearch?.((e.target as HTMLInputElement).value);

            (
                el.querySelector('[aria-label="Kategori"]') as HTMLSelectElement
            ).onchange = (e) =>
                props.onKategoriChange?.((e.target as HTMLSelectElement).value);

            el.querySelector('[data-action="clear"]')!.addEventListener(
                'click',
                () => props.onClear?.(),
            );
            el.querySelector('[data-action="status-draft"]')!.addEventListener(
                'click',
                () => props.onStatusChange?.('draft'),
            );
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
// `syncUI` dihapus karena tidak dipakai di file ini.
// `tick` di-import tetap kalau nanti butuh; kalau tidak, hapus juga
// import `tick` dari svelte. Di sini dibiarkan agar minimal diff.

// ============================================================
// 5. FIXTURES
// ============================================================
// Semua literal `status`/`kategori` diberi cast ke union sempit
// supaya tidak melebar jadi `string` dan bikin error
// "Type 'string' is not assignable to type 'PackageStatus'".
type PackageStatus = 'draft' | 'aktif' | 'arsip';

const makePackage = (overrides: Record<string, unknown> = {}) => ({
    id: 1,
    kode: 'PKT-01',
    nama: 'Paket Bali',
    slug: 'paket-bali',
    deskripsi: 'Wisata Bali 3 hari',
    kategori: 'wisata_alam',
    durasi_hari: 3,
    durasi_malam: 2,
    harga_per_orang: '750000',
    harga_per_rombongan: null as string | null,
    max_peserta: 20,
    cover_image: 'packages/bali.jpg' as string | null,
    status: 'aktif' as PackageStatus,
    created_at: '2026-01-01',
    ...overrides,
});

const makeProps = (overrides: Record<string, unknown> = {}) => ({
    packages: {
        data: [makePackage()],
        current_page: 1,
        last_page: 1,
        total: 1,
        per_page: 15,
        from: 1,
        to: 1,
        links: [],
    },
    filters: {
        search: '',
        status: '',
        kategori: '',
        sort: 'created_at',
        direction: 'desc',
    },
    statusCounts: { draft: 0, aktif: 1, arsip: 0 },
    ...overrides,
});

// Cast di satu titik: `render(Index, ...)`. Ini memutus type-check
// yang tidak perlu tanpa mengubah runtime.
const renderIndex = (props = makeProps()) =>
    render(Index, { props: props as any });

// ============================================================
// 6. TEST SUITE
// ============================================================
describe('Mitra Packages — Index Page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        mockUpdaters.clear();
        vi.useFakeTimers();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    describe('Rendering', () => {
        it('merender PageHeader + tombol Buat Paket Baru', () => {
            renderIndex();
            expect(screen.getByTestId('page-header')).toHaveTextContent(
                'Katalog Paket Wisata',
            );
            expect(
                screen.getByRole('link', { name: /Buat Paket Baru/i }),
            ).toHaveAttribute('href', '/mitra/packages/create');
        });

        it('merender FilterBar', () => {
            renderIndex();
            expect(screen.getByTestId('filter-bar')).toBeInTheDocument();
        });

        it('merender EmptyState jika data kosong', () => {
            renderIndex(
                makeProps({
                    packages: {
                        data: [],
                        current_page: 1,
                        last_page: 1,
                        total: 0,
                        per_page: 15,
                        from: 0,
                        to: 0,
                        links: [],
                    },
                }),
            );
            expect(screen.getByTestId('empty-state')).toBeInTheDocument();
        });

        it('merender card paket jika ada data', () => {
            renderIndex();
            expect(screen.getByText('PKT-01')).toBeInTheDocument();
            expect(screen.getByText('Paket Bali')).toBeInTheDocument();
            expect(screen.getByText('Rp 750000')).toBeInTheDocument();
            expect(screen.getByTestId('pagination')).toBeInTheDocument();
        });

        it('merender link detail paket dengan href yang benar', () => {
            renderIndex();
            const link = screen.getByText('PKT-01').closest('a');
            expect(link).toHaveAttribute('href', '/mitra/packages/1');
        });

        it('menampilkan harga_per_rombongan jika ada', () => {
            renderIndex(
                makeProps({
                    packages: {
                        data: [makePackage({ harga_per_rombongan: '5000000' })],
                        current_page: 1,
                        last_page: 1,
                        total: 1,
                        per_page: 15,
                        from: 1,
                        to: 1,
                        links: [],
                    },
                }),
            );
            expect(screen.getByText('Rp 5000000')).toBeInTheDocument();
            expect(screen.getByText('Harga Rombongan')).toBeInTheDocument();
        });

        it('menampilkan cover image jika ada', () => {
            renderIndex();
            const img = screen.getByAltText('Paket Bali') as HTMLImageElement;
            expect(img).toHaveAttribute('src', '/storage/packages/bali.jpg');
        });

        it('menampilkan placeholder inisial jika cover_image null', () => {
            renderIndex(
                makeProps({
                    packages: {
                        data: [makePackage({ cover_image: null })],
                        current_page: 1,
                        last_page: 1,
                        total: 1,
                        per_page: 15,
                        from: 1,
                        to: 1,
                        links: [],
                    },
                }),
            );
            expect(screen.getByText('P')).toBeInTheDocument();
        });
    });

    describe('Interaction — Filter & Search', () => {
        it('memanggil getPackages saat search debounce selesai', async () => {
            renderIndex();
            const input = screen.getByLabelText('Search');

            await fireEvent.input(input, { target: { value: 'bali' } });
            expect(mockApi.getPackages).not.toHaveBeenCalled();

            await vi.advanceTimersByTimeAsync(300);
            expect(mockApi.getPackages).toHaveBeenCalledWith(
                expect.objectContaining({ search: 'bali' }),
                expect.any(Object),
            );
        });

        it('memanggil getPackages saat status tab berubah', async () => {
            renderIndex();
            await fireEvent.click(screen.getByText('draft'));

            expect(mockApi.getPackages).toHaveBeenCalledWith(
                expect.objectContaining({ status: 'draft' }),
                expect.any(Object),
            );
        });

        it('memanggil getPackages saat kategori berubah', async () => {
            renderIndex();
            const select = screen.getByLabelText('Kategori');
            await fireEvent.change(select, { target: { value: 'budaya' } });

            expect(mockApi.getPackages).toHaveBeenCalledWith(
                expect.objectContaining({ kategori: 'budaya' }),
                expect.any(Object),
            );
        });

        it('memanggil getPackages saat tombol clear filter diklik', async () => {
            renderIndex(
                makeProps({
                    filters: {
                        search: 'bali',
                        status: 'aktif',
                        kategori: 'budaya',
                        sort: 'created_at',
                        direction: 'desc',
                    },
                }),
            );
            await fireEvent.click(screen.getByText('Hapus Filter'));

            expect(mockApi.getPackages).toHaveBeenCalledWith(
                {},
                expect.any(Object),
            );
        });
    });
});
