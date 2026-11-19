import { render, screen, fireEvent } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import BankAccountManager from '../BankAccountManager.svelte';

// ==========================================
// MOCK DATA
// ==========================================
const createMockAccount = (overrides: Record<string, unknown> = {}) =>
    ({
        id: crypto.randomUUID(),
        tipe: 'bank' as 'bank' | 'ewallet',
        nama_penyedia: 'BCA',
        nomor_rekening: '1234567890',
        nama_pemilik: 'Budi Santoso',
        ...overrides,
    }) as any;

describe('BankAccountManager Component', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    // ==========================================
    // 1. RENDERING DASAR
    // ==========================================
    describe('Rendering', () => {
        it('harus merender judul dan deskripsi', () => {
            render(BankAccountManager, { props: { accounts: [createMockAccount()], errors: {} } });

            expect(screen.getByText('Rekening Pembayaran')).toBeInTheDocument();
            expect(screen.getByText(/Tambahkan minimal 1 rekening/i)).toBeInTheDocument();
        });

        it('harus merender 1 rekening jika accounts berisi 1 item', () => {
            render(BankAccountManager, { props: { accounts: [createMockAccount()], errors: {} } });

            expect(screen.getByText('Rekening 1')).toBeInTheDocument();
            expect(screen.getByLabelText(/Nama Bank/i)).toBeInTheDocument();
        });
    });

    // ==========================================
    // 2. MENAMBAH REKENING
    // ==========================================
    describe('Adding Account', () => {
        it('harus menambahkan rekening baru saat tombol "Tambah Rekening Lain" diklik', async () => {
            render(BankAccountManager, {
                props: { accounts: [createMockAccount()], errors: {} },
            });

            const addBtn = screen.getByRole('button', { name: /Tambah Rekening Lain/i });
            await fireEvent.click(addBtn);

            // Cek apakah ada 2 rekening sekarang
            expect(screen.getByText('Rekening 2')).toBeInTheDocument();
        });

        it('TIDAK boleh menampilkan tombol tambah jika sudah mencapai MAX_ACCOUNTS (3)', () => {
            const accounts = [
                createMockAccount({ id: '1' }),
                createMockAccount({ id: '2' }),
                createMockAccount({ id: '3' }),
            ];
            render(BankAccountManager, { props: { accounts, errors: {} } });

            expect(screen.queryByRole('button', { name: /Tambah Rekening Lain/i })).not.toBeInTheDocument();
            expect(screen.getByText(/Batas maksimal 3 rekening/i)).toBeInTheDocument();
        });
    });

    // ==========================================
    // 3. MENGHAPUS REKENING
    // ==========================================
    describe('Removing Account', () => {
        it('harus menghapus rekening saat tombol Hapus diklik', async () => {
            const accounts = [
                createMockAccount({ id: '1' }),
                createMockAccount({ id: '2' }),
            ];
            render(BankAccountManager, { props: { accounts, errors: {} } });

            const deleteBtns = screen.getAllByRole('button', { name: /Hapus rekening/i });
            await fireEvent.click(deleteBtns[0]);

            // Cek apakah rekening 2 hilang dan rekening 1 tersisa
            expect(screen.queryByText('Rekening 2')).not.toBeInTheDocument();
            expect(screen.getByText('Rekening 1')).toBeInTheDocument();
        });

        it('TIDAK boleh menampilkan tombol Hapus jika hanya ada 1 rekening', () => {
            render(BankAccountManager, { props: { accounts: [createMockAccount()], errors: {} } });
            expect(screen.queryByRole('button', { name: /Hapus rekening/i })).not.toBeInTheDocument();
        });
    });

    // ==========================================
    // 4. CONDITIONAL DROPDOWN (Bank vs E-Wallet)
    // ==========================================
    describe('Conditional Dropdown', () => {
        it('harus menampilkan opsi Bank jika tipe = bank', () => {
            render(BankAccountManager, {
                props: { accounts: [createMockAccount({ tipe: 'bank' })], errors: {} },
            });

            const select = screen.getByLabelText(/Nama Bank/i) as HTMLSelectElement;
            const options = Array.from(select.options).map((o) => o.value);

            expect(options).toContain('BCA');
            expect(options).not.toContain('DANA');
        });

        it('harus menampilkan opsi E-Wallet jika tipe = ewallet', () => {
            render(BankAccountManager, {
                props: { accounts: [createMockAccount({ tipe: 'ewallet' })], errors: {} },
            });

            const select = screen.getByLabelText(/Nama E-Wallet/i) as HTMLSelectElement;
            const options = Array.from(select.options).map((o) => o.value);

            expect(options).toContain('DANA');
            expect(options).not.toContain('BCA');
        });
    });

    // ==========================================
    // 5. ERROR STATE
    // ==========================================
    describe('Error State', () => {
        it('harus menampilkan pesan error untuk nama penyedia jika ada', () => {
            render(BankAccountManager, {
                props: {
                    accounts: [createMockAccount()],
                    errors: { 'accounts.0.nama_penyedia': 'Pilih bank terlebih dahulu' },
                },
            });

            expect(screen.getByText('Pilih bank terlebih dahulu')).toBeInTheDocument();
        });
    });
});