import { render, screen, fireEvent } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import BankAccountFormModal from '../BankAccountFormModal.svelte';

// ==========================================
// MOCK DATA
// ==========================================
const createMockAccount = (overrides = {}) => ({
    tipe: 'bank' as const,
    namaPenyedia: 'BCA',
    nomorRekening: '1234567890',
    namaPemilik: 'Budi Santoso',
    ...overrides,
});

describe('BankAccountFormModal Component', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    // ==========================================
    // 1. RENDERING DASAR
    // ==========================================
    describe('Rendering', () => {
        it('TIDAK boleh merender apa pun jika open=false', () => {
            render(BankAccountFormModal, { 
                props: { open: false, accounts: [], onSave: vi.fn() } 
            });
            expect(screen.queryByText('Edit Rekening Pembayaran')).not.toBeInTheDocument();
        });

        it('harus merender modal jika open=true', () => {
            render(BankAccountFormModal, { 
                props: { open: true, accounts: [createMockAccount()], onSave: vi.fn() } 
            });
            
            expect(screen.getByText('Edit Rekening Pembayaran')).toBeInTheDocument();
            expect(screen.getByText('Rekening 1')).toBeInTheDocument();
        });

        it('harus merender pesan error password jika ada', () => {
            render(BankAccountFormModal, { 
                props: { 
                    open: true, 
                    accounts: [createMockAccount()], 
                    onSave: vi.fn(),
                    errors: { password: 'Password salah' }
                } 
            });
            
            expect(screen.getByText('Password salah')).toBeInTheDocument();
        });
    });

    // ==========================================
    // 2. TAMBAH/HAPUS REKENING
    // ==========================================
    describe('Add/Remove Account', () => {
        it('harus menambah form rekening baru saat tombol "+ Tambah Rekening Lain" diklik', async () => {
            render(BankAccountFormModal, { 
                props: { open: true, accounts: [createMockAccount()], onSave: vi.fn() } 
            });
            
            await fireEvent.click(screen.getByLabelText('Tambah rekening lain'));
            expect(screen.getByText('Rekening 2')).toBeInTheDocument();
        });

        it('harus menghapus form rekening saat tombol "Hapus" diklik', async () => {
            render(BankAccountFormModal, { 
                props: { open: true, accounts: [createMockAccount()], onSave: vi.fn() } 
            });
            
            await fireEvent.click(screen.getByLabelText('Tambah rekening lain'));
            expect(screen.getByText('Rekening 2')).toBeInTheDocument();

            const deleteBtns = screen.getAllByLabelText(/Hapus rekening/);
            await fireEvent.click(deleteBtns[1]);
            
            expect(screen.queryByText('Rekening 2')).not.toBeInTheDocument();
        });
    });

    // ==========================================
    // 3. INTERAKSI
    // ==========================================
    describe('User Interaction', () => {
        it('harus memanggil onSave saat tombol "Simpan Rekening" diklik', async () => {
            const onSave = vi.fn();
            render(BankAccountFormModal, { 
                props: { open: true, accounts: [createMockAccount()], onSave } 
            });
            
            await fireEvent.click(screen.getByText('Simpan Rekening'));
            expect(onSave).toHaveBeenCalledTimes(1);
        });

        it('harus menutup modal saat tombol "Batal" diklik', async () => {
            render(BankAccountFormModal, { 
                props: { open: true, accounts: [createMockAccount()], onSave: vi.fn() } 
            });
            
            // Modal awalnya ada
            expect(screen.getByText('Edit Rekening Pembayaran')).toBeInTheDocument();
            
            // Klik tombol Batal
            await fireEvent.click(screen.getByText('Batal'));
            
            // Modal harus hilang dari DOM
            expect(screen.queryByText('Edit Rekening Pembayaran')).not.toBeInTheDocument();
        });
    });

    // ==========================================
    // 4. AKSESIBILITAS (A11Y)
    // ==========================================
    describe('Accessibility', () => {
        it('ikon SVG dekoratif harus disembunyikan dari screen reader', () => {
            const { container } = render(BankAccountFormModal, { 
                props: { open: true, accounts: [createMockAccount()], onSave: vi.fn() } 
            });
            
            const svgs = container.querySelectorAll('svg');
            svgs.forEach((svg) => {
                expect(svg).toHaveAttribute('aria-hidden', 'true');
            });
        });
    });
});