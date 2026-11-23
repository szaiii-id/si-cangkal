import { render, screen, fireEvent, waitFor } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { tick } from 'svelte';
import VerifyEmail from '../VerifyEmail.svelte';

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
    resendVerificationEmail: vi.fn(),
    logout: vi.fn(),
}));

import { logout, resendVerificationEmail } from '@/api/auth';

describe('VerifyEmail Page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        mockPage.props.flash = { status: null, error: null, retry_after: null };
        vi.useFakeTimers();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    describe('Rendering', () => {
        it('menampilkan email tujuan verifikasi', () => {
            render(VerifyEmail, { props: { email: 'budi@example.com' } });

            expect(
                screen.getByRole('heading', { name: /Verifikasi Alamat/i }),
            ).toBeInTheDocument();
            expect(screen.getByText('budi@example.com')).toBeInTheDocument();
        });

        it('menampilkan tombol kirim ulang dan logout', () => {
            render(VerifyEmail, { props: { email: 'budi@example.com' } });

            expect(
                screen.getByRole('button', {
                    name: /Kirim Ulang Email Verifikasi/i,
                }),
            ).toBeInTheDocument();
            expect(
                screen.getByRole('button', { name: /Keluar \(Logout\)/i }),
            ).toBeInTheDocument();
        });
    });

    describe('Kirim ulang', () => {
        it('memanggil API dan memulai cooldown 60 detik', async () => {
            vi.mocked(resendVerificationEmail).mockImplementation((options) => {
                options?.onSuccess?.(null as never);
                options?.onFinish?.({} as never);
            });

            render(VerifyEmail, { props: { email: 'budi@example.com' } });

            await fireEvent.click(
                screen.getByRole('button', {
                    name: /Kirim Ulang Email Verifikasi/i,
                }),
            );
            await tick();

            expect(resendVerificationEmail).toHaveBeenCalled();
            expect(
                screen.getByRole('button', { name: /Tunggu 60 detik/i }),
            ).toBeDisabled();
        });

        it('menangani 429 tanpa error modal dan menampilkan countdown', async () => {
            vi.mocked(resendVerificationEmail).mockImplementation((options) => {
                const result = options?.onHttpException?.({
                    status: 429,
                    headers: { 'retry-after': '42' },
                    data: {},
                } as never);

                expect(result).toBe(false);
                options?.onFinish?.({} as never);
            });

            render(VerifyEmail, { props: { email: 'budi@example.com' } });

            await fireEvent.click(
                screen.getByRole('button', {
                    name: /Kirim Ulang Email Verifikasi/i,
                }),
            );
            await tick();

            await waitFor(() => {
                expect(screen.getByRole('alert')).toHaveTextContent(
                    /Terlalu banyak permintaan/i,
                );
            });
            expect(
                screen.getByRole('button', { name: /Tunggu 42 detik/i }),
            ).toBeDisabled();
        });

        it('menampilkan error validasi dari backend', async () => {
            vi.mocked(resendVerificationEmail).mockImplementation((options) => {
                options?.onError?.({ email: 'Gagal mengirim email.' });
                options?.onFinish?.({} as never);
            });

            render(VerifyEmail, { props: { email: 'budi@example.com' } });

            await fireEvent.click(
                screen.getByRole('button', {
                    name: /Kirim Ulang Email Verifikasi/i,
                }),
            );
            await tick();

            await waitFor(() => {
                expect(screen.getByRole('alert')).toHaveTextContent(
                    'Gagal mengirim email.',
                );
            });
        });
    });

    describe('Logout', () => {
        it('memanggil logout saat tombol keluar diklik', async () => {
            render(VerifyEmail, { props: { email: 'budi@example.com' } });

            await fireEvent.click(
                screen.getByRole('button', { name: /Keluar \(Logout\)/i }),
            );

            expect(logout).toHaveBeenCalled();
        });
    });

    describe('Flash', () => {
        it('menampilkan status sukses dari flash', () => {
            mockPage.props.flash = {
                status: 'Tautan verifikasi baru telah dikirim.',
                error: null,
                retry_after: null,
            };

            render(VerifyEmail, { props: { email: 'budi@example.com' } });

            expect(screen.getByRole('status')).toHaveTextContent(
                'Tautan verifikasi baru telah dikirim.',
            );
        });
    });
});
