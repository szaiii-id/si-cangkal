import { render, screen, fireEvent } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import BankAccountForm from '../BankAccountForm.svelte';

// ==========================================
// MOCK API & TOAST
// ==========================================
vi.mock('@/api/mitra', () => ({
    updateBankAccounts: vi.fn(),
}));

vi.mock('@/utils/toast.svelte', () => ({
    showToast: vi.fn(),
}));

import { updateBankAccounts } from '@/api/mitra';

describe('BankAccountForm Component', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    // ==========================================
    // 1. RENDERING DASAR
    // ==========================================
    describe('Rendering', () => {
        it('harus merender judul "Rekening Pembayaran"', () => {
            render(BankAccountForm);
            expect(screen.getByText('Rekening Pembayaran')).toBeInTheDocument();
        });

        it('harus merender satu form rekening default', () => {
            render(BankAccountForm);
            expect(screen.getByText('Rekening 1')).toBeInTheDocument();
            expect(screen.getByText('(Utama)')).toBeInTheDocument();
        });

        it('harus merender input password', () => {
            render(BankAccountForm);
            expect(screen.getByLabelText(/Konfirmasi Password/i)).toBeInTheDocument();
        });
    });

    // ==========================================
    // 2. TAMBAH/HAPUS REKENING
    // ==========================================
    describe('Add/Remove Account', () => {
        it('harus menambah form rekening baru saat tombol "+ Tambah Rekening Lain" diklik', async () => {
            render(BankAccountForm);
            
            const addBtn = screen.getByLabelText('Tambah rekening lain');
            await fireEvent.click(addBtn);

            expect(screen.getByText('Rekening 2')).toBeInTheDocument();
        });

        it('harus menghapus form rekening saat tombol "Hapus" diklik', async () => {
            render(BankAccountForm);
            
            // Tambah dulu
            await fireEvent.click(screen.getByLabelText('Tambah rekening lain'));
            expect(screen.getByText('Rekening 2')).toBeInTheDocument();

            // Hapus rekening kedua
            const deleteBtns = screen.getAllByLabelText(/Hapus rekening/);
            await fireEvent.click(deleteBtns[1]);

            expect(screen.queryByText('Rekening 2')).not.toBeInTheDocument();
        });

        it('TIDAK boleh menghapus rekening jika hanya tersisa 1', async () => {
            render(BankAccountForm);
            
            // Tombol hapus tidak muncul saat hanya 1 rekening
            expect(screen.queryByLabelText(/Hapus rekening/)).not.toBeInTheDocument();
        });
    });

    // ==========================================
    // 3. SUBMIT FORM
    // ==========================================
    describe('Form Submission', () => {
        it('harus memanggil updateBankAccounts saat form disubmit', async () => {
            render(BankAccountForm);
            
            const passwordInput = screen.getByLabelText(/Konfirmasi Password/i);
            await fireEvent.input(passwordInput, { target: { value: 'password123' } });

            const form = document.querySelector('form');
            await fireEvent.submit(form!);

            expect(updateBankAccounts).toHaveBeenCalledWith(
                expect.objectContaining({ password: 'password123' }),
                expect.any(Object)
            );
        });
    });

    // ==========================================
    // 4. AKSESIBILITAS (A11Y)
    // ==========================================
    describe('Accessibility', () => {
        it('ikon SVG dekoratif harus disembunyikan dari screen reader', () => {
            const { container } = render(BankAccountForm);
            
            const svgs = container.querySelectorAll('svg');
            svgs.forEach((svg) => {
                expect(svg).toHaveAttribute('aria-hidden', 'true');
            });
        });
    });
});