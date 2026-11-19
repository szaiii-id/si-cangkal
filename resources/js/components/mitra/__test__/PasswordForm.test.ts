import { render, screen, fireEvent, waitFor } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import PasswordForm from '../PasswordForm.svelte';

// ==========================================
// MOCK API & TOAST
// ==========================================
vi.mock('@/api/mitra', () => ({
    updatePassword: vi.fn(),
}));

vi.mock('@/utils/toast.svelte', () => ({
    showToast: vi.fn(),
}));

import { updatePassword } from '@/api/mitra';
import { showToast } from '@/utils/toast.svelte';

describe('PasswordForm Component', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    // ==========================================
    // 1. RENDERING DASAR
    // ==========================================
    describe('Rendering', () => {
        it('harus merender judul dan tiga input password', () => {
            render(PasswordForm);

            expect(screen.getByText('Form Ganti Password')).toBeInTheDocument();
            
            // PERBAIKAN: Gunakan placeholder yang unik
            expect(screen.getByPlaceholderText('Masukkan password lama Anda')).toBeInTheDocument();
            expect(screen.getByPlaceholderText('Minimal 8 karakter')).toBeInTheDocument();
            expect(screen.getByPlaceholderText('Ulangi password baru Anda')).toBeInTheDocument();
        });
    });

    // ==========================================
    // 2. VALIDASI PASSWORD
    // ==========================================
    describe('Password Validation', () => {
        it('harus menampilkan kriteria jika password diisi', async () => {
            render(PasswordForm);
            
            // PERBAIKAN: Gunakan placeholder
            const passwordInput = screen.getByPlaceholderText('Minimal 8 karakter');
            await fireEvent.input(passwordInput, { target: { value: 'abc' } });

            expect(screen.getByText('Min. 8 karakter')).toBeInTheDocument();
            expect(screen.getByText('Huruf')).toBeInTheDocument();
            expect(screen.getByText('Angka')).toBeInTheDocument();
            expect(screen.getByText('Simbol')).toBeInTheDocument();
        });

        it('harus menampilkan error jika konfirmasi password tidak cocok', async () => {
            render(PasswordForm);
            
            const passwordInput = screen.getByPlaceholderText('Minimal 8 karakter');
            await fireEvent.input(passwordInput, { target: { value: 'password123' } });

            const confirmInput = screen.getByPlaceholderText('Ulangi password baru Anda');
            await fireEvent.input(confirmInput, { target: { value: 'password456' } });

            await waitFor(() => {
                expect(screen.getByText('Konfirmasi password tidak cocok.')).toBeInTheDocument();
            });
        });

        it('tombol submit harus disabled jika password tidak cocok', async () => {
            render(PasswordForm);
            
            const passwordInput = screen.getByPlaceholderText('Minimal 8 karakter');
            await fireEvent.input(passwordInput, { target: { value: 'password123' } });

            const confirmInput = screen.getByPlaceholderText('Ulangi password baru Anda');
            await fireEvent.input(confirmInput, { target: { value: 'password456' } });

            const submitBtn = screen.getByText('Simpan Password Baru');
            expect(submitBtn).toBeDisabled();
        });
    });

    // ==========================================
    // 3. SUBMIT FORM
    // ==========================================
    describe('Form Submission', () => {
        it('harus memanggil updatePassword saat form disubmit', async () => {
            render(PasswordForm);
            
            const oldInput = screen.getByPlaceholderText('Masukkan password lama Anda');
            await fireEvent.input(oldInput, { target: { value: 'oldpass' } });

            const passwordInput = screen.getByPlaceholderText('Minimal 8 karakter');
            await fireEvent.input(passwordInput, { target: { value: 'newpassword123!' } });

            const confirmInput = screen.getByPlaceholderText('Ulangi password baru Anda');
            await fireEvent.input(confirmInput, { target: { value: 'newpassword123!' } });

            const form = document.querySelector('form');
            await fireEvent.submit(form!);

            expect(updatePassword).toHaveBeenCalledWith(
                {
                    old_password: 'oldpass',
                    password: 'newpassword123!',
                    password_confirmation: 'newpassword123!',
                },
                expect.any(Object)
            );
        });
    });

    // ==========================================
    // 4. AKSESIBILITAS (A11Y)
    // ==========================================
    describe('Accessibility', () => {
        it('ikon SVG dekoratif harus disembunyikan dari screen reader', () => {
            const { container } = render(PasswordForm);
            
            const svgs = container.querySelectorAll('svg');
            svgs.forEach((svg) => {
                expect(svg).toHaveAttribute('aria-hidden', 'true');
            });
        });
    });
});