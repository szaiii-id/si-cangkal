import { render, screen, fireEvent } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import ConfirmPasswordModal from '../ConfirmPasswordModal.svelte';

// ==========================================
// MOCK CHILD COMPONENT (ConfirmModal)
// ==========================================
vi.mock(
    '@/global/ConfirmModal.svelte',
    () => import('@/global/__mocks__/ConfirmModal.svelte'),
);

describe('ConfirmPasswordModal Component', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    // ==========================================
    // 1. RENDERING DASAR
    // ==========================================
    describe('Rendering', () => {
        it('TIDAK boleh merender apa pun jika open=false', () => {
            render(ConfirmPasswordModal, {
                props: { open: false, onConfirm: vi.fn() },
            });
            expect(
                screen.queryByTestId('confirm-modal'),
            ).not.toBeInTheDocument();
        });

        it('harus merender modal dan input password jika open=true', () => {
            render(ConfirmPasswordModal, {
                props: { open: true, onConfirm: vi.fn() },
            });

            expect(screen.getByTestId('confirm-modal')).toBeInTheDocument();
            expect(screen.getByLabelText('Password Akun')).toBeInTheDocument();
        });
    });

    // ==========================================
    // 2. VALIDASI PASSWORD
    // ==========================================
    describe('Password Validation', () => {
        it('harus menampilkan error jika password kurang dari 5 karakter', async () => {
            render(ConfirmPasswordModal, {
                props: { open: true, onConfirm: vi.fn() },
            });

            const input = screen.getByLabelText('Password Akun');
            await fireEvent.input(input, { target: { value: 'abc' } });

            const confirmBtn = screen.getByTestId('confirm-btn');
            await fireEvent.click(confirmBtn);

            expect(screen.getByRole('alert')).toHaveTextContent(
                'Password wajib diisi dan minimal 5 karakter.',
            );
        });

        it('TIDAK boleh memanggil onConfirm jika password kurang dari 5 karakter', async () => {
            const onConfirm = vi.fn();
            render(ConfirmPasswordModal, {
                props: { open: true, onConfirm },
            });

            const input = screen.getByLabelText('Password Akun');
            await fireEvent.input(input, { target: { value: 'abc' } });

            const confirmBtn = screen.getByTestId('confirm-btn');
            await fireEvent.click(confirmBtn);

            expect(onConfirm).not.toHaveBeenCalled();
        });
    });

    // ==========================================
    // 3. SUBMIT PASSWORD VALID
    // ==========================================
    describe('Submit Password', () => {
        it('harus memanggil onConfirm dengan password yang valid', async () => {
            const onConfirm = vi.fn();
            render(ConfirmPasswordModal, {
                props: { open: true, onConfirm },
            });

            const input = screen.getByLabelText('Password Akun');
            await fireEvent.input(input, { target: { value: 'password123' } });

            const confirmBtn = screen.getByTestId('confirm-btn');
            await fireEvent.click(confirmBtn);

            expect(onConfirm).toHaveBeenCalledWith('password123');
        });

        it('harus memanggil onConfirm saat menekan Enter', async () => {
            const onConfirm = vi.fn();
            render(ConfirmPasswordModal, {
                props: { open: true, onConfirm },
            });

            const input = screen.getByLabelText('Password Akun');
            await fireEvent.input(input, { target: { value: 'password123' } });
            await fireEvent.keyDown(input, { key: 'Enter' });

            expect(onConfirm).toHaveBeenCalledWith('password123');
        });
    });

    // ==========================================
    // 4. AKSESIBILITAS (A11Y)
    // ==========================================
    describe('Accessibility', () => {
        it('ikon SVG dekoratif harus disembunyikan dari screen reader', () => {
            const { container } = render(ConfirmPasswordModal, {
                props: { open: true, onConfirm: vi.fn() },
            });

            // Cari SVG yang ada di dalam input wrapper (bukan dari mock)
            const svgs = container.querySelectorAll('svg');
            svgs.forEach((svg) => {
                // Pastikan setiap SVG memiliki aria-hidden
                expect(svg).toHaveAttribute('aria-hidden', 'true');
            });
        });

        it('pesan error harus memiliki role="alert"', async () => {
            render(ConfirmPasswordModal, {
                props: { open: true, onConfirm: vi.fn() },
            });

            const input = screen.getByLabelText('Password Akun');
            await fireEvent.input(input, { target: { value: 'abc' } });

            const confirmBtn = screen.getByTestId('confirm-btn');
            await fireEvent.click(confirmBtn);

            const errorMessage = screen.getByRole('alert');
            expect(errorMessage).toBeInTheDocument();
        });
    });
});
