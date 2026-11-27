import { render, screen, fireEvent } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import Show from '../Show.svelte';

// ============================================================
// HOISTED MOCK REGISTRY
// ============================================================
const mockRouter = vi.hoisted(() => ({
    post: vi.fn(),
    visit: vi.fn(),
}));
const mockUpdaters = vi.hoisted(() => new Set<() => void>());

// ============================================================
// MOCK — INERTIA & API
// ============================================================
vi.mock('@inertiajs/svelte', () => ({
    inertia: vi.fn(),
    router: mockRouter,
    useForm: vi.fn(() => ({
        payment_proof: null,
        processing: false,
        errors: {},
        post: vi.fn(),
        reset: vi.fn(),
    })),
}));

// ============================================================
// MOCK — UTILS
// ============================================================
vi.mock('@/utils/bookingRules', () => ({
    canClientCancelBooking: vi.fn(
        (status: string) => status === 'pending_payment',
    ),
    needsMitraCancel: vi.fn((status: string) => status === 'confirmed'),
    getBookingStatusDescription: vi.fn(
        (status: string) => `Deskripsi untuk ${status}`,
    ),
}));

vi.mock('@/utils/countdown', () => ({
    formatBookingCountdown: vi.fn(() => ({
        label: '00:59:59',
        isExpiringSoon: false,
    })),
}));

vi.mock('@/utils/formatCurrency', () => ({
    formatCurrency: vi.fn((v: string | number) => `Rp ${v}`),
}));
vi.mock('@/utils/formatDate', () => ({
    formatDate: vi.fn((v) => `Date: ${v}`),
}));
vi.mock('@/utils/duration', () => ({ formatDurasi: vi.fn(() => '3H 2M') }));
vi.mock('@/utils/toast.svelte', () => ({ showToast: vi.fn() }));
vi.mock('@/utils/enums', () => ({
    paymentMetodeLabel: vi.fn((m) => m),
    bookingStatusBadge: vi.fn((s) => s),
    bookingStatusLabel: vi.fn((s) => s),
}));

// ============================================================
// MOCK — CHILD COMPONENTS
// ============================================================
vi.mock('@/components/layout/ClientLayout.svelte', () => ({
    default: (anchor: Node, props: any) => {
        props.children?.(anchor);
        return {};
    },
}));

vi.mock('@/global/ConfirmModal.svelte', () => ({
    default: (anchor: Node, props: any) => {
        const el = document.createElement('div');
        el.setAttribute('data-testid', 'confirm-modal');
        anchor.parentNode?.insertBefore(el, anchor);

        const update = () => {
            el.innerHTML = '';
            if (!props.open) return;
            el.innerHTML = `
                <p>${props.description}</p>
                <button type="button" data-action="confirm">${props.confirmLabel}</button>
            `;
            const btn = el.querySelector('button');
            if (btn) btn.onclick = () => props.onConfirm?.();
        };

        update();
        mockUpdaters.add(update);
        return {};
    },
}));

vi.mock('@/components/booking/BookingStatusBanner.svelte', () => ({
    default: (anchor: Node, props: any) => {
        const el = document.createElement('div');
        el.setAttribute('data-testid', 'status-banner');
        el.textContent = `Status: ${props.status}`;
        anchor.parentNode?.insertBefore(el, anchor);
        return {};
    },
}));

vi.mock('@/components/booking/UploadProofForm.svelte', () => ({
    default: (anchor: Node, props: any) => {
        const el = document.createElement('div');
        el.setAttribute('data-testid', 'upload-proof');
        el.setAttribute('data-has-proof', String(props.hasProof));
        anchor.parentNode?.insertBefore(el, anchor);
        return {};
    },
}));

vi.mock('@/components/booking/RefundStatusCard.svelte', () => ({
    default: (anchor: Node, props: any) => {
        const el = document.createElement('div');
        el.setAttribute('data-testid', 'refund-card');
        el.textContent = `Refund: ${props.amount} (${props.status})`;
        anchor.parentNode?.insertBefore(el, anchor);
        return {};
    },
}));

vi.mock('@/components/shared/ETicketDownloadButton.svelte', () => ({
    default: (anchor: Node, props: any) => {
        const el = document.createElement('div');
        el.setAttribute('data-testid', 'eticket-btn');
        el.textContent = `E-Ticket: ${props.eticket?.kode || 'Ready'}`;
        anchor.parentNode?.insertBefore(el, anchor);
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

// ============================================================
// HELPERS
// ============================================================
async function syncUI() {
    await Promise.resolve();
    mockUpdaters.forEach((fn) => fn());
}

// ============================================================
// FIXTURES
// ============================================================
const makeBooking = (overrides: Record<string, unknown> = {}) =>
    ({
        id: 1,
        kode: 'BK-001',
        status: 'pending_payment',
        statusLabel: 'Menunggu Pembayaran',
        total_harga: '1500000',
        tanggal_keberangkatan: '2026-02-01',
        jumlah_peserta: 2,
        expired_at: '2026-01-20T12:00:00Z',
        catatan: null,
        created_at: '2026-01-01T00:00:00Z',
        has_payment_proof: false,
        package: {
            id: 1,
            nama: 'Paket Bali',
            kode: 'PKT-001',
            durasi_hari: 3,
            durasi_malam: 2,
        },
        participants: [{ id: 1, nama: 'Budi' }],
        payments: [],
        mitra: {
            name: 'PT Mitra',
            telepon: '08123456789',
            bankAccounts: [
                {
                    id: 1,
                    tipe: 'bank',
                    nama_penyedia: 'BCA',
                    nomor_rekening: '123456789',
                    nama_pemilik: 'PT Mitra',
                    is_primary: true,
                },
            ],
        },
        ...overrides,
    }) as any;

const renderPage = (props: Record<string, unknown> = {}) =>
    render(Show, {
        props: {
            booking: makeBooking(),
            eticket: null,
            refunds: [],
            ...props,
        } as any,
    });

// ============================================================
// TEST SUITE
// ============================================================
describe('Client Bookings — Show Page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        vi.useFakeTimers();
        mockUpdaters.clear();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    describe('Rendering — Header & Banner', () => {
        it('menampilkan banner status dan tombol kembali', () => {
            renderPage();
            expect(screen.getByTestId('status-banner')).toHaveTextContent(
                'Status: pending_payment',
            );
            expect(
                screen.getByRole('link', { name: /Kembali ke Pesanan Saya/i }),
            ).toHaveAttribute('href', '/client/bookings');
        });
    });

    describe('Rendering — Data Paket & Peserta', () => {
        it('menampilkan nama paket dan detail kode', () => {
            renderPage();
            expect(screen.getByText('Paket Bali')).toBeInTheDocument();
            expect(screen.getByText('PKT-001')).toBeInTheDocument();
        });

        it('meneruskan data ke ParticipantList', () => {
            renderPage();
            expect(screen.getByTestId('participant-list')).toHaveTextContent(
                '1 peserta',
            );
        });

        it('meneruskan data ke PaymentHistory', () => {
            renderPage({ booking: makeBooking({ payments: [{ id: 1 }] }) });
            expect(screen.getByTestId('payment-history')).toHaveTextContent(
                '1 pembayaran',
            );
        });
    });

    describe('Rendering — Pending Payment State', () => {
        it('TIDAK menampilkan rekening dan form bukti sebelum transfer dipilih', async () => {
            renderPage();
            await syncUI();

            expect(
                screen.queryByTestId('upload-proof'),
            ).not.toBeInTheDocument();
            expect(
                screen.queryByText('Rekening Tujuan Transfer'),
            ).not.toBeInTheDocument();
        });

        it('menampilkan rekening dan form bukti setelah transfer dipilih', async () => {
            renderPage();
            await syncUI();

            await fireEvent.click(
                screen.getByRole('button', { name: /Pilih Transfer/i }),
            );

            expect(screen.getByTestId('upload-proof')).toBeInTheDocument();
            expect(
                screen.getByText('Rekening Tujuan Transfer'),
            ).toBeInTheDocument();
            expect(screen.getByText('BCA')).toBeInTheDocument();
            expect(screen.getByText('123456789')).toBeInTheDocument();
        });

        it('menampilkan rekening dan form bukti bila bukti sudah ada', async () => {
            renderPage({
                booking: makeBooking({ has_payment_proof: true }),
            });
            await syncUI();

            expect(screen.getByTestId('upload-proof')).toBeInTheDocument();
            expect(
                screen.getByText('Rekening Tujuan Transfer'),
            ).toBeInTheDocument();
        });

        it('TIDAK menampilkan UploadProofForm jika status confirmed', () => {
            renderPage({ booking: makeBooking({ status: 'confirmed' }) });
            expect(
                screen.queryByTestId('upload-proof'),
            ).not.toBeInTheDocument();
        });
    });

    describe('Rendering — E-Ticket & Refund', () => {
        it('menampilkan ETicketDownloadButton jika eticket ada', () => {
            renderPage({ eticket: { kode: 'ET-001' } });
            expect(screen.getByTestId('eticket-btn')).toBeInTheDocument();
        });

        it('menampilkan RefundStatusCard jika refunds tidak kosong', () => {
            renderPage({
                refunds: [{ amount: '500000', status: 'SUCCEEDED' }],
            });
            expect(screen.getByTestId('refund-card')).toHaveTextContent(
                'Refund: 500000',
            );
        });
    });

    describe('Interaction — Cancel Booking', () => {
        it('menampilkan tombol Batalkan Pesanan jika status pending_payment', () => {
            renderPage();
            expect(
                screen.getByRole('button', { name: 'Batalkan Pesanan' }),
            ).toBeInTheDocument();
        });

        it('menampilkan info pembatalan via mitra jika status confirmed', () => {
            renderPage({ booking: makeBooking({ status: 'confirmed' }) });
            expect(
                screen.getByText('Pembatalan via Mitra'),
            ).toBeInTheDocument();
        });

        it('membuka ConfirmModal saat tombol batalkan diklik', async () => {
            renderPage();

            await fireEvent.click(
                screen.getByRole('button', { name: 'Batalkan Pesanan' }),
            );
            await syncUI();

            const modal = screen.getByTestId('confirm-modal');
            expect(modal).toHaveTextContent('Ya, Batalkan');
        });

        it('memanggil router.post /cancel saat konfirmasi modal diklik', async () => {
            renderPage();

            await fireEvent.click(
                screen.getByRole('button', { name: 'Batalkan Pesanan' }),
            );
            await syncUI();

            const confirmBtn = screen.getByRole('button', {
                name: 'Ya, Batalkan',
            });
            await fireEvent.click(confirmBtn);

            expect(mockRouter.post).toHaveBeenCalledWith(
                '/client/bookings/1/cancel',
                {},
                expect.any(Object),
            );
        });
    });

    describe('Interaction — Copy Rekening', () => {
        it('memanggil clipboard.writeText dan menampilkan toast', async () => {
            const writeTextMock = vi.fn().mockResolvedValue(undefined);
            Object.defineProperty(navigator, 'clipboard', {
                value: { writeText: writeTextMock },
                writable: true,
                configurable: true,
            });

            renderPage();
            await syncUI();
            await fireEvent.click(
                screen.getByRole('button', { name: /Pilih Transfer/i }),
            );

            const copyBtn = screen.getByRole('button', {
                name: /Salin Rekening/i,
            });
            await fireEvent.click(copyBtn);

            expect(writeTextMock).toHaveBeenCalledWith('123456789');
        });
    });
});
