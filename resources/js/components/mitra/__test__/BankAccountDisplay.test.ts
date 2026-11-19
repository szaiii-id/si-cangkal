import { render, screen, fireEvent } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import BankAccountDisplay from '../BankAccountDisplay.svelte';
import type { BankAccount } from '@/types';

// ==========================================
// MOCK DATA
// ==========================================
const createMockAccount = (overrides: Partial<BankAccount> = {}): BankAccount => ({
    id: 1,
    tipe: 'bank',
    nama_penyedia: 'BCA',
    nomor_rekening: '1234567890',
    nama_pemilik: 'Budi Santoso',
    is_primary: true,
    ...overrides,
});

describe('BankAccountDisplay Component', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    // ==========================================
    // 1. RENDERING DENGAN AKUN
    // ==========================================
    describe('With Accounts', () => {
        it('harus merender nama bank dan nomor rekening yang disensor', () => {
            render(BankAccountDisplay, { 
                props: { accounts: [createMockAccount()], onEdit: vi.fn(), onAdd: vi.fn() } 
            });

            expect(screen.getByText('BCA')).toBeInTheDocument();
            // maskAccountNumber('1234567890') -> '•••• •••• 7890'
            expect(screen.getByText(/•••• •••• 7890/)).toBeInTheDocument();
        });

        it('harus merender nama pemilik', () => {
            render(BankAccountDisplay, { 
                props: { accounts: [createMockAccount()], onEdit: vi.fn(), onAdd: vi.fn() } 
            });

            expect(screen.getByText(/Budi Santoso/)).toBeInTheDocument();
        });

        it('harus menampilkan badge "Utama" jika is_primary=true', () => {
            render(BankAccountDisplay, { 
                props: { accounts: [createMockAccount({ is_primary: true })], onEdit: vi.fn(), onAdd: vi.fn() } 
            });

            expect(screen.getByText('Utama')).toBeInTheDocument();
        });

        it('TIDAK menampilkan badge "Utama" jika is_primary=false', () => {
            render(BankAccountDisplay, { 
                props: { accounts: [createMockAccount({ is_primary: false })], onEdit: vi.fn(), onAdd: vi.fn() } 
            });

            expect(screen.queryByText('Utama')).not.toBeInTheDocument();
        });
    });

    // ==========================================
    // 2. RENDERING TANPA AKUN (EMPTY)
    // ==========================================
    describe('Without Accounts', () => {
        it('harus menampilkan pesan kosong', () => {
            render(BankAccountDisplay, { 
                props: { accounts: [], onEdit: vi.fn(), onAdd: vi.fn() } 
            });

            expect(screen.getByText('Belum ada rekening yang ditambahkan.')).toBeInTheDocument();
        });

        it('harus menampilkan tombol "+ Tambah Rekening"', () => {
            render(BankAccountDisplay, { 
                props: { accounts: [], onEdit: vi.fn(), onAdd: vi.fn() } 
            });

            expect(screen.getByLabelText('Tambah rekening baru')).toBeInTheDocument();
        });
    });

    // ==========================================
    // 3. INTERAKSI
    // ==========================================
    describe('User Interaction', () => {
        it('harus memanggil onEdit saat tombol "Edit Rekening" diklik', async () => {
            const onEdit = vi.fn();
            render(BankAccountDisplay, { 
                props: { accounts: [createMockAccount()], onEdit, onAdd: vi.fn() } 
            });

            await fireEvent.click(screen.getByLabelText('Edit rekening pembayaran'));
            expect(onEdit).toHaveBeenCalledTimes(1);
        });

        it('harus memanggil onAdd saat tombol "+ Tambah Rekening" diklik', async () => {
            const onAdd = vi.fn();
            render(BankAccountDisplay, { 
                props: { accounts: [], onEdit: vi.fn(), onAdd } 
            });

            await fireEvent.click(screen.getByLabelText('Tambah rekening baru'));
            expect(onAdd).toHaveBeenCalledTimes(1);
        });
    });

    // ==========================================
    // 4. AKSESIBILITAS (A11Y)
    // ==========================================
    describe('Accessibility', () => {
        it('ikon SVG dekoratif harus disembunyikan dari screen reader', () => {
            const { container } = render(BankAccountDisplay, { 
                props: { accounts: [createMockAccount()], onEdit: vi.fn(), onAdd: vi.fn() } 
            });
            
            const svgs = container.querySelectorAll('svg');
            svgs.forEach((svg) => {
                expect(svg).toHaveAttribute('aria-hidden', 'true');
            });
        });

        it('empty state harus memiliki role="status"', () => {
            render(BankAccountDisplay, { 
                props: { accounts: [], onEdit: vi.fn(), onAdd: vi.fn() } 
            });

            const status = screen.getByRole('status');
            expect(status).toHaveTextContent('Belum ada rekening yang ditambahkan.');
        });
    });
});