/**
 * ============================================================
 * Client Bookings — Create Page (Form Pemesanan) — Component Test
 * ============================================================
 *
 * Fokus pengujian:
 *   1. Isolation & Mocking  → Inertia (router.post), format helpers,
 *                             timezone, toast, ClientLayout.
 *   2. Rendering            → Step 1 (Info), Stepper, Error Alert.
 *   3. Interaction          → Validasi Step 1, sinkronisasi peserta,
 *                             kembali ke Step 1, submit.
 *   4. Reactivity           → Total harga conditional (rombongan vs per orang).
 * ============================================================
 */

import { render, screen, fireEvent, within } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import Create from '../Create.svelte';

// ============================================================
// 1. HOISTED MOCK REGISTRY
// ============================================================
const mockRouterPost = vi.hoisted(() => vi.fn());

// ============================================================
// 2. MOCK — INERTIA
// ============================================================
vi.mock('@inertiajs/svelte', () => ({
    inertia: vi.fn(),
    router: { post: mockRouterPost },
}));

// ============================================================
// 3. MOCK — UTILS
// ============================================================
vi.mock('@/utils/formatCurrency', () => ({
    formatCurrency: vi.fn((v: number | string) => `Rp ${v}`),
}));

vi.mock('@/utils/toast.svelte', () => ({
    showToast: vi.fn(),
}));

vi.mock('@/utils/timezone', () => ({
    todayInTimezone: vi.fn(() => '2026-01-01'), // Fixed date untuk test konsisten
}));

// ============================================================
// 4. MOCK — CLIENT LAYOUT
// ============================================================
vi.mock('@/components/layout/ClientLayout.svelte', () => ({
    default: (anchor: Node, props: { children?: (anchor: Node) => void }) => {
        props.children?.(anchor);
        return {};
    },
}));

// ============================================================
// 5. FIXTURES
// ============================================================
const makePackage = (overrides = {}) => ({
    id: 1,
    kode: 'PKT-001',
    nama: 'Paket Bali',
    slug: 'paket-bali',
    deskripsi: 'Liburan seru',
    kategori: 'wisata_alam',
    durasi_hari: 3,
    durasi_malam: 2,
    harga_per_orang: '750000',
    harga_per_rombongan: null,
    max_peserta: 10,
    timezone: 'Asia/Jakarta',
    facilities: [],
    ...overrides,
});

const renderPage = (pkg = makePackage()) =>
    render(Create, { props: { package: pkg } });

// Helper untuk scroll ke step 2 (karena ada validasi lokal)
async function goToStep2() {
    const dateInput = screen.getByLabelText(/Tanggal Keberangkatan/i);
    await fireEvent.input(dateInput, { target: { value: '2026-01-10' } });

    const qtyInput = screen.getByLabelText(/Jumlah Peserta/i);
    await fireEvent.input(qtyInput, { target: { value: '2' } });

    const nextBtn = screen.getByRole('button', { name: /Lanjut Isi Peserta/i });
    await fireEvent.click(nextBtn);
}

// ============================================================
// 6. TEST SUITE
// ============================================================
describe('Client Bookings — Create Page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    // ----------------------------------------------------------
    // 6.1 RENDERING — Step 1 (Info Pemesanan)
    // ----------------------------------------------------------
    describe('Rendering — Step 1', () => {
        it('menampilkan form info pemesanan secara default', () => {
            renderPage();
            
            expect(screen.getByRole('heading', { name: 'Info Pemesanan' })).toBeInTheDocument();
            expect(screen.getByLabelText(/Tanggal Keberangkatan/i)).toBeInTheDocument();
            expect(screen.getByLabelText(/Jumlah Peserta/i)).toBeInTheDocument();
            expect(screen.getByRole('button', { name: /Lanjut Isi Peserta/i })).toBeInTheDocument();
        });

        it('menampilkan info minimal H+1 (berdasarkan timezone)', () => {
            renderPage();
            // todayInTimezone di-mock '2026-01-01', + 1 hari = '2026-01-02'
            expect(screen.getByText(/Tersedia mulai:/i)).toHaveTextContent(/2 Januari 2026/i);
        });

        it('menampilkan error alert jika validasi step 1 gagal', async () => {
            renderPage();
            
            // Kosongkan field & submit
            await fireEvent.click(screen.getByRole('button', { name: /Lanjut Isi Peserta/i }));
            
            expect(screen.getByRole('alert')).toHaveTextContent(/Terdapat 1 kesalahan pada form/i);
        });
    });

    // ----------------------------------------------------------
    // 6.2 INTERACTION — Step 1 Validation & Navigation
    // ----------------------------------------------------------
    describe('Interaction — Step 1', () => {
        it('tidak lanjut ke step 2 jika tanggal kosong', async () => {
            renderPage();
            
            // Isi hanya qty
            const qtyInput = screen.getByLabelText(/Jumlah Peserta/i);
            await fireEvent.input(qtyInput, { target: { value: '2' } });

            await fireEvent.click(screen.getByRole('button', { name: /Lanjut Isi Peserta/i }));
            
            // Tetap di step 1
            expect(screen.queryByRole('heading', { name: 'Data Peserta' })).not.toBeInTheDocument();
        });

        it('lanjut ke step 2 jika form valid & generate field peserta sesuai qty', async () => {
            renderPage();
            await goToStep2();

            // Step 2 muncul
            expect(screen.getByRole('heading', { name: 'Data Peserta' })).toBeInTheDocument();
            
            // Cek jumlah field nama peserta (qty=2)
            expect(screen.getAllByLabelText(/Nama Lengkap/i)).toHaveLength(2);
        });
    });

    // ----------------------------------------------------------
    // 6.3 RENDERING — Step 2 (Data Peserta)
    // ----------------------------------------------------------
    describe('Rendering — Step 2', () => {
        it('menampilkan ringkasan pemesanan', async () => {
            renderPage();
            await goToStep2();

            expect(screen.getByText('Paket Bali')).toBeInTheDocument();
            expect(screen.getByText('2 orang')).toBeInTheDocument();
            // Format tanggal: "Sabtu, 10 Januari 2026"
            expect(screen.getByText(/10 Januari 2026/i)).toBeInTheDocument();
        });

        it('menampilkan total harga per orang (qty * harga_per_orang)', async () => {
            renderPage();
            await goToStep2(); // qty 2

            // 750.000 * 2 = 1.500.000
            expect(screen.getByText('Rp 1500000')).toBeInTheDocument();
        });

        it('menampilkan total harga rombongan (jika di-set)', async () => {
            // Harga rombongan ada, tapi min 2 orang
            renderPage(makePackage({ harga_per_rombongan: '3000000' }));
            await goToStep2(); // qty 2

            // Harus pakai harga rombongan, bukan per orang * qty
            expect(screen.getByText('Rp 3000000')).toBeInTheDocument();
        });
    });

    // ----------------------------------------------------------
    // 6.4 INTERACTION — Back to Step 1
    // ----------------------------------------------------------
    describe('Interaction — Back Navigation', () => {
        it('klik "Ubah Info" kembali ke step 1', async () => {
            renderPage();
            await goToStep2();

            const ubahBtn = screen.getByRole('button', { name: 'Ubah Info' });
            await fireEvent.click(ubahBtn);

            expect(screen.getByRole('heading', { name: 'Info Pemesanan' })).toBeInTheDocument();
            expect(screen.queryByRole('heading', { name: 'Data Peserta' })).not.toBeInTheDocument();
        });
    });

    // ----------------------------------------------------------
    // 6.5 INTERACTION — Submit Booking
    // ----------------------------------------------------------
    describe('Interaction — Submit', () => {
        it('validasi nama peserta (min 2 karakter) sebelum submit', async () => {
            renderPage();
            await goToStep2();

            // Isi nama peserta 1 dengan 1 karakter (invalid)
            const names = screen.getAllByLabelText(/Nama Lengkap/i);
            await fireEvent.input(names[0], { target: { value: 'A' } });
            await fireEvent.input(names[1], { target: { value: 'Budi' } });

            const submitBtn = screen.getByRole('button', { name: /Konfirmasi Pesanan/i });
            await fireEvent.click(submitBtn);

            // Error muncul, router.post tidak dipanggil
            expect(screen.getByText('Nama peserta minimal 2 karakter.')).toBeInTheDocument();
            expect(mockRouterPost).not.toHaveBeenCalled();
        });

        it('memanggil router.post dengan payload benar jika valid', async () => {
            renderPage();
            await goToStep2();

            // Isi semua nama valid
            const names = screen.getAllByLabelText(/Nama Lengkap/i);
            await fireEvent.input(names[0], { target: { value: 'Budi' } });
            await fireEvent.input(names[1], { target: { value: 'Ani' } });

            const submitBtn = screen.getByRole('button', { name: /Konfirmasi Pesanan/i });
            await fireEvent.click(submitBtn);

            expect(mockRouterPost).toHaveBeenCalledOnce();
            expect(mockRouterPost).toHaveBeenCalledWith(
                '/client/bookings/1',
                expect.objectContaining({
                    tanggal_keberangkatan: '2026-01-10',
                    jumlah_peserta: 2,
                    participants: [
                        expect.objectContaining({ nama: 'Budi' }),
                        expect.objectContaining({ nama: 'Ani' }),
                    ],
                }),
                expect.any(Object)
            );
        });
    });
});