/**
 * ============================================================
 * Mitra Packages — Edit Page — Component Test
 * ============================================================
 */

import { render, screen, fireEvent } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { tick } from 'svelte';
import Edit from '../Edit.svelte';

// ============================================================
// 1. HOISTED MOCK REGISTRY
// ============================================================
const mockApi = vi.hoisted(() => ({
    updatePackage: vi.fn(),
    publishPackage: vi.fn(),
}));
const mockUpdaters = vi.hoisted(() => new Set<() => void>());

// ============================================================
// 2. MOCK DEPENDENCIES
// ============================================================
vi.mock('@/api/mitra', () => mockApi);

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

vi.mock('@/global/BackButton.svelte', () => ({
    default: (anchor: Node, props: any) => {
        const el = document.createElement('div');
        el.setAttribute('data-testid', 'back-button');
        el.textContent = props.label;
        anchor.parentNode?.insertBefore(el, anchor);
        return {};
    },
}));

vi.mock('@/global/InputText.svelte', () => ({
    default: (anchor: Node, props: any) => {
        const el = document.createElement('div');
        anchor.parentNode?.insertBefore(el, anchor);

        const update = () => {
            el.innerHTML = `
                <label for="${props.id}">${props.label}</label>
                <input type="text" id="${props.id}" aria-label="${props.label}" value="${props.value || ''}" />
            `;
            (el.querySelector('input') as HTMLInputElement).oninput = (e) =>
                (props.value = (e.target as HTMLInputElement).value);
        };

        update();
        mockUpdaters.add(update);
        return {};
    },
}));

vi.mock('@/global/RupiahInput.svelte', () => ({
    default: (anchor: Node, props: any) => {
        const el = document.createElement('div');
        anchor.parentNode?.insertBefore(el, anchor);

        const update = () => {
            el.innerHTML = `
                <label>${props.label}</label>
                <input type="number" aria-label="${props.label}" value="${props.value || 0}" />
            `;
            (el.querySelector('input') as HTMLInputElement).oninput = (e) =>
                (props.value = Number((e.target as HTMLInputElement).value));
        };

        update();
        mockUpdaters.add(update);
        return {};
    },
}));

vi.mock('@/global/ImageUploader.svelte', () => ({
    default: (anchor: Node) => {
        const el = document.createElement('div');
        el.setAttribute('data-testid', 'image-uploader');
        anchor.parentNode?.insertBefore(el, anchor);
        return {};
    },
}));

vi.mock('@/components/package/DaysEditor.svelte', () => ({
    default: (anchor: Node, props: any) => {
        const el = document.createElement('div');
        el.setAttribute('data-testid', 'days-editor');
        el.textContent = `${props.days?.length ?? 0} days`;
        anchor.parentNode?.insertBefore(el, anchor);
        return {};
    },
}));

vi.mock('@/components/package/FacilitiesEditor.svelte', () => ({
    default: (anchor: Node, props: any) => {
        const el = document.createElement('div');
        el.setAttribute('data-testid', 'facilities-editor');
        el.textContent = `${props.facilities?.length ?? 0} facilities`;
        anchor.parentNode?.insertBefore(el, anchor);
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
const makePackage = (overrides: Record<string, unknown> = {}) =>
    ({
        id: 1,
        mitra_id: 1,
        nama: 'Paket Bali',
        kode: 'PKT-001',
        slug: 'paket-bali',
        deskripsi: 'Wisata Bali',
        kategori: 'wisata_alam',
        durasi_hari: 2,
        durasi_malam: 1,
        harga_per_orang: '750000',
        harga_per_rombongan: null,
        max_peserta: null,
        timezone: 'Asia/Makassar',
        cover_image: 'packages/bali.jpg',
        status: 'aktif',
        created_at: '2026-01-01T00:00:00Z',
        updated_at: '2026-01-01T00:00:00Z',
        days: [
            {
                id: 1,
                hari_ke: 1,
                judul: 'Day 1',
                deskripsi: 'Desc 1',
                jam_mulai: '08:00',
                jam_selesai: '17:00',
                lokasi: 'Bali',
            },
            {
                id: 2,
                hari_ke: 2,
                judul: 'Day 2',
                deskripsi: 'Desc 2',
                jam_mulai: '08:00',
                jam_selesai: '17:00',
                lokasi: 'Bali',
            },
        ],
        facilities: [
            { id: 1, tipe: 'include', nama: 'Hotel' },
            { id: 2, tipe: 'exclude', nama: 'Tiket Pesawat' },
        ],
        ...overrides,
    }) as any;

const renderPage = (pkg = makePackage()) =>
    render(Edit, { props: { package: pkg } });

// ============================================================
// 6. TEST SUITE
// ============================================================
describe('Mitra Packages — Edit Page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        mockUpdaters.clear();
    });

    describe('Rendering', () => {
        it('merender tab navigation (Info, Rencana Kegiatan, Fasilitas)', () => {
            renderPage();
            expect(
                screen.getByRole('button', { name: /Info & Harga/i }),
            ).toBeInTheDocument();
            expect(
                screen.getByRole('button', { name: /Rencana Kegiatan/i }),
            ).toBeInTheDocument();
            expect(
                screen.getByRole('button', { name: /Fasilitas & Foto/i }),
            ).toBeInTheDocument();
        });

        it('menampilkan tab Info & Harga secara default', () => {
            renderPage();
            expect(screen.getByLabelText(/Nama Paket/i)).toBeInTheDocument();
        });

        it('merender info dasar paket (nama, deskripsi, kategori, timezone)', () => {
            renderPage();
            const namaInput = screen.getByLabelText(
                /Nama Paket/i,
            ) as HTMLInputElement;
            expect(namaInput.value).toBe('Paket Bali');

            const deskripsi = screen.getByLabelText(
                /Deskripsi/i,
            ) as HTMLTextAreaElement;
            expect(deskripsi.value).toBe('Wisata Bali');
        });

        it('memilih kategori & timezone sesuai paket', () => {
            renderPage();
            expect(
                (screen.getByLabelText(/Kategori/i) as HTMLSelectElement).value,
            ).toBe('wisata_alam');
            expect(
                (screen.getByLabelText(/Zona Waktu/i) as HTMLSelectElement)
                    .value,
            ).toBe('Asia/Makassar');
        });

        it('merender durasi & harga', () => {
            renderPage();
            expect(screen.getByLabelText(/Durasi Hari/i)).toBeInTheDocument();
            expect(screen.getByLabelText(/Durasi Malam/i)).toBeInTheDocument();
            expect(
                screen.getByLabelText(/Harga per Orang/i),
            ).toBeInTheDocument();
        });
    });

    describe('Interaction — Tab Navigation', () => {
        it('pindah ke tab Rencana Kegiatan saat tombol klik', async () => {
            renderPage();
            await fireEvent.click(
                screen.getByRole('button', { name: /Rencana Kegiatan/i }),
            );
            await syncUI();

            expect(screen.getByTestId('days-editor')).toBeInTheDocument();
        });

        it('pindah ke tab Fasilitas & Foto saat tombol klik', async () => {
            renderPage();
            await fireEvent.click(
                screen.getByRole('button', { name: /Fasilitas & Foto/i }),
            );
            await syncUI();

            expect(screen.getByTestId('facilities-editor')).toBeInTheDocument();
            expect(screen.getByTestId('image-uploader')).toBeInTheDocument();
        });

        it('kembali ke tab Info dari tab Rencana Kegiatan', async () => {
            renderPage();
            await fireEvent.click(
                screen.getByRole('button', { name: /Rencana Kegiatan/i }),
            );
            await syncUI();
            await fireEvent.click(
                screen.getByRole('button', { name: /Info & Harga/i }),
            );
            await syncUI();

            expect(screen.getByLabelText(/Nama Paket/i)).toBeInTheDocument();
        });
    });

    describe('Interaction — Toggle Harga Rombongan', () => {
        it('toggle checkbox menampilkan input harga rombongan', async () => {
            renderPage();
            const checkbox = screen.getByRole('checkbox', {
                name: /Gunakan Harga Rombongan/i,
            });
            await fireEvent.click(checkbox);
            await syncUI();

            expect(
                screen.getByLabelText('Harga Rombongan', { exact: true }),
            ).toBeInTheDocument();
            expect(
                screen.getByLabelText(/Maksimal Peserta/i),
            ).toBeInTheDocument();
        });
    });

    describe('Interaction — Submit', () => {
        it('memanggil updatePackage + publishPackage jika status draft', async () => {
            mockApi.updatePackage.mockImplementation((_id, _fd, opts) =>
                opts.onSuccess?.(),
            );
            mockApi.publishPackage.mockImplementation((_id, opts) =>
                opts.onSuccess?.(),
            );

            renderPage(makePackage({ status: 'draft' }));
            await fireEvent.click(
                screen.getByRole('button', { name: /Simpan Perubahan/i }),
            );
            await syncUI();

            expect(mockApi.updatePackage).toHaveBeenCalledOnce();
            expect(mockApi.publishPackage).toHaveBeenCalledOnce();
        });

        it('hanya updatePackage jika status aktif', async () => {
            mockApi.updatePackage.mockImplementation((_id, _fd, opts) =>
                opts.onSuccess?.(),
            );

            renderPage(makePackage({ status: 'aktif' }));
            await fireEvent.click(
                screen.getByRole('button', { name: /Simpan Perubahan/i }),
            );
            await syncUI();

            expect(mockApi.updatePackage).toHaveBeenCalledOnce();
            expect(mockApi.publishPackage).not.toHaveBeenCalled();
        });

        it('showToast warning jika nama kosong', async () => {
            const { showToast } = await import('@/utils/toast.svelte');
            renderPage(makePackage({ nama: '' }));

            await fireEvent.click(
                screen.getByRole('button', { name: /Simpan Perubahan/i }),
            );
            await syncUI();

            expect(showToast).toHaveBeenCalledWith(
                'Lengkapi Nama Paket dan Durasi Hari.',
                'warning',
            );
            expect(mockApi.updatePackage).not.toHaveBeenCalled();
        });

        it('showToast warning jika itinerary belum lengkap', async () => {
            const { showToast } = await import('@/utils/toast.svelte');
            renderPage(makePackage({ days: [] }));

            await fireEvent.click(
                screen.getByRole('button', { name: /Simpan Perubahan/i }),
            );
            await syncUI();

            expect(showToast).toHaveBeenCalledWith(
                expect.stringContaining('Rencana Kegiatan belum lengkap'),
                'warning',
            );
        });
    });
});
