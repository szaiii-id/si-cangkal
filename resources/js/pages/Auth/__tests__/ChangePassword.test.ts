import { render, screen, fireEvent, waitFor } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import ChangePassword from '../ChangePassword.svelte';

// ==========================================
// MOCK INERTIA
// ==========================================
const mockPage = vi.hoisted(() => ({
    props: {
        flash: {
            status: null as string | null,
            error: null as string | null,
        },
    },
}));

vi.mock('@inertiajs/svelte', () => ({
    page: mockPage,
    inertia: vi.fn(),
}));

// ==========================================
// MOCK API AUTH
// ==========================================
vi.mock('@/api/auth', () => ({
    changePassword: vi.fn(),
    logout: vi.fn(),
}));

// ==========================================
// MOCK CHILD COMPONENT (PasswordChecklist)
// ==========================================
vi.mock('@/components/ui/PasswordChecklist.svelte', () => 
    import('@/components/ui/__mocks__/PasswordChecklist.svelte')
);

import { changePassword, logout } from '@/api/auth';

describe('ChangePassword Page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        mockPage.props.flash = { status: null, error: null };
    });

    // ==========================================
    // 1. RENDERING (MODE NORMAL)
    // ==========================================
    describe('Rendering (Mode Normal)', () => {
        it('harus merender 3 input password (lama, baru, konfirmasi)', () => {
            render(ChangePassword, { props: { mustChangePassword: false } });

            expect(screen.getByLabelText(/^Password Lama/i)).toBeInTheDocument();
            expect(screen.getByLabelText(/^Password Baru/i)).toBeInTheDocument();
            expect(screen.getByLabelText(/Konfirmasi Password Baru/i)).toBeInTheDocument();
        });

        it('TIDAK boleh merender flash info wajib ganti', () => {
            render(ChangePassword, { props: { mustChangePassword: false } });
            expect(screen.queryByText('Wajib Ganti Password')).not.toBeInTheDocument();
        });
    });

    // ==========================================
    // 2. RENDERING (MODE WAJIB)
    // ==========================================
    describe('Rendering (Mode Wajib)', () => {
        it('TIDAK boleh merender input password lama', () => {
            render(ChangePassword, { props: { mustChangePassword: true } });
            expect(screen.queryByLabelText(/^Password Lama/i)).not.toBeInTheDocument();
        });

        it('harus merender input password baru dan konfirmasi', () => {
            render(ChangePassword, { props: { mustChangePassword: true } });
            expect(screen.getByLabelText(/^Password Baru/i)).toBeInTheDocument();
            expect(screen.getByLabelText(/Konfirmasi Password Baru/i)).toBeInTheDocument();
        });

        it('harus merender flash info wajib ganti password', () => {
            render(ChangePassword, { props: { mustChangePassword: true } });
            expect(screen.getByRole('note')).toHaveTextContent('Wajib Ganti Password');
        });
    });

    // ==========================================
    // 3. FORM SUBMISSION (MODE NORMAL)
    // ==========================================
    describe('Form Submission (Mode Normal)', () => {
        it('harus mengirim old_password, password, dan password_confirmation', async () => {
            render(ChangePassword, { props: { mustChangePassword: false } });

            // PERBAIKAN: Gunakan placeholder atau regex yang lebih ketat
            await fireEvent.input(screen.getByPlaceholderText('Masukkan password lama Anda'), {
                target: { value: 'oldpass' },
            });
            await fireEvent.input(screen.getByLabelText(/^Password Baru/i), {
                target: { value: 'newpassword123!' },
            });
            await fireEvent.input(screen.getByLabelText(/Konfirmasi Password Baru/i), {
                target: { value: 'newpassword123!' },
            });

            await fireEvent.submit(document.querySelector('form')!);

            expect(changePassword).toHaveBeenCalledWith(
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
    // 4. FORM SUBMISSION (MODE WAJIB)
    // ==========================================
    describe('Form Submission (Mode Wajib)', () => {
        it('TIDAK boleh mengirim old_password', async () => {
            render(ChangePassword, { props: { mustChangePassword: true } });

            await fireEvent.input(screen.getByLabelText(/^Password Baru/i), {
                target: { value: 'newpassword123!' },
            });
            await fireEvent.input(screen.getByLabelText(/Konfirmasi Password Baru/i), {
                target: { value: 'newpassword123!' },
            });

            await fireEvent.submit(document.querySelector('form')!);

            expect(changePassword).toHaveBeenCalledWith(
                {
                    password: 'newpassword123!',
                    password_confirmation: 'newpassword123!',
                },
                expect.any(Object)
            );
        });
    });

    // ==========================================
    // 5. PASSWORD MISMATCH
    // ==========================================
    describe('Password Mismatch', () => {
        it('harus menampilkan error dan mengunci tombol jika password tidak cocok', async () => {
            render(ChangePassword, { props: { mustChangePassword: false } });

            await fireEvent.input(screen.getByLabelText(/^Password Baru/i), {
                target: { value: 'newpassword123!' },
            });
            await fireEvent.input(screen.getByLabelText(/Konfirmasi Password Baru/i), {
                target: { value: 'berbeda123!' },
            });

            expect(screen.getByRole('alert')).toHaveTextContent('Konfirmasi password tidak cocok.');
            expect(screen.getByRole('button', { name: /Simpan Password Baru/i })).toBeDisabled();
        });
    });

    // ==========================================
    // 6. ERROR STATE
    // ==========================================
    describe('Error State', () => {
        it('harus menampilkan error validasi dari backend', async () => {
            vi.mocked(changePassword).mockImplementation((_data, options) => {
                options?.onError?.({ password: 'Password terlalu lemah' });
                options?.onFinish?.({} as any);
            });

            render(ChangePassword, { props: { mustChangePassword: false } });

            await fireEvent.input(screen.getByLabelText(/^Password Baru/i), {
                target: { value: 'weak' },
            });
            await fireEvent.input(screen.getByLabelText(/Konfirmasi Password Baru/i), {
                target: { value: 'weak' },
            });
            await fireEvent.submit(document.querySelector('form')!);

            await waitFor(() => {
                expect(screen.getByText('Password terlalu lemah')).toBeInTheDocument();
            });
        });

        it('harus menampilkan flash error global', () => {
            mockPage.props.flash = { status: null, error: 'Sesi habis' };
            render(ChangePassword, { props: { mustChangePassword: false } });

            expect(screen.getByRole('alert')).toHaveTextContent('Sesi habis');
        });
    });

    // ==========================================
    // 7. LOGOUT
    // ==========================================
    describe('Logout', () => {
        it('harus memanggil logout saat tombol "Keluar" diklik', async () => {
            render(ChangePassword, { props: { mustChangePassword: false } });

            const logoutBtn = screen.getByRole('button', { name: /Keluar dari akun/i });
            await fireEvent.click(logoutBtn);

            expect(logout).toHaveBeenCalledTimes(1);
        });
    });

    // ==========================================
    // 8. AKSESIBILITAS
    // ==========================================
    describe('Accessibility', () => {
        it('semua ikon SVG dekoratif harus memiliki aria-hidden="true"', () => {
            const { container } = render(ChangePassword, { props: { mustChangePassword: false } });
            const svgs = container.querySelectorAll('svg');

            svgs.forEach((svg) => {
                expect(svg).toHaveAttribute('aria-hidden', 'true');
            });
        });

        it('flash status sukses harus memiliki role="status"', () => {
            mockPage.props.flash = { status: 'Password berhasil diubah', error: null };
            render(ChangePassword, { props: { mustChangePassword: false } });

            expect(screen.getByRole('status')).toHaveTextContent('Password berhasil diubah');
        });
    });
});