/**
 * ============================================================
 * Mitra Bookings — Show Page — Component Test
 * ============================================================
 */

import { render, screen, fireEvent, within } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { tick } from 'svelte';
import Show from '../Show.svelte';

// ============================================================
// 1. HOISTED MOCK REGISTRY
// ============================================================
const mockUpdaters = vi.hoisted(() => new Set<() => void>());
const mockApi = vi.hoisted(() => ({
    verifyPayment: vi.fn(),
    rejectPayment: vi.fn(),
    cancelBooking: vi.fn(),
}));

// ============================================================
// 2. MOCK DEPENDENCIES
// ============================================================
vi.mock('@/api/mitra', () => mockApi);

vi.mock('@/utils/bookingRules', () => ({
    isPendingPayment: vi.fn((s: string) => s === 'pending_payment'),
    isFinalBooking: vi.fn((s: string) => ['completed', 'cancelled'].includes(s)),
    canVerifyPayment: vi.fn((s: string) => s === 'pending_payment'),
}));

vi.mock('@/utils/enums', () => ({
    bookingStatusBadge: vi.fn((s: string) => `badge-${s}`),
    bookingStatusLabel: vi.fn((s: string) => s),
    refundReasonLabel: vi.fn((r: string) => r),
}));

vi.mock('@/utils/formatCurrency', () => ({
    formatCurrency: vi.fn((v: string | number) => `Rp ${v}`),
}));

vi.mock('@/utils/formatDate', () => ({
    formatDate: vi.fn((d: string) => `Date: ${d}`),
    formatDateTime: vi.fn((d: string) => `DateTime: ${d}`),
}));

vi.mock('@/utils/toast.svelte', () => ({
    showToast: vi.fn(),
}));

vi.mock('@/utils/menus', () => ({
    mitraMenu: [],
}));

vi.mock('@inertiajs/svelte', () => ({
    inertia: vi.fn(),
}));

// ============================================================
// 3. MOCK CHILD COMPONENTS
// ============================================================
vi.mock('@/components/layout/DashboardLayout.svelte', () => ({
    default: (anchor: Node, props: any) => {
        props.children?.(anchor);
        return {};
    },
}));

vi.mock('@/global/BackButton.svelte', () => ({
    default: (anchor: Node, props: any) => {
        const el = document.createElement('div');
        el.setAttribute('data-testid', 'back-button');
        el.textContent = props.label;
        anchor.parentNode?.insertBefore(el, anchor);
        return {};
    },
}));

// MOCK CONFIRM MODAL DENGAN REASON INPUT
vi.mock('@/global/ConfirmModal.svelte', () => ({
    default: (anchor: Node, props: any) => {
        const el = document.createElement('div');
        anchor.parentNode?.insertBefore(el, anchor);

        const update = () => {
            el.innerHTML = '';
            if (!props.open) {
                el.removeAttribute('data-testid');
                return;
            }
            
            el.setAttribute('data-testid', `modal-${props.title.replace(/\s+/g, '-')}`);
            
            // Render reason input jika diperlukan
            const reasonHtml = props.requiresReason
                ? `<textarea data-action="reason" placeholder="${props.reasonPlaceholder || ''}">${props.reasonValue || ''}</textarea>
                   ${props.reasonError ? `<p data-testid="reason-error">${props.reasonError}</p>` : ''}`
                : '';

            el.innerHTML = `
                <h2>${props.title}</h2>
                <p>${props.description || ''}</p>
                ${reasonHtml}
                <button type="button" data-action="confirm">${props.confirmLabel}</button>
            `;

            // Sync textarea ke props.reasonValue
            const textarea = el.querySelector('[data-action="reason"]') as HTMLTextAreaElement | null;
            if (textarea) {
                textarea.oninput = (e) => {
                    props.reasonValue = (e.target as HTMLTextAreaElement).value;
                };
            }

            el.querySelector('[data-action="confirm"]')!.addEventListener('click', () => props.onConfirm());
        };

        update();
        mockUpdaters.add(update);
        return {};
    },
}));

vi.mock('@/components/booking/ParticipantList.svelte', () => ({
    default: (anchor: Node, props: any) => {
        const el = document.createElement('div');
        el.setAttribute('data-testid', 'participant-list');
        el.textContent = `${props.participants.length} peserta`;
        anchor.parentNode?.insertBefore(el, anchor);
        return {};
    },
}));

vi.mock('@/components/booking/PaymentHistory.svelte', () => ({
    default: (anchor: Node, props: any) => {
        const el = document.createElement('div');
        el.setAttribute('data-testid', 'payment-history');
        el.textContent = `${props.payments.length} pembayaran`;
        anchor.parentNode?.insertBefore(el, anchor);
        return {};
    },
}));

vi.mock('@/components/booking/RefundStatusCard.svelte', () => ({
    default: (anchor: Node, props: any) => {
        const el = document.createElement('div');
        el.setAttribute('data-testid', 'refund-status');
        el.textContent = `Refund: ${props.amount}`;
        anchor.parentNode?.insertBefore(el, anchor);
        return {};
    },
}));

vi.mock('@/components/shared/PaymentProofPreview.svelte', () => ({
    default: (anchor: Node, props: any) => {
        const el = document.createElement('div');
        el.setAttribute('data-testid', 'payment-proof');
        el.textContent = props.hasProof ? 'Proof: Ada' : 'Proof: Tidak Ada';
        anchor.parentNode?.insertBefore(el, anchor);
        return {};
    },
}));

vi.mock('@/components/mitra/bookings/UploadPaymentProofCard.svelte', () => ({
    default: (anchor: Node) => {
        const el = document.createElement('div');
        el.setAttribute('data-testid', 'upload-proof-card');
        anchor.parentNode?.insertBefore(el, anchor);
        return {};
    },
}));

vi.mock('@/components/mitra/bookings/VerifyPaymentCard.svelte', () => ({
    default: (anchor: Node, props: any) => {
        const el = document.createElement('div');
        el.setAttribute('data-testid', 'verify-card');
        el.innerHTML = `
            <button type="button" data-action="verify" ${!props.canVerify ? 'disabled' : ''}>Verify</button>
            <button type="button" data-action="reject" ${!props.canReject ? 'disabled' : ''}>Reject</button>
        `;
        anchor.parentNode?.insertBefore(el, anchor);

        el.querySelector('[data-action="verify"]')!.addEventListener('click', () => props.onVerify());
        el.querySelector('[data-action="reject"]')!.addEventListener('click', () => props.onReject());
        return {};
    },
}));

// ============================================================
// 4. HELPERS
// ============================================================
async function syncUI() {
    await tick();
    mockUpdaters.forEach(fn => fn());
}

// ============================================================
// 5. FIXTURES
// ============================================================
const makeBooking = (overrides: Record<string, unknown> = {}) =>
    ({
        id: 1,
        kode: 'BK-01',
        status: 'pending_payment',
        statusLabel: 'Menunggu Pembayaran',
        tanggal_keberangkatan: '2026-02-01',
        jumlah_peserta: 2,
        total_harga: '1500000',
        expired_at: null,
        catatan: null,
        created_at: '2026-01-01',
        has_payment_proof: false,
        package: { id: 1, nama: 'Paket Bali' },
        client: { id: 10, name: 'Budi', email: 'budi@test.com' },
        participants: [{ id: 1, nama: 'Budi' }],
        payments: [],
        refunds: [],
        ...overrides,
    }) as any;

const renderPage = (booking = makeBooking()) => 
    render(Show, { props: { booking } });

// ============================================================
// 6. TEST SUITE
// ============================================================
describe('Mitra Bookings — Show Page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        mockUpdaters.clear();
    });

    describe('Rendering', () => {
        it('merender header dengan data booking', () => {
            renderPage();
            expect(screen.getByRole('heading', { level: 1 })).toHaveTextContent('Paket Bali');
            expect(screen.getByText('BK-01')).toBeInTheDocument();
            expect(screen.getByText('Rp 1500000')).toBeInTheDocument();
            expect(screen.getByText('2 orang')).toBeInTheDocument();
        });

        it('merender client dan peserta', () => {
            renderPage();
            expect(screen.getByText('Budi')).toBeInTheDocument();
            expect(screen.getByText('budi@test.com')).toBeInTheDocument();
            expect(screen.getByTestId('participant-list')).toHaveTextContent('1 peserta');
        });
    });

    describe('Conditional Rendering — Payment Proof', () => {
        it('merender UploadPaymentProofCard jika pending & belum ada bukti', () => {
            renderPage(makeBooking({ status: 'pending_payment', has_payment_proof: false }));
            expect(screen.getByTestId('upload-proof-card')).toBeInTheDocument();
            expect(screen.queryByTestId('verify-card')).not.toBeInTheDocument();
        });

        it('merender VerifyPaymentCard jika pending & sudah ada bukti', () => {
            renderPage(makeBooking({ status: 'pending_payment', has_payment_proof: true }));
            expect(screen.getByTestId('verify-card')).toBeInTheDocument();
            expect(screen.queryByTestId('upload-proof-card')).not.toBeInTheDocument();
        });

        it('merender PaymentProofPreview jika status bukan pending & sudah ada bukti', () => {
            renderPage(makeBooking({ status: 'confirmed', has_payment_proof: true }));
            expect(screen.getByTestId('payment-proof')).toBeInTheDocument();
            expect(screen.queryByTestId('verify-card')).not.toBeInTheDocument();
            expect(screen.queryByTestId('upload-proof-card')).not.toBeInTheDocument();
        });
    });

    describe('Interaction — Verify Payment Flow', () => {
        it('membuka modal verifikasi saat tombol verify diklik', async () => {
            renderPage(makeBooking({ status: 'pending_payment', has_payment_proof: true }));
            
            await fireEvent.click(screen.getByText('Verify'));
            await syncUI();

            expect(screen.getByTestId('modal-Verifikasi-Pembayaran?')).toBeInTheDocument();
        });

        it('memanggil verifyPayment saat modal dikonfirmasi', async () => {
            mockApi.verifyPayment.mockImplementation((_id, opts) => {
                opts.onSuccess();
                opts.onFinish();
            });

            renderPage(makeBooking({ status: 'pending_payment', has_payment_proof: true }));
            await fireEvent.click(screen.getByText('Verify'));
            await syncUI();

            const modal = screen.getByTestId('modal-Verifikasi-Pembayaran?');
            await fireEvent.click(within(modal).getByText('Ya, Verifikasi'));
            await syncUI();

            expect(mockApi.verifyPayment).toHaveBeenCalledWith(1, expect.any(Object));
        });
    });

    describe('Interaction — Cancel Booking Flow', () => {
        it('merender tombol Batalkan Pesanan jika status belum final', () => {
            renderPage(makeBooking({ status: 'pending_payment' }));
            expect(screen.getByText('Batalkan Pesanan')).toBeInTheDocument();
        });

        it('merender tombol Batalkan & Refund jika status sudah bayar', () => {
            renderPage(makeBooking({ status: 'confirmed' }));
            expect(screen.getByText('Batalkan & Refund')).toBeInTheDocument();
        });

        it('membuka modal pembatalan saat tombol diklik', async () => {
            renderPage();
            await fireEvent.click(screen.getByText('Batalkan Pesanan'));
            await syncUI();

            expect(screen.getByTestId('modal-Batalkan-Booking?')).toBeInTheDocument();
        });

        it('memanggil cancelBooking saat modal dikonfirmasi (dengan alasan valid)', async () => {
            mockApi.cancelBooking.mockImplementation((_id, _data, opts) => {
                opts.onSuccess();
                opts.onFinish();
            });

            renderPage();
            await fireEvent.click(screen.getByText('Batalkan Pesanan'));
            await syncUI();

            // Isi alasan terlebih dahulu (validasi min 5 karakter)
            const modal = screen.getByTestId('modal-Batalkan-Booking?');
            const textarea = within(modal).getByPlaceholderText(/Jelaskan alasan pembatalan/i) as HTMLTextAreaElement;
            await fireEvent.input(textarea, { target: { value: 'Pelanggan minta batal' } });

            // Klik konfirmasi
            await fireEvent.click(within(modal).getByText('Ya, Batalkan'));
            await syncUI();

            expect(mockApi.cancelBooking).toHaveBeenCalledWith(
                1,
                { alasan: 'Pelanggan minta batal' },
                expect.any(Object)
            );
        });

        it('TIDAK memanggil cancelBooking jika alasan kurang dari 5 karakter', async () => {
            renderPage();
            await fireEvent.click(screen.getByText('Batalkan Pesanan'));
            await syncUI();

            const modal = screen.getByTestId('modal-Batalkan-Booking?');
            const textarea = within(modal).getByPlaceholderText(/Jelaskan alasan pembatalan/i);
            await fireEvent.input(textarea, { target: { value: 'abc' } });

            await fireEvent.click(within(modal).getByText('Ya, Batalkan'));
            await syncUI();

            expect(mockApi.cancelBooking).not.toHaveBeenCalled();
        });
    });
});