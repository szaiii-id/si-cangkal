/**
 * ============================================================
 * Mitra Packages — CompletePhase2 Page — Component Test
 * ============================================================
 */

import { render, screen, fireEvent } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { tick } from 'svelte';
import CompletePhase2 from '../CompletePhase2.svelte';

// ============================================================
// 1. MOCKS
// ============================================================
const mockApi = vi.hoisted(() => ({
    updatePackage: vi.fn(),
    publishPackage: vi.fn(),
}));

vi.mock('@/api/mitra', () => mockApi);

vi.mock('@/utils/toast.svelte', () => ({
    showToast: vi.fn(),
}));

vi.mock('@/utils/menus', () => ({
    mitraMenu: [],
}));

vi.mock('@/utils/formatCurrency', () => ({
    formatCurrency: vi.fn((v: string | number) => `Rp ${v}`),
}));

vi.mock('@inertiajs/svelte', () => ({
    inertia: vi.fn(),
}));

// ============================================================
// 2. MOCK CHILD COMPONENTS
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
// 3. HELPERS
// ============================================================
async function syncUI() {
    await tick();
}

const makePackage = (overrides = {}) => ({
    id: 1,
    nama: 'Paket Bali',
    deskripsi: 'Wisata Bali',
    kategori: 'wisata_alam',
    durasi_hari: 2,
    durasi_malam: 1,
    harga_per_orang: '750000',
    harga_per_rombongan: null,
    max_peserta: null,
    timezone: 'Asia/Makassar',
    cover_image: null,
    status: 'draft',
    days: [
        { id: 1, hari_ke: 1, judul: 'Day 1', deskripsi: 'Desc 1', jam_mulai: '08:00', jam_selesai: '17:00', lokasi: 'Bali' },
        { id: 2, hari_ke: 2, judul: 'Day 2', deskripsi: 'Desc 2', jam_mulai: '08:00', jam_selesai: '17:00', lokasi: 'Bali' },
    ],
    facilities: [
        { id: 1, tipe: 'include', nama: 'Hotel' },
        { id: 2, tipe: 'exclude', nama: 'Tiket Pesawat' },
    ],
    ...overrides,
});

const renderPage = (pkg = makePackage()) =>
    render(CompletePhase2, { props: { package: pkg } });

/** Render halaman dan langsung pindah ke step 2 (Fasilitas). */
async function goToStep2(pkg = makePackage()) {
    const result = render(CompletePhase2, { props: { package: pkg } });
    await fireEvent.click(screen.getByRole('button', { name: /Lanjut/i }));
    await syncUI();
    return result;
}

// ============================================================
// 4. TEST SUITE
// ============================================================
describe('Mitra Packages — CompletePhase2 Page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    describe('Rendering', () => {
        it('menampilkan badge "Fase 2: Kelengkapan & Terbitkan"', () => {
            renderPage();
            expect(screen.getByText('Fase 2: Kelengkapan & Terbitkan')).toBeInTheDocument();
        });

        it('menampilkan stepper dengan 3 label step', () => {
            renderPage();
            expect(screen.getByText('Rencana Kegiatan')).toBeInTheDocument();
            expect(screen.getByText('Fasilitas')).toBeInTheDocument();
            expect(screen.getByText('Review & Terbit')).toBeInTheDocument();
        });

        it('menampilkan DaysEditor pada step 1 (default)', () => {
            renderPage();
            expect(screen.getByTestId('days-editor')).toBeInTheDocument();
            expect(screen.getByText(/Paket Anda berdurasi 2 hari/i)).toBeInTheDocument();
        });

        it('menampilkan tombol Batal di step 1', () => {
            renderPage();
            expect(screen.getByRole('link', { name: /Batal/i })).toBeInTheDocument();
        });
    });

    describe('Interaction — Step 1', () => {
        it('nextStep blocked jika days.length < durasi_hari', async () => {
            renderPage(makePackage({ days: [] }));
            await fireEvent.click(screen.getByRole('button', { name: /Lanjut/i }));
            await syncUI();

            const { showToast } = await import('@/utils/toast.svelte');
            expect(showToast).toHaveBeenCalledWith(
                expect.stringContaining('Rencana Kegiatan belum lengkap'),
                'warning',
            );
        });

        it('nextStep berhasil jika days lengkap → masuk step 2', async () => {
            renderPage();
            await fireEvent.click(screen.getByRole('button', { name: /Lanjut/i }));
            await syncUI();

            expect(screen.getByTestId('facilities-editor')).toBeInTheDocument();
            expect(screen.queryByTestId('days-editor')).not.toBeInTheDocument();
        });
    });

    describe('Interaction — Step 2 (Fasilitas)', () => {
        it('nextStep blocked jika tidak ada include', async () => {
            await goToStep2(makePackage({ facilities: [{ id: 1, tipe: 'exclude', nama: 'X' }] }));
            await fireEvent.click(screen.getByRole('button', { name: /Lanjut/i }));
            await syncUI();

            const { showToast } = await import('@/utils/toast.svelte');
            expect(showToast).toHaveBeenCalledWith(
                'Harap isi minimal 1 fasilitas Termasuk dan 1 Tidak Termasuk.',
                'warning',
            );
        });

        it('nextStep blocked jika tidak ada exclude', async () => {
            await goToStep2(makePackage({ facilities: [{ id: 1, tipe: 'include', nama: 'Y' }] }));
            await fireEvent.click(screen.getByRole('button', { name: /Lanjut/i }));
            await syncUI();

            const { showToast } = await import('@/utils/toast.svelte');
            expect(showToast).toHaveBeenCalledWith(
                'Harap isi minimal 1 fasilitas Termasuk dan 1 Tidak Termasuk.',
                'warning',
            );
        });

        it('nextStep berhasil jika include & exclude ada → masuk step 3', async () => {
            await goToStep2();
            await fireEvent.click(screen.getByRole('button', { name: /Lanjut/i }));
            await syncUI();

            expect(screen.getByText('Paket Siap Terbit!')).toBeInTheDocument();
            expect(screen.getByText('Ringkasan Paket')).toBeInTheDocument();
        });

        it('tombol Kembali kembali ke step 1', async () => {
            await goToStep2();
            await fireEvent.click(screen.getByRole('button', { name: /Kembali/i }));
            await syncUI();

            expect(screen.getByTestId('days-editor')).toBeInTheDocument();
        });
    });

    describe('Rendering — Step 3 (Review)', () => {
        it('menampilkan ringkasan paket', async () => {
            await goToStep2();
            await fireEvent.click(screen.getByRole('button', { name: /Lanjut/i }));
            await syncUI();

            expect(screen.getByText('Paket Bali')).toBeInTheDocument();
            expect(screen.getByText('2 Hari 1 Malam')).toBeInTheDocument();
            expect(screen.getByText('2 Hari (Lengkap)')).toBeInTheDocument();
            expect(screen.getByText('Rp 750000 / orang')).toBeInTheDocument();
        });

        it('menampilkan tombol Simpan & Terbitkan', async () => {
            await goToStep2();
            await fireEvent.click(screen.getByRole('button', { name: /Lanjut/i }));
            await syncUI();

            expect(screen.getByRole('button', { name: /Simpan & Terbitkan/i })).toBeInTheDocument();
        });
    });

    describe('Interaction — Submit', () => {
        it('memanggil updatePackage + publishPackage (draft)', async () => {
            mockApi.updatePackage.mockImplementation((_id, _fd, opts) => opts.onSuccess?.());
            mockApi.publishPackage.mockImplementation((_id, opts) => opts.onSuccess?.());

            await goToStep2();
            await fireEvent.click(screen.getByRole('button', { name: /Lanjut/i }));
            await syncUI();
            await fireEvent.click(screen.getByRole('button', { name: /Simpan & Terbitkan/i }));
            await syncUI();

            expect(mockApi.updatePackage).toHaveBeenCalledOnce();
            expect(mockApi.publishPackage).toHaveBeenCalledOnce();
        });

        it('hanya updatePackage jika status aktif', async () => {
            mockApi.updatePackage.mockImplementation((_id, _fd, opts) => opts.onSuccess?.());

            await goToStep2(makePackage({ status: 'aktif' }));
            await fireEvent.click(screen.getByRole('button', { name: /Lanjut/i }));
            await syncUI();
            await fireEvent.click(screen.getByRole('button', { name: /Simpan & Terbitkan/i }));
            await syncUI();

            expect(mockApi.updatePackage).toHaveBeenCalledOnce();
            expect(mockApi.publishPackage).not.toHaveBeenCalled();
        });
    });
});