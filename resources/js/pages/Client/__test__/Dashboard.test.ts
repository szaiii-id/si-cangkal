/**
 * ============================================================
 * Client Dashboard (Beranda) — Component Test
 * ============================================================
 *
 * Fokus pengujian:
 *   1. Isolation & Mocking  → Inertia (page.auth), formatCurrency,
 *                             formatDurasi, ClientLayout.
 *   2. Props & Rendering    → welcome header, hero, section baru/laris,
 *                             empty sections, getHarga helper.
 *   3. Interaction          → link katalog, link detail paket.
 *   4. Reactivity           → conditional sections (hanya render jika data ada).
 * ============================================================
 */

import { render, screen } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import Dashboard from '../Dashboard.svelte';

// ============================================================
// 1. MOCK — INERTIA (page.auth)
// ============================================================
const mockPage = vi.hoisted(() => ({
    props: {
        auth: {
            user: { id: 1, name: 'Budi', role: 'client' },
        },
    },
}));

vi.mock('@inertiajs/svelte', () => ({
    page: mockPage,
    inertia: vi.fn(),
}));

// ============================================================
// 2. MOCK — FORMAT HELPERS
// ============================================================
vi.mock('@/utils/formatCurrency', () => ({
    formatCurrency: vi.fn(
        (v: string | number) => `Rp ${Number(v).toLocaleString('id-ID')}`,
    ),
}));

vi.mock('@/utils/duration', () => ({
    formatDurasi: vi.fn((hari: number, malam: number) => `${hari}H ${malam}M`),
}));

// ============================================================
// 3. MOCK — CLIENT LAYOUT
// ============================================================
vi.mock('@/components/layout/ClientLayout.svelte', () => ({
    default: (anchor: Node, props: { children?: (anchor: Node) => void }) => {
        props.children?.(anchor);
        return {};
    },
}));

// ============================================================
// 4. STATIC IMPORT
// ============================================================
import { formatCurrency } from '@/utils/formatCurrency';
import { formatDurasi } from '@/utils/duration';

// ============================================================
// 5. FIXTURES
// ============================================================
const makePackage = (overrides = {}) => ({
    id: 1,
    nama: 'Paket Bali',
    slug: 'paket-bali',
    cover_image: 'packages/bali.jpg',
    durasi_hari: 3,
    durasi_malam: 2,
    harga_per_orang: '750000',
    harga_per_rombongan: null,
    isRombongan: false,
    mitra: { name: 'PT Mitra Jaya' },
    ...overrides,
});

const renderPage = (props = {}) =>
    render(Dashboard, {
        props: {
            paket_baru: [],
            paket_laris: [],
            ...props,
        },
    });

// ============================================================
// 6. TEST SUITE
// ============================================================
describe('Client Dashboard (Beranda)', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        mockPage.props.auth = { user: { id: 1, name: 'Budi', role: 'client' } };
    });

    // ----------------------------------------------------------
    // 6.1 RENDERING — Header Hero
    // ----------------------------------------------------------
    describe('Rendering — Hero', () => {
        it('menampilkan nama user dari page.auth', () => {
            renderPage();
            expect(screen.getByRole('heading', { level: 1 })).toHaveTextContent(
                'Halo, Budi',
            );
        });

        it('menampilkan fallback "Client" jika user null', () => {
            mockPage.props.auth = { user: null } as any;
            renderPage();
            expect(screen.getByRole('heading', { level: 1 })).toHaveTextContent(
                'Halo, Client',
            );
        });

        it('menampilkan link "Jelajahi Katalog" ke /client/catalog', () => {
            renderPage();
            const link = screen.getByRole('link', {
                name: /Jelajahi Katalog/i,
            });
            expect(link).toHaveAttribute('href', '/client/catalog');
        });
    });

    // ----------------------------------------------------------
    // 6.2 RENDERING — Paket Terbaru Section
    // ----------------------------------------------------------
    describe('Rendering — Paket Terbaru', () => {
        it('TIDAK menampilkan section "Paket Terbaru" jika array kosong', () => {
            renderPage({ paket_baru: [] });
            expect(screen.queryByText('Paket Terbaru')).not.toBeInTheDocument();
        });

        it('menampilkan section "Paket Terbaru" jika ada data', () => {
            renderPage({
                paket_baru: [makePackage({ id: 1, nama: 'Paket Baru 1' })],
            });
            expect(screen.getByText('Paket Terbaru')).toBeInTheDocument();
            expect(screen.getByText('Paket Baru 1')).toBeInTheDocument();
        });

        it('menampilkan badge "BARU" di setiap card', () => {
            renderPage({
                paket_baru: [
                    makePackage({ id: 1, nama: 'P1' }),
                    makePackage({ id: 2, nama: 'P2' }),
                ],
            });
            expect(screen.getAllByText('BARU')).toHaveLength(2);
        });

        it('menampilkan placeholder "Tanpa Gambar" saat cover_image null', () => {
            renderPage({
                paket_baru: [makePackage({ id: 1, cover_image: null })],
            });
            expect(screen.getByText('Tanpa Gambar')).toBeInTheDocument();
        });

        it('memanggil formatCurrency dengan harga_per_orang (default)', () => {
            renderPage({
                paket_baru: [
                    makePackage({
                        id: 1,
                        harga_per_orang: '750000',
                        isRombongan: false,
                    }),
                ],
            });
            expect(formatCurrency).toHaveBeenCalledWith('750000');
        });

        it('memakai harga_per_rombongan saat isRombongan=true & harga ada', () => {
            renderPage({
                paket_baru: [
                    makePackage({
                        id: 1,
                        harga_per_orang: '750000',
                        harga_per_rombongan: '3000000',
                        isRombongan: true,
                    }),
                ],
            });
            expect(formatCurrency).toHaveBeenCalledWith('3000000');
        });
    });

    // ----------------------------------------------------------
    // 6.3 RENDERING — Paket Paling Laku Section
    // ----------------------------------------------------------
    describe('Rendering — Paket Paling Laku', () => {
        it('TIDAK menampilkan section "Best Seller" jika array kosong', () => {
            renderPage({ paket_laris: [] });
            expect(screen.queryByText(/Paling Laku/i)).not.toBeInTheDocument();
        });

        it('menampilkan section "Paling Laku (Best Seller)" jika ada data', () => {
            renderPage({
                paket_laris: [makePackage({ id: 1, nama: 'Paket Laris 1' })],
            });
            expect(
                screen.getByText('Paling Laku (Best Seller)'),
            ).toBeInTheDocument();
            expect(screen.getByText('Paket Laris 1')).toBeInTheDocument();
        });

        it('menampilkan badge "Best Seller" di setiap card', () => {
            renderPage({
                paket_laris: [makePackage({ id: 1, nama: 'P1' })],
            });
            expect(screen.getByText('Best Seller')).toBeInTheDocument();
        });

        it('menampilkan nama mitra', () => {
            renderPage({
                paket_laris: [
                    makePackage({
                        id: 1,
                        nama: 'P1',
                        mitra: { name: 'PT Mitra Keren' },
                    }),
                ],
            });
            expect(screen.getByText('PT Mitra Keren')).toBeInTheDocument();
        });

        it('menampilkan fallback "Mitra Travel" saat mitra null', () => {
            renderPage({
                paket_laris: [makePackage({ id: 1, nama: 'P1', mitra: null })],
            });
            expect(screen.getByText('Mitra Travel')).toBeInTheDocument();
        });

        it('memanggil formatDurasi dengan durasi_hari & durasi_malam', () => {
            renderPage({
                paket_laris: [
                    makePackage({
                        id: 1,
                        nama: 'P1',
                        durasi_hari: 4,
                        durasi_malam: 3,
                    }),
                ],
            });
            expect(formatDurasi).toHaveBeenCalledWith(4, 3);
        });
    });

    // ----------------------------------------------------------
    // 6.4 INTERACTION — Links
    // ----------------------------------------------------------
    describe('Interaction — Links', () => {
        it('setiap card paket baru link ke /client/catalog/:slug', () => {
            renderPage({
                paket_baru: [makePackage({ id: 1, slug: 'paket-keren' })],
            });

            const cardLinks = screen
                .getAllByRole('link')
                .filter(
                    (el) =>
                        el.getAttribute('href') ===
                        '/client/catalog/paket-keren',
                );
            expect(cardLinks.length).toBeGreaterThanOrEqual(1);
        });

        it('setiap card paket laris link ke /client/catalog/:slug', () => {
            renderPage({
                paket_laris: [makePackage({ id: 2, slug: 'paket-laris' })],
            });

            const cardLink = screen
                .getAllByRole('link')
                .find(
                    (el) =>
                        el.getAttribute('href') ===
                        '/client/catalog/paket-laris',
                );
            expect(cardLink).toBeInTheDocument();
        });

        it('link "Lihat Semua" di section baru ke /client/catalog', () => {
            renderPage({
                paket_baru: [makePackage({ id: 1 })],
            });

            // Ada minimal 2 link "Lihat Semua" (baru & laris) — pakai semua
            const links = screen.getAllByRole('link', { name: /Lihat Semua/i });
            expect(links.length).toBeGreaterThanOrEqual(1);
            expect(links[0]).toHaveAttribute('href', '/client/catalog');
        });
    });

    // ----------------------------------------------------------
    // 6.5 RENDERING — Kombinasi Section
    // ----------------------------------------------------------
    describe('Rendering — Both Sections', () => {
        it('menampilkan kedua section jika keduanya ada data', () => {
            renderPage({
                paket_baru: [makePackage({ id: 1, nama: 'Baru 1' })],
                paket_laris: [makePackage({ id: 2, nama: 'Laris 1' })],
            });

            expect(screen.getByText('Paket Terbaru')).toBeInTheDocument();
            expect(
                screen.getByText('Paling Laku (Best Seller)'),
            ).toBeInTheDocument();
            expect(screen.getByText('Baru 1')).toBeInTheDocument();
            expect(screen.getByText('Laris 1')).toBeInTheDocument();
        });

        it('TIDAK crash saat kedua section kosong', () => {
            renderPage({ paket_baru: [], paket_laris: [] });

            // Hero tetap ada
            expect(
                screen.getByRole('heading', { level: 1 }),
            ).toBeInTheDocument();
        });
    });
});

it('tidak menampilkan panah dekoratif pada tombol aksi', () => {
    const { container } = renderPage();
    expect(
        container.querySelector('path[d="M14 5l7 7m0 0l-7 7m7-7H3"]'),
    ).toBeNull();
});
