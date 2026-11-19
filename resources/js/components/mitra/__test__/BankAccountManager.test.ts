import { render, screen, fireEvent } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import BankAccountManager from '../BankAccountManager.svelte';

// ==========================================
// MOCK CHILD COMPONENTS (Mengarah ke file fisik)
// ==========================================
vi.mock('../BankAccountDisplay.svelte', () => import('../__mocks__/BankAccountDisplay.svelte'));
vi.mock('../ConfirmPasswordModal.svelte', () => import('../__mocks__/ConfirmPasswordModal.svelte'));
vi.mock('../BankAccountFormModal.svelte', () => import('../__mocks__/BankAccountFormModal.svelte'));

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
import { showToast } from '@/utils/toast.svelte';

describe('BankAccountManager Component', () => {
    const mockAccounts = [
        {
            id: 1,
            tipe: 'bank' as const,
            nama_penyedia: 'BCA',
            nomor_rekening: '1234567890',
            nama_pemilik: 'Budi Santoso',
            is_primary: true,
        },
    ];

    beforeEach(() => {
        vi.clearAllMocks();
        
        // PERBAIKAN: Mock implementation untuk memicu onSuccess dan onFinish
        (updateBankAccounts as any).mockImplementation((_payload: any, options: any) => {
            options.onSuccess?.();
            options.onFinish?.();
        });
    });

    // ==========================================
    // 1. RENDERING DASAR
    // ==========================================
    describe('Rendering', () => {
        it('harus merender BankAccountDisplay', () => {
            render(BankAccountManager, { props: { bankAccounts: mockAccounts } });
            expect(screen.getByTestId('bank-account-display')).toBeInTheDocument();
        });
    });

    // ==========================================
    // 2. ALUR KONFIRMASI PASSWORD
    // ==========================================
    describe('Password Flow', () => {
        it('harus membuka modal password saat tombol Edit diklik', async () => {
            render(BankAccountManager, { props: { bankAccounts: mockAccounts } });
            
            const editBtn = screen.getByTestId('edit-btn');
            await fireEvent.click(editBtn);

            expect(screen.getByTestId('confirm-password-modal')).toBeInTheDocument();
        });

        it('harus membuka modal form setelah password dikonfirmasi', async () => {
            render(BankAccountManager, { props: { bankAccounts: mockAccounts } });
            
            await fireEvent.click(screen.getByTestId('edit-btn'));
            await fireEvent.click(screen.getByTestId('confirm-btn'));

            expect(screen.getByTestId('bank-account-form-modal')).toBeInTheDocument();
        });
    });

    // ==========================================
    // 3. SIMPAN PERUBAHAN
    // ==========================================
    describe('Save Changes', () => {
        it('harus memanggil updateBankAccounts dan showToast success saat simpan', async () => {
            render(BankAccountManager, { props: { bankAccounts: mockAccounts } });

            // Simulasi alur: buka password modal, konfirmasi, lalu simpan
            await fireEvent.click(screen.getByTestId('edit-btn'));
            await fireEvent.click(screen.getByTestId('confirm-btn'));
            await fireEvent.click(screen.getByTestId('save-btn'));

            expect(updateBankAccounts).toHaveBeenCalledTimes(1);
            expect(showToast).toHaveBeenCalledWith('Data rekening berhasil diperbarui.', 'success');
        });
    });
});