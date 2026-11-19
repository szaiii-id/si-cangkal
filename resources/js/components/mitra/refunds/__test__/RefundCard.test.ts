import { render, screen, fireEvent } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import RefundCard from '../RefundCard.svelte';

// ==========================================
// MOCK DATA (Tipe yang lebih longgar)
// ==========================================
type RefundItem = {
    id: number;
    booking_id: number;
    mitra_id: number;
    amount: string;
    currency: string;
    reason: string;
    status: string;
    failure_code: string | null;
    refund_fee_amount: string | null;
    initiated_by_mitra: number | null;
    catatan: string | null;
    created_at: string;
    updated_at: string;
    booking: {
        id: number;
        kode: string;
        total_harga: string;
        status: string;
        client: { id: number; name: string; email: string } | null;
    } | null;
    mitra: null;
};

const createMockRefund = (overrides: Partial<RefundItem> = {}): RefundItem => ({
    id: 1,
    booking_id: 100,
    mitra_id: 1,
    amount: '500000',
    currency: 'IDR',
    reason: 'CANCELLATION',
    status: 'PENDING',
    failure_code: null,
    refund_fee_amount: null,
    initiated_by_mitra: null,
    catatan: null,
    created_at: '2026-10-16T10:00:00Z',
    updated_at: '2026-10-16T10:00:00Z',
    booking: {
        id: 100,
        kode: 'BK-123',
        total_harga: '1000000',
        status: 'cancelled',
        client: { id: 1, name: 'Budi', email: 'budi@test.com' },
    },
    mitra: null,
    ...overrides,
});

describe('RefundCard Component (Mitra)', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    // ==========================================
    // 1. RENDERING DASAR
    // ==========================================
    describe('Rendering', () => {
        it('harus merender ID refund dan jumlah', () => {
            render(RefundCard, {
                props: { refund: createMockRefund(), onApprove: vi.fn(), onReject: vi.fn() },
            });

            expect(screen.getByText(/#1/)).toBeInTheDocument();
            expect(screen.getByText(/Rp 500.000/)).toBeInTheDocument();
        });

        it('harus merender kode pesanan dan client', () => {
            render(RefundCard, {
                props: { refund: createMockRefund(), onApprove: vi.fn(), onReject: vi.fn() },
            });

            expect(screen.getByText('BK-123')).toBeInTheDocument();
            expect(screen.getByText('Budi')).toBeInTheDocument();
        });

        it('harus merender alasan refund', () => {
            render(RefundCard, {
                props: { refund: createMockRefund(), onApprove: vi.fn(), onReject: vi.fn() },
            });

            expect(screen.getByText('Pembatalan oleh Mitra')).toBeInTheDocument();
        });
    });

    // ==========================================
    // 2. TOMBOL AKSI (PENDING)
    // ==========================================
    describe('Action Buttons (PENDING)', () => {
        it('harus merender tombol "Setujui" dan "Tolak" jika status PENDING', () => {
            render(RefundCard, {
                props: { refund: createMockRefund({ status: 'PENDING' }), onApprove: vi.fn(), onReject: vi.fn() },
            });

            expect(screen.getByLabelText(/Setujui refund/)).toBeInTheDocument();
            expect(screen.getByLabelText(/Tolak pengajuan refund/)).toBeInTheDocument();
        });

        it('harus memanggil onApprove saat tombol Setujui diklik', async () => {
            const onApprove = vi.fn();
            render(RefundCard, {
                props: { refund: createMockRefund({ status: 'PENDING' }), onApprove, onReject: vi.fn() },
            });

            await fireEvent.click(screen.getByLabelText(/Setujui refund/));
            expect(onApprove).toHaveBeenCalledTimes(1);
        });

        it('harus memanggil onReject saat tombol Tolak diklik', async () => {
            const onReject = vi.fn();
            render(RefundCard, {
                props: { refund: createMockRefund({ status: 'PENDING' }), onApprove: vi.fn(), onReject },
            });

            await fireEvent.click(screen.getByLabelText(/Tolak pengajuan refund/));
            expect(onReject).toHaveBeenCalledTimes(1);
        });
    });

    // ==========================================
    // 3. STATUS FINAL (BUKAN PENDING)
    // ==========================================
    describe('Final Status', () => {
        it('harus menampilkan pesan "Dana telah dikembalikan" jika SUCCEEDED', () => {
            render(RefundCard, {
                props: { refund: createMockRefund({ status: 'SUCCEEDED' }), onApprove: vi.fn(), onReject: vi.fn() },
            });

            expect(screen.getByText(/Dana telah dikembalikan/)).toBeInTheDocument();
            expect(screen.queryByRole('button')).not.toBeInTheDocument();
        });

        it('harus menampilkan pesan "dibatalkan" jika CANCELLED', () => {
            render(RefundCard, {
                props: { refund: createMockRefund({ status: 'CANCELLED' }), onApprove: vi.fn(), onReject: vi.fn() },
            });

            expect(screen.getByText(/Pengajuan refund dibatalkan/)).toBeInTheDocument();
        });

        it('harus menampilkan pesan "gagal diproses" jika FAILED', () => {
            render(RefundCard, {
                props: { refund: createMockRefund({ status: 'FAILED' }), onApprove: vi.fn(), onReject: vi.fn() },
            });

            expect(screen.getByText(/Pengembalian dana gagal diproses/)).toBeInTheDocument();
        });
    });

    // ==========================================
    // 4. AKSESIBILITAS (A11Y)
    // ==========================================
    describe('Accessibility', () => {
        it('pesan status final harus memiliki role="status"', () => {
            render(RefundCard, {
                props: { refund: createMockRefund({ status: 'SUCCEEDED' }), onApprove: vi.fn(), onReject: vi.fn() },
            });

            const statusElement = screen.getByRole('status');
            expect(statusElement).toHaveTextContent(/Dana telah dikembalikan/);
        });
    });
});