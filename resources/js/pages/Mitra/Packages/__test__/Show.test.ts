/**
 * ============================================================
 * Mitra Packages — Show Page — Component Test
 * ============================================================
 */

import { render, screen, fireEvent } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { tick } from 'svelte';
import Show from '../Show.svelte';

// ============================================================
// 1. HOISTED MOCK REGISTRY
// ============================================================
const mockUpdaters = vi.hoisted(() => new Set<() => void>());
const mockApi = vi.hoisted(() => ({
    archivePackage: vi.fn(),
    restorePackage: vi.fn(),
    deletePackage: vi.fn(),
}));

// ============================================================
// 2. MOCK DEPENDENCIES
// ============================================================
vi.mock('@/api/mitra', () => mockApi);

vi.mock('@/utils/formatCurrency', () => ({
    formatCurrency: vi.fn((v: string | number) => `Rp ${v}`),
}));

vi.mock('@/utils/duration', () => ({
    formatDurasi: vi.fn((h: number, m: number) => `${h}H ${m}M`),
}));

vi.mock('@/utils/timezone', () => ({
    timezoneLabelLong: vi.fn((tz: string) => `TZ: ${tz}`),
}));

vi.mock('@/utils/enums', () => ({
    packageStatusBadge: vi.fn((s: string) => `badge-${s}`),
    packageStatusLabel: vi.fn((s: string) => s),
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

vi.mock('@/global/BackButton.svelte', () => ({
    default: (anchor: Node, props: any) => {
        const el = document.createElement('div');
        el.setAttribute('data-testid', 'back-button');
        el.textContent = props.label;
        anchor.parentNode?.insertBefore(el, anchor);
        return {};
    },
}));

vi.mock('@/components/mitra/packages/PackageHero.svelte', () => ({
    default: (anchor: Node, props: any) => {
        const el = document.createElement('div');
        el.setAttribute('data-testid', 'package-hero');
        el.setAttribute('data-nama', props.nama);
        el.setAttribute('data-status', props.status);
        anchor.parentNode?.insertBefore(el, anchor);
        return {};
    },
}));

vi.mock('@/components/mitra/packages/PackageActions.svelte', () => ({
    default: (anchor: Node, props: any) => {
        const el = document.createElement('div');
        el.setAttribute('data-testid', 'package-actions');
        el.innerHTML = `
            <button type="button" data-action="archive">Archive</button>
            <button type="button" data-action="restore">Restore</button>
            <button type="button" data-action="delete">Delete</button>
        `;
        anchor.parentNode?.insertBefore(el, anchor);

        el.querySelector('[data-action="archive"]')!.addEventListener(
            'click',
            () => props.onArchive?.(),
        );
        el.querySelector('[data-action="restore"]')!.addEventListener(
            'click',
            () => props.onRestore?.(),
        );
        el.querySelector('[data-action="delete"]')!.addEventListener(
            'click',
            () => props.onDelete?.(),
        );
        return {};
    },
}));

vi.mock('@/components/mitra/packages/PackageItinerary.svelte', () => ({
    default: (anchor: Node, props: any) => {
        const el = document.createElement('div');
        el.setAttribute('data-testid', 'package-itinerary');
        el.setAttribute('data-days-count', String(props.days?.length ?? 0));
        anchor.parentNode?.insertBefore(el, anchor);
        return {};
    },
}));

vi.mock('@/components/mitra/packages/PackageFacilities.svelte', () => ({
    default: (anchor: Node, props: any) => {
        const el = document.createElement('div');
        el.setAttribute('data-testid', 'package-facilities');
        el.setAttribute(
            'data-facilities-count',
            String(props.facilities?.length ?? 0),
        );
        anchor.parentNode?.insertBefore(el, anchor);
        return {};
    },
}));

vi.mock('@/global/ConfirmModal.svelte', () => ({
    default: (anchor: Node, props: any) => {
        const el = document.createElement('div');
        anchor.parentNode?.insertBefore(el, anchor);

        const update = () => {
            el.innerHTML = '';
            if (!props.open) {
                el.removeAttribute('data-testid');
                return;
            }

            el.setAttribute(
                'data-testid',
                `modal-${props.title.replace(/\s+/g, '-')}`,
            );
            el.innerHTML = `
                <h2>${props.title}</h2>
                <p>${props.description || ''}</p>
                <button type="button" data-action="confirm">${props.confirmLabel}</button>
            `;
            el.querySelector('button')!.addEventListener('click', () =>
                props.onConfirm(),
            );
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
    mockUpdaters.forEach((fn) => fn());
}

// ============================================================
// 5. FIXTURES
// ============================================================
// Jangan deklarasikan ulang `PackageDetail` — tipe asli di project
// punya `kategori: PackageKategori` & `status: PackageStatus`, jadi
// deklarasi lokal bikin error "Two different types with this name".
// Cukup sempitkan literal di fixture, lalu cast di titik `render`.
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
    timezone: 'Asia/Jakarta',
    cover_image: 'packages/bali.jpg' as string | null,
    status: 'draft' as 'draft' | 'aktif' | 'arsip',
    created_at: '2026-01-01',
    updated_at: '2026-01-02',
    days: [
        {
            id: 1,
            hari_ke: 1,
            judul: 'Hari 1',
            deskripsi: 'Day 1',
            tanggal: '2026-02-01',
            status: 'pending',
        },
        {
            id: 2,
            hari_ke: 2,
            judul: 'Hari 2',
            deskripsi: 'Day 2',
            tanggal: '2026-02-02',
            status: 'pending',
        },
        {
            id: 3,
            hari_ke: 3,
            judul: 'Hari 3',
            deskripsi: 'Day 3',
            tanggal: '2026-02-03',
            status: 'pending',
        },
    ],
    facilities: [
        { id: 1, tipe: 'include', nama: 'Hotel' },
        { id: 2, tipe: 'exclude', nama: 'Tiket Pesawat' },
    ],
    ...overrides,
});

const renderPage = (pkg = makePackage()) =>
    render(Show, { props: { package: pkg } as any });

// ============================================================
// 6. TEST SUITE
// ============================================================
describe('Mitra Packages — Show Page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        mockUpdaters.clear();
    });

    describe('Rendering', () => {
        it('merender BackButton', () => {
            renderPage();
            expect(screen.getByTestId('back-button')).toHaveTextContent(
                'Kembali ke Daftar Paket',
            );
        });

        it('merender PackageHero dengan data paket', () => {
            renderPage();
            const hero = screen.getByTestId('package-hero');
            expect(hero).toHaveAttribute('data-nama', 'Paket Bali');
            expect(hero).toHaveAttribute('data-status', 'draft');
        });

        it('merender PackageItinerary dan PackageFacilities', () => {
            renderPage();
            expect(screen.getByTestId('package-itinerary')).toHaveAttribute(
                'data-days-count',
                '3',
            );
            expect(screen.getByTestId('package-facilities')).toHaveAttribute(
                'data-facilities-count',
                '2',
            );
        });

        it('menampilkan harga per orang dan harga rombongan jika ada', () => {
            renderPage(makePackage({ harga_per_rombongan: '5000000' }));
            expect(screen.getByText('Rp 750000')).toBeInTheDocument();
            expect(screen.getByText('Rp 5000000')).toBeInTheDocument();
        });
    });

    describe('Banner Status Kelengkapan (Draft)', () => {
        it('menampilkan banner "Belum Siap Terbit" jika draft & belum lengkap', () => {
            renderPage(makePackage({ days: [], facilities: [] }));
            expect(
                screen.getByText('Paket Belum Siap Terbit'),
            ).toBeInTheDocument();
        });

        it('menampilkan banner "Tanpa Foto" jika draft & lengkap tapi cover kosong', () => {
            renderPage(makePackage({ cover_image: null }));
            expect(
                screen.getByText('Paket Siap Terbit, Tapi Tanpa Foto'),
            ).toBeInTheDocument();
        });

        it('menampilkan banner "Siap Terbit" jika draft & lengkap & ada foto', () => {
            renderPage();
            expect(
                screen.getByText('Paket Lengkap & Siap Terbit'),
            ).toBeInTheDocument();
        });

        it('TIDAK menampilkan banner jika status bukan draft', () => {
            renderPage(makePackage({ status: 'aktif' }));
            expect(
                screen.queryByText(/Paket Belum Siap Terbit/i),
            ).not.toBeInTheDocument();
            expect(
                screen.queryByText(/Paket Siap Terbit, Tapi Tanpa Foto/i),
            ).not.toBeInTheDocument();
            expect(
                screen.queryByText(/Paket Lengkap & Siap Terbit/i),
            ).not.toBeInTheDocument();
        });
    });

    describe('Interaction — Archive/Restore/Delete', () => {
        it('membuka modal archive saat tombol archive diklik', async () => {
            renderPage();
            await fireEvent.click(screen.getByText('Archive'));
            await syncUI();

            expect(
                screen.getByTestId('modal-Arsipkan-Paket?'),
            ).toBeInTheDocument();
        });

        it('memanggil archivePackage saat modal dikonfirmasi', async () => {
            mockApi.archivePackage.mockImplementation(
                (_id: number, opts: any) => opts.onFinish?.(),
            );
            renderPage();
            await fireEvent.click(screen.getByText('Archive'));
            await syncUI();

            const modal = screen.getByTestId('modal-Arsipkan-Paket?');
            await fireEvent.click(
                modal.querySelector('[data-action="confirm"]')!,
            );
            await syncUI();

            expect(mockApi.archivePackage).toHaveBeenCalledWith(
                1,
                expect.any(Object),
            );
        });

        it('membuka modal restore saat tombol restore diklik', async () => {
            renderPage();
            await fireEvent.click(screen.getByText('Restore'));
            await syncUI();

            expect(
                screen.getByTestId('modal-Aktifkan-Kembali?'),
            ).toBeInTheDocument();
        });

        it('membuka modal delete saat tombol delete diklik', async () => {
            renderPage();
            await fireEvent.click(screen.getByText('Delete'));
            await syncUI();

            expect(
                screen.getByTestId('modal-Hapus-Paket?'),
            ).toBeInTheDocument();
        });

        it('memanggil deletePackage saat modal dikonfirmasi', async () => {
            mockApi.deletePackage.mockImplementation((_id: number, opts: any) =>
                opts.onFinish?.(),
            );
            renderPage();
            await fireEvent.click(screen.getByText('Delete'));
            await syncUI();

            const modal = screen.getByTestId('modal-Hapus-Paket?');
            await fireEvent.click(
                modal.querySelector('[data-action="confirm"]')!,
            );
            await syncUI();

            expect(mockApi.deletePackage).toHaveBeenCalledWith(
                1,
                expect.any(Object),
            );
        });
    });
});
