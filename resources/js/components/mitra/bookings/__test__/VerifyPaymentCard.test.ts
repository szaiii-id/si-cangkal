import { render, screen, fireEvent } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import VerifyPaymentCard from '../VerifyPaymentCard.svelte';

// ==========================================
// MOCK CHILD COMPONENT (Mengarah ke file fisik)
// ==========================================
vi.mock(
    '@/components/shared/PaymentProofPreview.svelte',
    () => import('@/components/shared/__mocks__/PaymentProofPreview.svelte'),
);

describe('VerifyPaymentCard Component', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    // ==========================================
    // 1. RENDERING DASAR
    // ==========================================
    describe('Rendering', () => {
        it('harus merender judul dan deskripsi', () => {
            render(VerifyPaymentCard, {
                props: {
                    bookingId: '1',
                    hasProof: true,
                    onVerify: vi.fn(),
                    onReject: vi.fn(),
                    verifyProcessing: false,
                    canVerify: true,
                    canReject: true,
                },
            });

            expect(
                screen.getByText('Verifikasi Pembayaran'),
            ).toBeInTheDocument();
            expect(
                screen.getByText(/Bukti transfer sudah dikirim/),
            ).toBeInTheDocument();
        });

        it('harus merender PaymentProofPreview (mock)', () => {
            render(VerifyPaymentCard, {
                props: {
                    bookingId: '1',
                    hasProof: true,
                    onVerify: vi.fn(),
                    onReject: vi.fn(),
                    verifyProcessing: false,
                    canVerify: true,
                    canReject: true,
                },
            });

            expect(
                screen.getByTestId('payment-proof-preview'),
            ).toBeInTheDocument();
        });

        it('harus merender tombol Verifikasi dan Tolak', () => {
            render(VerifyPaymentCard, {
                props: {
                    bookingId: '1',
                    hasProof: true,
                    onVerify: vi.fn(),
                    onReject: vi.fn(),
                    verifyProcessing: false,
                    canVerify: true,
                    canReject: true,
                },
            });

            expect(
                screen.getByRole('button', {
                    name: /Verifikasi dan terima pembayaran/i,
                }),
            ).toBeInTheDocument();
            expect(
                screen.getByRole('button', { name: /Tolak pembayaran/i }),
            ).toBeInTheDocument();
        });
    });

    // ==========================================
    // 2. INTERAKSI
    // ==========================================
    describe('User Interaction', () => {
        it('harus memanggil onVerify saat tombol Verifikasi diklik', async () => {
            const onVerify = vi.fn();
            render(VerifyPaymentCard, {
                props: {
                    bookingId: '1',
                    hasProof: true,
                    onVerify,
                    onReject: vi.fn(),
                    verifyProcessing: false,
                    canVerify: true,
                    canReject: true,
                },
            });

            const btn = screen.getByRole('button', {
                name: /Verifikasi dan terima pembayaran/i,
            });
            await fireEvent.click(btn);

            expect(onVerify).toHaveBeenCalledTimes(1);
        });

        it('harus memanggil onReject saat tombol Tolak diklik', async () => {
            const onReject = vi.fn();
            render(VerifyPaymentCard, {
                props: {
                    bookingId: '1',
                    hasProof: true,
                    onVerify: vi.fn(),
                    onReject,
                    verifyProcessing: false,
                    canVerify: true,
                    canReject: true,
                },
            });

            const btn = screen.getByRole('button', {
                name: /Tolak pembayaran/i,
            });
            await fireEvent.click(btn);

            expect(onReject).toHaveBeenCalledTimes(1);
        });
    });

    // ==========================================
    // 3. LOADING STATE
    // ==========================================
    describe('Loading State', () => {
        it('harus menonaktifkan tombol Verifikasi dan menampilkan "Memproses…" saat verifyProcessing=true', () => {
            render(VerifyPaymentCard, {
                props: {
                    bookingId: '1',
                    hasProof: true,
                    onVerify: vi.fn(),
                    onReject: vi.fn(),
                    verifyProcessing: true,
                    canVerify: true,
                    canReject: true,
                },
            });

            const btn = screen.getByRole('button', {
                name: /Sedang memverifikasi pembayaran/i,
            });
            expect(btn).toBeDisabled();
            expect(screen.getByText('Memproses…')).toBeInTheDocument();
        });
    });

    // ==========================================
    // 4. DISABLED STATE
    // ==========================================
    describe('Disabled State', () => {
        it('harus menonaktifkan tombol Verifikasi jika canVerify=false', () => {
            render(VerifyPaymentCard, {
                props: {
                    bookingId: '1',
                    hasProof: true,
                    onVerify: vi.fn(),
                    onReject: vi.fn(),
                    verifyProcessing: false,
                    canVerify: false,
                    canReject: true,
                },
            });

            const btn = screen.getByRole('button', {
                name: /Verifikasi dan terima pembayaran/i,
            });
            expect(btn).toBeDisabled();
        });

        it('harus menonaktifkan tombol Tolak jika canReject=false', () => {
            render(VerifyPaymentCard, {
                props: {
                    bookingId: '1',
                    hasProof: true,
                    onVerify: vi.fn(),
                    onReject: vi.fn(),
                    verifyProcessing: false,
                    canVerify: true,
                    canReject: false,
                },
            });

            const btn = screen.getByRole('button', {
                name: /Tolak pembayaran/i,
            });
            expect(btn).toBeDisabled();
        });
    });
});
