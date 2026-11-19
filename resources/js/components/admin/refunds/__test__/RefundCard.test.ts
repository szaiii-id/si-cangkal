import { render, screen } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import RefundCard from '../RefundCard.svelte';
import type { RefundResource } from '@/types/models';

// ==========================================
// MOCK INERTIA
// ==========================================
vi.mock('@inertiajs/svelte', () => ({
    inertia: vi.fn(),
}));

// ==========================================
// MOCK DATA (SESUAI RefundResource)
// ==========================================
const createMockRefund = (overrides: Partial<RefundResource> = {}): RefundResource => ({
    id: 1,
    booking_id: 100,
    mitra_id: 1,
    amount: '500000',
    amountRupiah: 'Rp 500.000',
    currency: 'IDR',
    reason: 'CANCELLATION',
    reasonLabel: 'Pembatalan oleh Mitra',
    status: 'PENDING',
    statusLabel: 'Sedang Diproses',
    isFinal: false,
    failure_code: null,
    refund_fee_amount: null,
    refundFeeRupiah: 'Rp 0',
    catatan: null,
    created_at: '2026-10-16T10:00:00Z',
    mitra: null,
    mitraInitiator: { id: 1, name: 'Mitra A', email: 'mitra@test.com' },
    booking: {
        id: 100,
        kode: 'BK-123',
        total_harga: '1000000',
        status: 'cancelled',
    },
    ...overrides,
});

describe('RefundCard Component', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    // ==========================================
    // 1. RENDERING DASAR
    // ==========================================
    describe('Rendering', () => {
        it('harus merender ID refund dan jumlah yang terformat', () => {
            render(RefundCard, { props: { refund: createMockRefund() } });

            expect(screen.getByText(/Refund ID: #1/)).toBeInTheDocument();
            expect(screen.getByText(/Rp 500.000/)).toBeInTheDocument();
        });

        it('harus merender kode pesanan', () => {
            render(RefundCard, { props: { refund: createMockRefund() } });

            expect(screen.getByText('BK-123')).toBeInTheDocument();
        });

        it('harus merender alasan refund', () => {
            render(RefundCard, { props: { refund: createMockRefund() } });

            expect(screen.getByText('Pembatalan oleh Mitra')).toBeInTheDocument();
        });

        it('harus merender nama pengaju (mitraInitiator)', () => {
            render(RefundCard, { props: { refund: createMockRefund() } });

            expect(screen.getByText('Mitra A')).toBeInTheDocument();
        });
    });

    // ==========================================
    // 2. STATUS & BADGE
    // ==========================================
    describe('Status Badge', () => {
        it('harus menampilkan label "Sedang Diproses" untuk status PENDING', () => {
            render(RefundCard, { props: { refund: createMockRefund({ status: 'PENDING' }) } });

            expect(screen.getByText('Sedang Diproses')).toBeInTheDocument();
        });

        it('harus menampilkan label "Berhasil" untuk status SUCCEEDED', () => {
            render(RefundCard, { props: { refund: createMockRefund({ status: 'SUCCEEDED' }) } });

            expect(screen.getByText('Berhasil')).toBeInTheDocument();
        });
    });

    // ==========================================
    // 3. FOOTER (PENDING vs SELESAI)
    // ==========================================
    describe('Footer Status', () => {
        it('harus menampilkan "Butuh Tindakan Mitra" jika status PENDING', () => {
            render(RefundCard, { props: { refund: createMockRefund({ status: 'PENDING' }) } });

            expect(screen.getByText('Butuh Tindakan Mitra')).toBeInTheDocument();
        });

        it('harus menampilkan "Selesai" jika status bukan PENDING', () => {
            render(RefundCard, { props: { refund: createMockRefund({ status: 'SUCCEEDED' }) } });

            expect(screen.getByText('Selesai')).toBeInTheDocument();
        });
    });

    // ==========================================
    // 4. AKSESIBILITAS (A11Y)
    // ==========================================
    describe('Accessibility', () => {
        it('tautan pesanan harus memiliki accessible name yang sesuai dan mengarah ke detail booking', () => {
            render(RefundCard, { props: { refund: createMockRefund() } });

            const link = screen.getByRole('link', { name: /BK-123/i });
            expect(link).toBeInTheDocument();
            expect(link).toHaveAttribute('href', '/admin/bookings/100');
        });
    });
});