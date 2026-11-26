import { render, screen, fireEvent, waitFor } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import Login from '../Login.svelte';

// ==========================================
// MOCK INERTIA (Dengan Tipe Eksplisit)
// ==========================================
type MockFlash = {
    status: string | null;
    error: string | null;
    retry_after: number | null;
};

const mockPage = vi.hoisted(() => ({
    props: {
        flash: {
            status: null as string | null,
            error: null as string | null,
            retry_after: null as number | null,
        } as MockFlash,
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
    login: vi.fn(),
}));

import { login } from '@/api/auth';

describe('Login Page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        mockPage.props.flash = { status: null, error: null, retry_after: null };
        vi.useFakeTimers();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    // ==========================================
    // 1. RENDERING DASAR
    // ==========================================
    describe('Rendering', () => {
        it('harus merender judul "Login." dan form', () => {
            render(Login);

            expect(screen.getByText('Login')).toBeInTheDocument();
            expect(
                screen.getByLabelText('Username atau Email'),
            ).toBeInTheDocument();
            expect(screen.getByLabelText('Password')).toBeInTheDocument();
            expect(
                screen.getByRole('button', { name: /Masuk ke Sistem/i }),
            ).toBeInTheDocument();
        });
    });

    // ==========================================
    // 2. INTERAKSI FORM
    // ==========================================
    describe('User Interaction', () => {
        it('harus memanggil login saat form disubmit', async () => {
            render(Login);

            const identitasInput = screen.getByLabelText('Username atau Email');
            await fireEvent.input(identitasInput, {
                target: { value: 'budi@test.com' },
            });

            const passwordInput = screen.getByLabelText('Password');
            await fireEvent.input(passwordInput, {
                target: { value: 'password123' },
            });

            const form = document.querySelector('form');
            await fireEvent.submit(form!);

            expect(login).toHaveBeenCalledWith(
                {
                    identitas: 'budi@test.com',
                    password: 'password123',
                    remember: false,
                },
                expect.any(Object),
            );
        });

        it('harus mengirim remember: true saat checkbox dicentang', async () => {
            render(Login);

            const identitasInput = screen.getByLabelText('Username atau Email');
            await fireEvent.input(identitasInput, {
                target: { value: 'budi@test.com' },
            });

            const passwordInput = screen.getByLabelText('Password');
            await fireEvent.input(passwordInput, {
                target: { value: 'password123' },
            });

            const rememberCheckbox = screen.getByLabelText(
                'Ingat saya',
            ) as HTMLInputElement;
            await fireEvent.click(rememberCheckbox);
            expect(rememberCheckbox.checked).toBe(true);

            const form = document.querySelector('form');
            await fireEvent.submit(form!);

            expect(login).toHaveBeenCalledWith(
                {
                    identitas: 'budi@test.com',
                    password: 'password123',
                    remember: true,
                },
                expect.any(Object),
            );
        });

        it('harus toggle show/hide password', async () => {
            render(Login);

            const passwordInput = screen.getByLabelText(
                'Password',
            ) as HTMLInputElement;
            expect(passwordInput.type).toBe('password');

            const toggleBtn = screen.getByRole('button', {
                name: /Tampilkan password/i,
            });
            await fireEvent.click(toggleBtn);

            expect(passwordInput.type).toBe('text');
        });
    });

    // ==========================================
    // 3. ERROR STATE
    // ==========================================
    describe('Error State', () => {
        it('harus menampilkan pesan error jika login gagal', async () => {
            (login as any).mockImplementation((_data: any, options: any) => {
                options.onError?.({ identitas: 'Kredensial tidak cocok' });
                options.onFinish?.();
            });

            render(Login);

            const form = document.querySelector('form');
            await fireEvent.submit(form!);

            await waitFor(() => {
                expect(
                    screen.getByText('Kredensial tidak cocok'),
                ).toBeInTheDocument();
            });
        });

        it('input harus memiliki aria-invalid=true jika error', async () => {
            (login as any).mockImplementation((_data: any, options: any) => {
                options.onError?.({ identitas: 'Error' });
                options.onFinish?.();
            });

            render(Login);

            const form = document.querySelector('form');
            await fireEvent.submit(form!);

            await waitFor(() => {
                const identitasInput = screen.getByLabelText(
                    'Username atau Email',
                );
                expect(identitasInput).toHaveAttribute('aria-invalid', 'true');
            });
        });
    });

    // ==========================================
    // 4. FLASH MESSAGES
    // ==========================================
    describe('Flash Messages', () => {
        it('harus menampilkan flash status', () => {
            mockPage.props.flash = {
                status: 'Berhasil daftar',
                error: null,
                retry_after: null,
            };
            render(Login);

            expect(screen.getByText('Berhasil daftar')).toBeInTheDocument();
        });

        it('harus menampilkan flash error', () => {
            mockPage.props.flash = {
                status: null,
                error: 'Terjadi kesalahan',
                retry_after: null,
            };
            render(Login);

            expect(screen.getByText('Terjadi kesalahan')).toBeInTheDocument();
        });
    });

    // ==========================================
    // 5. COUNTDOWN (RATE LIMIT)
    // ==========================================
    describe('Countdown (Rate Limit)', () => {
        it('harus memulai countdown jika flash.retry_after ada', async () => {
            mockPage.props.flash = {
                status: null,
                error: null,
                retry_after: 5,
            };
            render(Login);

            await vi.advanceTimersByTimeAsync(0);
            expect(screen.getByText('Tunggu 5 detik…')).toBeInTheDocument();

            await vi.advanceTimersByTimeAsync(1000);
            expect(screen.getByText('Tunggu 4 detik…')).toBeInTheDocument();
        });

        it('tombol harus disabled selama countdown', async () => {
            mockPage.props.flash = {
                status: null,
                error: null,
                retry_after: 5,
            };
            render(Login);

            await vi.advanceTimersByTimeAsync(0);
            const submitBtn = screen.getByRole('button', {
                name: /Tunggu 5 detik…/i,
            });
            expect(submitBtn).toBeDisabled();
        });

        it('tombol harus kembali aktif setelah countdown selesai', async () => {
            mockPage.props.flash = {
                status: null,
                error: null,
                retry_after: 2,
            };
            render(Login);

            await vi.advanceTimersByTimeAsync(0);
            expect(screen.getByText('Tunggu 2 detik…')).toBeInTheDocument();

            await vi.advanceTimersByTimeAsync(2000);
            await vi.advanceTimersByTimeAsync(0);
            expect(
                screen.getByRole('button', { name: /Masuk ke Sistem/i }),
            ).not.toBeDisabled();
        });
    });

    // ==========================================
    // 6. LOADING STATE
    // ==========================================
    describe('Loading State', () => {
        it('harus menampilkan "Memproses…" dan menonaktifkan tombol saat processing', async () => {
            (login as any).mockImplementation(() => {
                // Tidak memanggil onFinish, biarkan processing = true
            });

            render(Login);

            const form = document.querySelector('form');
            await fireEvent.submit(form!);

            await waitFor(() => {
                expect(screen.getByText('Memproses…')).toBeInTheDocument();
                expect(
                    screen.getByRole('button', { name: /Memproses/i }),
                ).toBeDisabled();
            });
        });
    });

    // ==========================================
    // 7. AKSESIBILITAS (A11Y)
    // ==========================================
    describe('Accessibility', () => {
        it('ikon SVG dekoratif harus disembunyikan dari screen reader', () => {
            const { container } = render(Login);

            const svgs = container.querySelectorAll('svg');
            svgs.forEach((svg) => {
                expect(svg).toHaveAttribute('aria-hidden', 'true');
            });
        });

        it('flash message sukses harus memiliki role="status"', () => {
            mockPage.props.flash = {
                status: 'Berhasil',
                error: null,
                retry_after: null,
            };
            render(Login);

            const status = screen.getByRole('status');
            expect(status).toHaveTextContent('Berhasil');
        });

        it('flash message error harus memiliki role="alert"', () => {
            mockPage.props.flash = {
                status: null,
                error: 'Gagal',
                retry_after: null,
            };
            render(Login);

            const alert = screen.getByRole('alert');
            expect(alert).toHaveTextContent('Gagal');
        });
    });
});
