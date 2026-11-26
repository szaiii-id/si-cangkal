import { render, screen, fireEvent, waitFor } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { tick } from 'svelte';
import ForgotPassword from '../ForgotPassword.svelte';

// ==========================================
// MOCK INERTIA
// ==========================================
const mockPage = vi.hoisted(() => ({
    props: {
        flash: {
            status: null as string | null,
            error: null as string | null,
            retry_after: null as number | null,
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
    forgotPassword: vi.fn(),
}));

import { forgotPassword } from '@/api/auth';

describe('ForgotPassword Page', () => {
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
        it('harus merender judul "Pemulihan" dan form email', () => {
            render(ForgotPassword);

            expect(
                screen.getByRole('heading', { name: /Pemulihan/i }),
            ).toBeInTheDocument();
            expect(screen.getByLabelText('Alamat Email')).toBeInTheDocument();
            expect(
                screen.getByRole('button', { name: /Kirim Tautan Pemulihan/i }),
            ).toBeInTheDocument();
        });

        it('harus merender tautan "Kembali ke Login"', () => {
            render(ForgotPassword);

            expect(
                screen.getByRole('link', { name: /Kembali ke Login/i }),
            ).toHaveAttribute('href', '/login');
        });
    });

    // ==========================================
    // 2. FORM SUBMISSION
    // ==========================================
    describe('Form Submission', () => {
        it('harus memanggil forgotPassword dengan email yang benar', async () => {
            render(ForgotPassword);

            await fireEvent.input(screen.getByLabelText('Alamat Email'), {
                target: { value: 'budi@test.com' },
            });

            await fireEvent.submit(document.querySelector('form')!);

            expect(forgotPassword).toHaveBeenCalledWith(
                { email: 'budi@test.com' },
                expect.any(Object),
            );
        });
    });

    // ==========================================
    // 3. ERROR STATE
    // ==========================================
    describe('Error State', () => {
        it('harus menampilkan error validasi email dari backend', async () => {
            vi.mocked(forgotPassword).mockImplementation((_data, options) => {
                options?.onError?.({ email: 'Email tidak terdaftar' });
                options?.onFinish?.({} as any);
            });

            render(ForgotPassword);

            await fireEvent.input(screen.getByLabelText('Alamat Email'), {
                target: { value: 'unknown@test.com' },
            });
            await fireEvent.submit(document.querySelector('form')!);

            await waitFor(() => {
                expect(
                    screen.getByText('Email tidak terdaftar'),
                ).toBeInTheDocument();
            });
        });

        it('input harus memiliki aria-invalid=true jika error', async () => {
            vi.mocked(forgotPassword).mockImplementation((_data, options) => {
                options?.onError?.({ email: 'Error' });
                options?.onFinish?.({} as any);
            });

            render(ForgotPassword);

            await fireEvent.input(screen.getByLabelText('Alamat Email'), {
                target: { value: 'unknown@test.com' },
            });
            await fireEvent.submit(document.querySelector('form')!);

            await waitFor(() => {
                const emailInput = screen.getByLabelText('Alamat Email');
                expect(emailInput).toHaveAttribute('aria-invalid', 'true');
            });
        });

        it('harus menampilkan flash error global', () => {
            mockPage.props.flash = {
                status: null,
                error: 'Terlalu banyak percobaan',
                retry_after: null,
            };
            render(ForgotPassword);

            expect(screen.getByRole('alert')).toHaveTextContent(
                'Terlalu banyak percobaan',
            );
        });
    });

    // ==========================================
    // 4. FLASH SUKSES
    // ==========================================
    describe('Flash Success', () => {
        it('harus menampilkan flash status sukses', () => {
            mockPage.props.flash = {
                status: 'Tautan pemulihan telah dikirim',
                error: null,
                retry_after: null,
            };
            render(ForgotPassword);

            expect(screen.getByRole('status')).toHaveTextContent(
                'Tautan pemulihan telah dikirim',
            );
        });
    });

    // ==========================================
    // 5. LOADING STATE
    // ==========================================
    describe('Loading State', () => {
        it('harus menampilkan "Mengirim Tautan…" saat processing', async () => {
            vi.mocked(forgotPassword).mockImplementation(() => {});

            render(ForgotPassword);

            await fireEvent.input(screen.getByLabelText('Alamat Email'), {
                target: { value: 'budi@test.com' },
            });
            await fireEvent.submit(document.querySelector('form')!);

            expect(
                screen.getByRole('button', { name: /Mengirim Tautan/i }),
            ).toBeDisabled();
        });
    });

    // ==========================================
    // 6. COUNTDOWN (RATE LIMIT)
    // ==========================================
    describe('Countdown (Rate Limit)', () => {
        it('harus mengunci tombol selama hitung mundur', async () => {
            mockPage.props.flash = {
                status: null,
                error: null,
                retry_after: 3,
            };
            render(ForgotPassword);
            await tick();

            const submitBtn = screen.getByRole('button', {
                name: /Tunggu 3 detik/i,
            });
            expect(submitBtn).toBeDisabled();

            await vi.advanceTimersByTimeAsync(1000);
            expect(submitBtn).toHaveTextContent('Tunggu 2 detik…');

            await vi.advanceTimersByTimeAsync(2000);
            expect(submitBtn).toHaveTextContent('Kirim Tautan Pemulihan');
            expect(submitBtn).toBeEnabled();
        });
    });

    // ==========================================
    // 7. AKSESIBILITAS
    // ==========================================
    describe('Accessibility', () => {
        it('semua ikon SVG dekoratif harus memiliki aria-hidden="true"', () => {
            const { container } = render(ForgotPassword);
            const svgs = container.querySelectorAll('svg');

            expect(svgs.length).toBeGreaterThan(0);
            svgs.forEach((svg) => {
                expect(svg).toHaveAttribute('aria-hidden', 'true');
            });
        });

        it('flash status sukses harus memiliki role="status"', () => {
            mockPage.props.flash = {
                status: 'Sukses',
                error: null,
                retry_after: null,
            };
            render(ForgotPassword);

            const status = screen.getByRole('status');
            expect(status).toHaveTextContent('Sukses');
        });

        it('flash error harus memiliki role="alert"', () => {
            mockPage.props.flash = {
                status: null,
                error: 'Gagal',
                retry_after: null,
            };
            render(ForgotPassword);

            const alert = screen.getByRole('alert');
            expect(alert).toHaveTextContent('Gagal');
        });

        it('pesan error validasi email harus memiliki role="alert"', async () => {
            vi.mocked(forgotPassword).mockImplementation((_data, options) => {
                options?.onError?.({ email: 'Error validasi' });
                options?.onFinish?.({} as any);
            });

            render(ForgotPassword);

            await fireEvent.input(screen.getByLabelText('Alamat Email'), {
                target: { value: 'test@test.com' },
            });
            await fireEvent.submit(document.querySelector('form')!);

            await waitFor(() => {
                const alerts = screen.getAllByRole('alert');
                expect(
                    alerts.some((alert) =>
                        alert.textContent?.includes('Error validasi'),
                    ),
                ).toBe(true);
            });
        });
    });
});
