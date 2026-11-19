import { render, screen, fireEvent } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { tick } from 'svelte';
import ResetPassword from '../ResetPassword.svelte';

const mockPage = vi.hoisted(() => ({
    props: {
        flash: {
            error: null as string | null,
            retry_after: null as number | null,
        },
    },
}));

vi.mock('@inertiajs/svelte', () => ({
    page: mockPage,
}));

vi.mock('@/api/auth', () => ({
    resetPassword: vi.fn(),
}));

import { resetPassword } from '@/api/auth';

describe('ResetPassword Page', () => {
    const defaultProps = {
        token: 'sample-reset-token-123',
        email: 'budi@test.com',
    };

    beforeEach(() => {
        vi.clearAllMocks();
        mockPage.props.flash = { error: null, retry_after: null };
        vi.useFakeTimers();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    describe('Rendering', () => {
        it('harus merender form reset password dengan input email readonly terisi dari props', () => {
            render(ResetPassword, defaultProps);

            expect(screen.getByRole('heading', { name: /Reset Sandi/i })).toBeInTheDocument();

            const emailInput = screen.getByLabelText('Email') as HTMLInputElement;
            expect(emailInput).toHaveValue('budi@test.com');
            expect(emailInput).toHaveAttribute('readonly');

            expect(screen.getByLabelText('Password Baru')).toBeInTheDocument();
            expect(screen.getByLabelText('Konfirmasi Password')).toBeInTheDocument();
            expect(screen.getByRole('button', { name: /Simpan Password Baru/i })).toBeEnabled();
        });

        it('harus menangani prop email bernilai null dengan menampilkan string kosong', () => {
            render(ResetPassword, { token: 'token-abc', email: null });

            const emailInput = screen.getByLabelText('Email') as HTMLInputElement;
            expect(emailInput).toHaveValue('');
            expect(emailInput).toHaveAttribute('readonly');
        });
    });

    describe('Validation & Reactivity', () => {
        it('harus menampilkan peringatan dan menonaktifkan tombol submit jika konfirmasi password tidak cocok', async () => {
            render(ResetPassword, defaultProps);

            await fireEvent.input(screen.getByLabelText('Password Baru'), {
                target: { value: 'PasswordBaru123!' },
            });
            await fireEvent.input(screen.getByLabelText('Konfirmasi Password'), {
                target: { value: 'Berbeda123!' },
            });

            expect(screen.getByRole('alert')).toHaveTextContent('Konfirmasi password tidak cocok.');
            expect(screen.getByRole('button', { name: /Simpan Password Baru/i })).toBeDisabled();

            await fireEvent.input(screen.getByLabelText('Konfirmasi Password'), {
                target: { value: 'PasswordBaru123!' },
            });

            expect(screen.queryByRole('alert')).not.toBeInTheDocument();
            expect(screen.getByRole('button', { name: /Simpan Password Baru/i })).toBeEnabled();
        });
    });

    describe('User Interaction', () => {
        it('harus memanggil resetPassword dengan token, email, password, dan password_confirmation saat submit', async () => {
            render(ResetPassword, defaultProps);

            await fireEvent.input(screen.getByLabelText('Password Baru'), {
                target: { value: 'PasswordBaru123!' },
            });
            await fireEvent.input(screen.getByLabelText('Konfirmasi Password'), {
                target: { value: 'PasswordBaru123!' },
            });

            await fireEvent.submit(document.querySelector('form')!);

            expect(resetPassword).toHaveBeenCalledTimes(1);
            expect(resetPassword).toHaveBeenCalledWith(
                {
                    token: 'sample-reset-token-123',
                    email: 'budi@test.com',
                    password: 'PasswordBaru123!',
                    password_confirmation: 'PasswordBaru123!',
                },
                expect.any(Object),
            );
        });

        it('harus toggle visibilitas password baru dan konfirmasi password secara independen', async () => {
            render(ResetPassword, defaultProps);

            const passwordInput = screen.getByLabelText('Password Baru') as HTMLInputElement;
            const confirmInput = screen.getByLabelText('Konfirmasi Password') as HTMLInputElement;

            expect(passwordInput.type).toBe('password');
            expect(confirmInput.type).toBe('password');

            await fireEvent.click(screen.getByRole('button', { name: 'Tampilkan password baru' }));
            expect(passwordInput.type).toBe('text');
            expect(confirmInput.type).toBe('password');

            await fireEvent.click(screen.getByRole('button', { name: 'Tampilkan konfirmasi password' }));
            expect(confirmInput.type).toBe('text');

            await fireEvent.click(screen.getByRole('button', { name: 'Sembunyikan password baru' }));
            expect(passwordInput.type).toBe('password');
            expect(confirmInput.type).toBe('text');
        });
    });

    describe('Loading, Error & Flash States', () => {
        it('harus menonaktifkan tombol dan menampilkan indikator saat penyimpanan berlangsung', async () => {
            vi.mocked(resetPassword).mockImplementation(() => {});

            render(ResetPassword, defaultProps);

            await fireEvent.input(screen.getByLabelText('Password Baru'), {
                target: { value: 'PasswordBaru123!' },
            });
            await fireEvent.input(screen.getByLabelText('Konfirmasi Password'), {
                target: { value: 'PasswordBaru123!' },
            });
            await fireEvent.submit(document.querySelector('form')!);

            expect(screen.getByRole('button', { name: /Menyimpan/i })).toBeDisabled();
        });

        it('harus menampilkan pesan error validasi dari backend untuk email, password, dan konfirmasi', async () => {
            vi.mocked(resetPassword).mockImplementation((_data, options) => {
                options?.onError?.({
                    email: 'Token reset password tidak valid atau kedaluwarsa',
                    password: 'Password minimal 8 karakter',
                    password_confirmation: 'Konfirmasi password wajib diisi',
                });
                options?.onFinish?.({} as any);
            });

            render(ResetPassword, defaultProps);
            await fireEvent.submit(document.querySelector('form')!);

            const alerts = screen.getAllByRole('alert');
            expect(alerts).toHaveLength(3);
            expect(alerts[0]).toHaveTextContent('Token reset password tidak valid atau kedaluwarsa');
            expect(alerts[1]).toHaveTextContent('Password minimal 8 karakter');
            expect(alerts[2]).toHaveTextContent('Konfirmasi password wajib diisi');

            expect(screen.getByLabelText('Email')).toHaveAttribute('aria-invalid', 'true');
            expect(screen.getByLabelText('Password Baru')).toHaveAttribute('aria-invalid', 'true');
            expect(screen.getByLabelText('Konfirmasi Password')).toHaveAttribute('aria-invalid', 'true');
            expect(screen.getByRole('button', { name: /Simpan Password Baru/i })).toBeEnabled();
        });

        it('harus menampilkan flash error global', () => {
            mockPage.props.flash = {
                error: 'Tautan reset password sudah tidak berlaku.',
                retry_after: null,
            };
            render(ResetPassword, defaultProps);

            expect(screen.getByRole('alert')).toHaveTextContent('Tautan reset password sudah tidak berlaku.');
        });
    });

    describe('Countdown (Rate Limit)', () => {
        it('harus mengunci tombol selama hitung mundur dan mengaktifkannya kembali setelah selesai', async () => {
            mockPage.props.flash = { error: null, retry_after: 3 };
            render(ResetPassword, defaultProps);
            await tick();

            const submitBtn = screen.getByRole('button', { name: /Tunggu 3 detik/i });
            expect(submitBtn).toBeDisabled();

            await vi.advanceTimersByTimeAsync(1000);
            expect(submitBtn).toHaveTextContent('Tunggu 2 detik...');

            await vi.advanceTimersByTimeAsync(2000);
            expect(submitBtn).toHaveTextContent('Simpan Password Baru');
            expect(submitBtn).toBeEnabled();
        });
    });

    describe('Accessibility', () => {
        it('semua ikon SVG dekoratif harus memiliki aria-hidden="true"', () => {
            const { container } = render(ResetPassword, defaultProps);
            const svgs = container.querySelectorAll('svg');

            expect(svgs.length).toBeGreaterThan(0);
            svgs.forEach((svg) => {
                expect(svg).toHaveAttribute('aria-hidden', 'true');
            });
        });
    });
});