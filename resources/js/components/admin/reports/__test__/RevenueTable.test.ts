import { render, screen } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import RevenueTable from '../RevenueTable.svelte';

// ==========================================
// MOCK — formatCurrency (deterministik)
// ==========================================
vi.mock('@/utils/formatCurrency', () => ({
    formatCurrency: vi.fn((v: number | string) => `Rp ${Number(v).toLocaleString('id-ID')}`),
}));

// ==========================================
// FIXTURE — sesuai tipe `Transaction` (tanpa `id`, `total: number`)
// ==========================================
const createMockTransaction = (overrides = {}) => ({
    kode: 'TRX-001',
    tanggal: '2026-10-16',
    client: 'Budi',
    mitra: 'Mitra Travel',
    paket: 'Paket Bali',
    total: 1500000,
    status: 'Berhasil',
    ...overrides,
});

describe('RevenueTable Component', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    // ==========================================
    // 1. RENDERING DASAR
    // ==========================================
    describe('Rendering', () => {
        it('harus merender judul "Rincian Transaksi"', () => {
            render(RevenueTable, { props: { transactions: [createMockTransaction()] } });
            expect(screen.getByText('Rincian Transaksi')).toBeInTheDocument();
        });

        it('harus merender jumlah transaksi', () => {
            render(RevenueTable, {
                props: {
                    transactions: [
                        createMockTransaction({ kode: 'TRX-001' }),
                        createMockTransaction({ kode: 'TRX-002' }),
                    ],
                },
            });
            expect(screen.getByText('2 transaksi ditemukan')).toBeInTheDocument();
        });

        it('harus merender data transaksi (kode, client, mitra, paket, total)', () => {
            render(RevenueTable, { props: { transactions: [createMockTransaction()] } });

            // Kode, client, mitra, paket, total muncul di mobile & desktop
            expect(screen.getAllByText('TRX-001').length).toBeGreaterThan(0);
            expect(screen.getAllByText('Budi').length).toBeGreaterThan(0);
            expect(screen.getAllByText('Mitra Travel').length).toBeGreaterThan(0);
            expect(screen.getAllByText('Paket Bali').length).toBeGreaterThan(0);
            expect(screen.getAllByText(/Rp 1\.500\.000/).length).toBeGreaterThan(0);
        });
    });

    // ==========================================
    // 2. EMPTY STATE
    // ==========================================
    describe('Empty State', () => {
        it('harus menampilkan pesan kosong di mobile dan desktop', () => {
            render(RevenueTable, { props: { transactions: [] } });

            const emptyMessages = screen.getAllByText('Belum ada transaksi pada periode ini.');
            expect(emptyMessages.length).toBe(2);
        });
    });

    // ==========================================
    // 3. FORMATTING
    // ==========================================
    describe('Formatting', () => {
        it('harus memformat total menjadi Rupiah', () => {
            render(RevenueTable, {
                props: { transactions: [createMockTransaction({ total: 2500000 })] },
            });
            expect(screen.getAllByText(/Rp 2\.500\.000/).length).toBeGreaterThan(0);
        });

        it('harus memanggil formatCurrency untuk setiap transaksi', async () => {
            const { formatCurrency } = await import('@/utils/formatCurrency');
            render(RevenueTable, {
                props: { transactions: [createMockTransaction({ total: 1500000 })] },
            });
            expect(formatCurrency).toHaveBeenCalledWith(1500000);
        });
    });

    // ==========================================
    // 4. AKSESIBILITAS (A11Y)
    // ==========================================
    describe('Accessibility', () => {
        it('tabel harus memiliki aria-label', () => {
            render(RevenueTable, { props: { transactions: [createMockTransaction()] } });

            const table = screen.getByRole('table', { name: /Rincian Transaksi/i });
            expect(table).toBeInTheDocument();
        });

        it('ikon SVG dekoratif harus disembunyikan dari screen reader', () => {
            const { container } = render(RevenueTable, {
                props: { transactions: [createMockTransaction()] },
            });

            const svgs = container.querySelectorAll('svg');
            svgs.forEach((svg) => {
                expect(svg).toHaveAttribute('aria-hidden', 'true');
            });
        });
    });
});