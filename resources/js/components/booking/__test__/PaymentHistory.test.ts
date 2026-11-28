import { render, screen } from '@testing-library/svelte';
import { describe, it, expect } from 'vitest';
import PaymentHistory from '../PaymentHistory.svelte';
import type { PaymentEloquent, PaymentResource } from '@/types/models';

// ==========================================
// MOCK DATA
// ==========================================
const createMockPaymentEloquent = (
    overrides: Partial<PaymentEloquent> = {},
): PaymentEloquent => ({
    id: 1,
    booking_id: '1',
    jumlah: '500000',
    metode: 'transfer_manual',
    channel_code: null,
    verified_by: null,
    verified_at: null,
    catatan: null,
    created_at: '2026-10-16T10:00:00Z',
    updated_at: '2026-10-16T10:00:00Z',
    ...overrides,
});

const createMockPaymentResource = (
    overrides: Partial<PaymentResource> = {},
): PaymentResource => ({
    id: 1,
    jumlah: '500000',
    jumlahRupiah: 'Rp 500.000',
    metode: 'transfer_manual',
    metodeLabel: 'Transfer Manual',
    isVerified: false,
    verified_at: null,
    catatan: null,
    ...overrides,
});

describe('PaymentHistory Component', () => {
    // ==========================================
    // 1. RENDERING AWAL (KOSONG)
    // ==========================================
    describe('Empty State', () => {
        it('harus menampilkan pesan default jika payments kosong', () => {
            render(PaymentHistory, { props: { payments: [] } });

            expect(
                screen.getByText('Belum ada pembayaran'),
            ).toBeInTheDocument();
            expect(
                screen.getByText(/Status akan otomatis terupdate/i),
            ).toBeInTheDocument();
        });

        it('harus menampilkan pesan kustom jika emptyMessage diberikan', () => {
            render(PaymentHistory, {
                props: { payments: [], emptyMessage: 'Tidak ada pembayaran.' },
            });

            expect(
                screen.getByText('Tidak ada pembayaran.'),
            ).toBeInTheDocument();
        });
    });

    // ==========================================
    // 2. MENAMPILKAN PEMBAYARAN (PaymentEloquent)
    // ==========================================
    describe('Displaying Payments (Eloquent)', () => {
        it('harus menampilkan jumlah yang terformat', () => {
            const payments = [createMockPaymentEloquent({ jumlah: '1500000' })];
            render(PaymentHistory, { props: { payments } });

            expect(screen.getByText(/Rp 1.500.000/)).toBeInTheDocument();
        });

        it('harus menampilkan status "Menunggu Verifikasi" jika belum diverifikasi', () => {
            const payments = [createMockPaymentEloquent({ verified_at: null })];
            render(PaymentHistory, { props: { payments } });

            expect(screen.getByText('Menunggu Verifikasi')).toBeInTheDocument();
        });

        it('harus menampilkan status "Terverifikasi" jika sudah diverifikasi', () => {
            const payments = [
                createMockPaymentEloquent({
                    verified_at: '2026-10-16T11:00:00Z',
                }),
            ];
            render(PaymentHistory, { props: { payments } });

            expect(screen.getByText('Terverifikasi')).toBeInTheDocument();
        });
    });

    // ==========================================
    // 3. MENAMPILKAN PEMBAYARAN (PaymentResource)
    // ==========================================
    describe('Displaying Payments (Resource)', () => {
        it('harus menampilkan jumlah dari resource', () => {
            const payments = [createMockPaymentResource({ jumlah: '2000000' })];
            render(PaymentHistory, { props: { payments } });

            // formatCurrency akan mengubah '2000000' menjadi 'Rp 2.000.000'
            expect(screen.getByText(/Rp 2.000.000/)).toBeInTheDocument();
        });

        it('harus menampilkan verifier jika ada', () => {
            const payments = [
                createMockPaymentResource({
                    verified_at: '2026-10-16T11:00:00Z',
                    verifier: {
                        id: '1',
                        name: 'Admin',
                        email: 'admin@test.com',
                    },
                }),
            ];
            render(PaymentHistory, { props: { payments } });

            expect(screen.getByText(/Diverifikasi oleh:/)).toBeInTheDocument();
            expect(screen.getByText('Admin')).toBeInTheDocument();
        });
    });

    // ==========================================
    // 4. AKSESIBILITAS (A11Y)
    // ==========================================
    describe('Accessibility', () => {
        it('pesan kosong harus memiliki role="status"', () => {
            render(PaymentHistory, { props: { payments: [] } });

            const emptyMessage = screen.getByRole('status');
            expect(emptyMessage).toHaveTextContent('Belum ada pembayaran');
        });
    });
});
