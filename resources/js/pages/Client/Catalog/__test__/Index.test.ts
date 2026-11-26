/**
 * ============================================================
 * Client Catalog — Index Page — Component Test
 * ============================================================
 *
 * Fokus pengujian:
 *   1. Isolation & Mocking  → Inertia (router + inertia), enums,
 *                             format helpers, & child components
 *                             (ClientLayout, Pagination) di-mock.
 *   2. Props & Rendering    → search, kategori chips, sort select,
 *                             grid card, empty state, pagination.
 *   3. Interaction          → search debounce, kategori click,
 *                             sort change, clear filters.
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
const mockRouterGet = vi.hoisted(() => vi.fn());

// ============================================================
// 2. MOCK — INERTIA
// ============================================================
vi.mock('@inertiajs/svelte', () => ({
    inertia: vi.fn(),
    router: { get: mockRouterGet },
}));

// ============================================================
// 3. MOCK — ENUMS
// ============================================================
vi.mock('@/utils/enums', () => ({
    packageKategoriLabel: vi.fn((k: string) => {
        const map: Record<string, string> = {
            wisata_alam: 'Alam',
            budaya: 'Budaya',
            kuliner: 'Kuliner',
            petualangan: 'Petualangan',
            lainnya: 'Lainnya',
        };
        return map[k] ?? k;
    }),
}));

// ============================================================
// 4. MOCK — FORMAT HELPERS
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
// 5. MOCK — CLIENT LAYOUT
// ============================================================
vi.mock('@/components/layout/ClientLayout.svelte', () => ({
    default: (anchor: Node, props: { children?: (anchor: Node) => void }) => {
        props.children?.(anchor);
        return {};
    },
}));

// ============================================================
// 6. MOCK — PAGINATION
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
// 7. STATIC IMPORT
// ============================================================
import { formatCurrency } from '@/utils/formatCurrency';
import { formatDurasi } from '@/utils/duration';

// ============================================================
// 8. FIXTURES
// ============================================================
const makePackage = (overrides = {}) => ({
    id: 1,
    nama: 'Paket Bali',
    slug: 'paket-bali',
    kategori: 'wisata_alam',
    cover_image: 'packages/bali.jpg',
    durasi_hari: 3,
    durasi_malam: 2,
    harga_per_orang: '750000',
    harga_per_rombongan: null as string | null,
    mitra: { id: 1, name: 'PT Mitra Jaya' },
    ...overrides,
});

const makePackages = (data: any[], overrides = {}) => ({
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
    makePackage({
        id: 1,
        nama: 'Paket Bali',
        slug: 'paket-bali',
        kategori: 'wisata_alam',
        harga_per_orang: '750000',
        harga_per_rombongan: null,
        mitra: { id: 1, name: 'PT Mitra Jaya' },
    }),
    makePackage({
        id: 2,
        nama: 'Paket Lombok',
        slug: 'paket-lombok',
        kategori: 'budaya',
        cover_image: null,
        harga_per_orang: '1000000',
        harga_per_rombongan: '5000000',
        mitra: null as any,
    }),
];

const DEFAULT_FILTERS = {
    search: '',
    status: null,
    kategori: '',
    sort: 'nama',
    direction: 'asc',
};

const renderPage = (
    packages = makePackages(SAMPLE_PACKAGES),
    filters: Record<string, unknown> = DEFAULT_FILTERS,
    ratings: Record<string, { rata_rata: number; jumlah: number }> = {},
) =>
    render(Index, {
        props: { packages, filters, ratings } as any,
    });

// ============================================================
// 9. TEST SUITE
// ============================================================
describe('Client Catalog — Index Page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        mockUpdaters.clear();
        vi.useFakeTimers();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    // ----------------------------------------------------------
    // 9.1 RENDERING — Header & Search
    // ----------------------------------------------------------
    describe('Rendering — Header', () => {
        it('menampilkan judul "Jelajahi Paket"', () => {
            renderPage();

            expect(
                screen.getByRole('heading', {
                    level: 1,
                    name: 'Jelajahi Paket',
                }),
            ).toBeInTheDocument();
        });

        it('menampilkan input search dengan placeholder', () => {
            renderPage();
            expect(
                screen.getByPlaceholderText('Cari destinasi wisata…'),
            ).toBeInTheDocument();
        });

        it('menampilkan select sort dengan semua opsi', () => {
            renderPage();

            const select = screen.getByLabelText(
                'Urutkan',
            ) as HTMLSelectElement;
            expect(select).toBeInTheDocument();

            const options = Array.from(select.options).map((o) => o.value);
            expect(options).toEqual([
                'nama',
                'harga_per_orang',
                'durasi_hari',
                'created_at',
            ]);
        });
    });

    // ----------------------------------------------------------
    // 9.2 RENDERING — Kategori Chips
    // ----------------------------------------------------------
    describe('Rendering — Kategori Chips', () => {
        it('merender 6 chip kategori (Semua + 5)', () => {
            renderPage();

            expect(
                screen.getByRole('button', { name: 'Semua' }),
            ).toBeInTheDocument();
            expect(
                screen.getByRole('button', { name: 'Alam' }),
            ).toBeInTheDocument();
            expect(
                screen.getByRole('button', { name: 'Budaya' }),
            ).toBeInTheDocument();
            expect(
                screen.getByRole('button', { name: 'Kuliner' }),
            ).toBeInTheDocument();
            expect(
                screen.getByRole('button', { name: 'Petualangan' }),
            ).toBeInTheDocument();
            expect(
                screen.getByRole('button', { name: 'Lainnya' }),
            ).toBeInTheDocument();
        });

        it('chip "Semua" aktif saat kategori=""', () => {
            renderPage();
            expect(screen.getByRole('button', { name: 'Semua' })).toHaveClass(
                'bg-dark',
            );
        });

        it('chip kategori aktif saat kategori terpilih', () => {
            renderPage(makePackages(SAMPLE_PACKAGES), {
                ...DEFAULT_FILTERS,
                kategori: 'budaya',
            });
            expect(screen.getByRole('button', { name: 'Budaya' })).toHaveClass(
                'bg-dark',
            );
            expect(
                screen.getByRole('button', { name: 'Semua' }),
            ).not.toHaveClass('bg-dark');
        });
    });

    // ----------------------------------------------------------
    // 9.3 RENDERING — Grid Card
    // ----------------------------------------------------------
    describe('Rendering — Grid Card', () => {
        it('merender 2 card untuk 2 paket', () => {
            renderPage();

            expect(screen.getByText('Paket Bali')).toBeInTheDocument();
            expect(screen.getByText('Paket Lombok')).toBeInTheDocument();
        });

        it('menampilkan cover image dengan alt=nama paket', () => {
            renderPage();

            const img = screen.getByAltText('Paket Bali') as HTMLImageElement;
            expect(img).toBeInTheDocument();
            expect(img.getAttribute('src')).toBe('/storage/packages/bali.jpg');
        });

        it('menampilkan placeholder "Tanpa Gambar" saat cover_image null', () => {
            renderPage();
            expect(screen.getByText('Tanpa Gambar')).toBeInTheDocument();
        });

        it('menampilkan badge kategori', () => {
            renderPage();

            // Ambil card links (bukan chip filter)
            const cardLinks = screen
                .getAllByRole('link')
                .filter((el) =>
                    el.getAttribute('href')?.startsWith('/client/catalog/'),
                );

            // Card Bali (index 0) → kategori "Alam"
            const baliCard = cardLinks.find(
                (el) =>
                    el.getAttribute('href') === '/client/catalog/paket-bali',
            )!;
            expect(baliCard).toHaveTextContent('Alam');

            // Card Lombok (index 1) → kategori "Budaya"
            const lombokCard = cardLinks.find(
                (el) =>
                    el.getAttribute('href') === '/client/catalog/paket-lombok',
            )!;
            expect(lombokCard).toHaveTextContent('Budaya');
        });

        it('menampilkan nama mitra', () => {
            renderPage();
            expect(screen.getByText('PT Mitra Jaya')).toBeInTheDocument();
            expect(screen.getByText('Mitra Travel')).toBeInTheDocument(); // fallback
        });
    });

    // ----------------------------------------------------------
    // 9.4 RENDERING — Harga (conditional)
    // ----------------------------------------------------------
    describe('Rendering — Harga', () => {
        it('menampilkan "Mulai dari" saat harga_per_rombongan null', () => {
            renderPage();
            expect(screen.getByText('Mulai dari')).toBeInTheDocument();
            expect(formatCurrency).toHaveBeenCalledWith('750000');
        });

        it('menampilkan "Harga Rombongan" saat harga_per_rombongan ada', () => {
            renderPage();
            expect(screen.getByText('Harga Rombongan')).toBeInTheDocument();
            expect(formatCurrency).toHaveBeenCalledWith('5000000');
        });

        it('memanggil formatDurasi untuk setiap paket', () => {
            renderPage();
            expect(formatDurasi).toHaveBeenCalledWith(3, 2);
        });
    });

    // ----------------------------------------------------------
    // 9.5 RENDERING — Link ke Detail
    // ----------------------------------------------------------
    describe('Rendering — Link Detail', () => {
        it('setiap card punya link ke /client/catalog/:slug', () => {
            renderPage();

            const links = screen.getAllByRole('link');
            const catalogLinks = links.filter((el) =>
                el.getAttribute('href')?.startsWith('/client/catalog/'),
            );
            expect(catalogLinks.length).toBeGreaterThan(0);
            expect(catalogLinks[0].getAttribute('href')).toBe(
                '/client/catalog/paket-bali',
            );
        });

        it('link "Lihat Detail" ada di setiap card', () => {
            renderPage();

            const detailLinks = screen.getAllByRole('link', {
                name: /Lihat Detail/i,
            });
            expect(detailLinks.length).toBe(2);
        });
    });

    // ----------------------------------------------------------
    // 9.6 RENDERING — Empty State & Pagination
    // ----------------------------------------------------------
    describe('Rendering — Empty State & Pagination', () => {
        it('merender empty state saat data kosong', () => {
            renderPage(makePackages([]));

            expect(
                screen.getByText(
                    'Tidak ada paket yang sesuai dengan pencarian Anda.',
                ),
            ).toBeInTheDocument();
        });

        it('merender tombol "Hapus Filter" di empty state saat filter aktif', () => {
            renderPage(makePackages([]), { ...DEFAULT_FILTERS, search: 'xyz' });

            expect(
                screen.getByRole('button', { name: 'Hapus Filter' }),
            ).toBeInTheDocument();
        });

        it('TIDAK merender tombol "Hapus Filter" saat tidak ada filter aktif', () => {
            renderPage(makePackages([]));
            expect(
                screen.queryByRole('button', { name: 'Hapus Filter' }),
            ).not.toBeInTheDocument();
        });

        it('merender Pagination saat data tersedia', () => {
            renderPage();
            expect(screen.getByTestId('pagination')).toHaveTextContent(
                'Total 2 paket',
            );
        });

        it('TIDAK merender Pagination saat data kosong', () => {
            renderPage(makePackages([]));
            expect(screen.queryByTestId('pagination')).not.toBeInTheDocument();
        });
    });

    // ----------------------------------------------------------
    // 9.7 INTERACTION — Search Debounce
    // ----------------------------------------------------------
    describe('Interaction — Search Debounce', () => {
        it('tidak memanggil router.get sebelum 300ms', async () => {
            renderPage();

            const input = screen.getByPlaceholderText('Cari destinasi wisata…');
            await fireEvent.input(input, { target: { value: 'bali' } });

            expect(mockRouterGet).not.toHaveBeenCalled();
        });

        it('memanggil router.get setelah 300ms dengan payload benar', async () => {
            renderPage();

            const input = screen.getByPlaceholderText('Cari destinasi wisata…');
            await fireEvent.input(input, { target: { value: 'bali' } });

            await vi.advanceTimersByTimeAsync(300);

            expect(mockRouterGet).toHaveBeenCalledOnce();
            expect(mockRouterGet).toHaveBeenCalledWith(
                '/client/catalog',
                expect.objectContaining({ search: 'bali' }),
                { preserveState: true, preserveScroll: true, replace: true },
            );
        });
    });

    // ----------------------------------------------------------
    // 9.8 INTERACTION — Kategori Click
    // ----------------------------------------------------------
    describe('Interaction — Kategori', () => {
        it('klik chip kategori memanggil router.get dengan kategori baru', async () => {
            renderPage();

            await fireEvent.click(
                screen.getByRole('button', { name: 'Budaya' }),
            );

            expect(mockRouterGet).toHaveBeenCalledWith(
                '/client/catalog',
                expect.objectContaining({ kategori: 'budaya' }),
                expect.any(Object),
            );
        });

        it('klik chip "Semua" mereset kategori', async () => {
            renderPage(makePackages(SAMPLE_PACKAGES), {
                ...DEFAULT_FILTERS,
                kategori: 'budaya',
            });

            await fireEvent.click(
                screen.getByRole('button', { name: 'Semua' }),
            );

            expect(mockRouterGet).toHaveBeenCalledWith(
                '/client/catalog',
                expect.objectContaining({ kategori: '' }),
                expect.any(Object),
            );
        });
    });

    // ----------------------------------------------------------
    // 9.9 INTERACTION — Sort Change
    // ----------------------------------------------------------
    describe('Interaction — Sort', () => {
        it('ganti sort ke "harga_per_orang" → direction="asc"', async () => {
            renderPage();

            const select = screen.getByLabelText(
                'Urutkan',
            ) as HTMLSelectElement;
            await fireEvent.change(select, {
                target: { value: 'harga_per_orang' },
            });

            expect(mockRouterGet).toHaveBeenCalledWith(
                '/client/catalog',
                expect.objectContaining({
                    sort: 'harga_per_orang',
                    direction: 'asc',
                }),
                expect.any(Object),
            );
        });

        it('ganti sort ke "created_at" → direction="desc"', async () => {
            renderPage();

            const select = screen.getByLabelText(
                'Urutkan',
            ) as HTMLSelectElement;
            await fireEvent.change(select, { target: { value: 'created_at' } });

            expect(mockRouterGet).toHaveBeenCalledWith(
                '/client/catalog',
                expect.objectContaining({
                    sort: 'created_at',
                    direction: 'desc',
                }),
                expect.any(Object),
            );
        });
    });

    // ----------------------------------------------------------
    // 9.10 INTERACTION — Clear Filters
    // ----------------------------------------------------------
    describe('Interaction — Clear Filters', () => {
        it('klik "Hapus Filter" memanggil router.get dengan payload kosong', async () => {
            renderPage(makePackages([]), { ...DEFAULT_FILTERS, search: 'xyz' });

            await fireEvent.click(
                screen.getByRole('button', { name: 'Hapus Filter' }),
            );

            expect(mockRouterGet).toHaveBeenCalledWith(
                '/client/catalog',
                {},
                { preserveState: true, preserveScroll: true, replace: true },
            );
        });
    });
});

describe('rating pada kartu katalog', () => {
    it('menampilkan bintang hanya untuk paket yang punya ulasan', () => {
        renderPage(makePackages(SAMPLE_PACKAGES), DEFAULT_FILTERS, {
            '1': { rata_rata: 4.5, jumlah: 2 },
        });

        expect(
            screen.getByLabelText('Rating paket Paket Bali'),
        ).toBeInTheDocument();
        expect(screen.getByText('4.5')).toBeInTheDocument();
        expect(screen.getByText('(2 ulasan)')).toBeInTheDocument();
        expect(
            screen.queryByLabelText('Rating paket Paket Lombok'),
        ).not.toBeInTheDocument();
    });
});
