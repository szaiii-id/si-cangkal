/**
 * ============================================================
 * Admin Bookings — Show Page (Detail Pesanan) — Component Test
 * ============================================================
 *
 * Fokus pengujian:
 *   1. Isolation & Mocking  → Inertia, API regenerateETicket, toast,
 *                             menu, utils (enums, bookingRules,
 *                             formatCurrency, formatDate, formatDurasi),
 *                             & child components (BackButton,
 *                             ParticipantList, PaymentHistory,
 *                             PaymentProofPreview, ConfirmModal) di-mock.
 *   2. Props & Rendering    → header, banner pending/confirmed,
 *                             data client, peserta, pembayaran,
 *                             info paket, waktu & catatan.
 *   3. Interaction          → buka modal, konfirmasi regenerate,
 *                             success & error flow.
 *   4. Reactivity           → isPending, isConfirmedStatus,
 *                             canRegenerateETicket, modal state.
 *
 * Strategi:
 *   - Anchor-aware mock untuk semua child component.
 *   - Util di-mock deterministik (tidak bergantung timezone / Intl).
 *   - A11y-first query: `getByRole('button')`, `getByRole('dialog')`.
 *   - Capture callback `onFinish` untuk verifikasi state processing.
 * ============================================================
 */

import { render, screen, fireEvent } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { tick } from 'svelte';
import Show from '../Show.svelte';

// ============================================================
// 1. HOISTED MOCK REGISTRY
// ============================================================
const mockUpdaters = vi.hoisted(() => new Set<() => void>());

// ============================================================
// 2. MOCK — INERTIA
// ============================================================
vi.mock('@inertiajs/svelte', () => ({
    inertia: vi.fn(),
}));

// ============================================================
// 3. MOCK — API ADMIN
// ============================================================
vi.mock('@/api/admin', () => ({
    regenerateETicket: vi.fn(),
}));

// ============================================================
// 4. MOCK — TOAST
// ============================================================
vi.mock('@/utils/toast.svelte', () => ({
    showToast: vi.fn(),
}));

// ============================================================
// 5. MOCK — MENUS
// ============================================================
vi.mock('@/utils/menus', () => ({
    adminMenu: [],
}));

// ============================================================
// 6. MOCK — ENUMS
// ============================================================
vi.mock('@/utils/enums', () => ({
    bookingStatusBadge: vi.fn(() => 'bg-gray-100 text-gray-700'),
    bookingStatusLabel: vi.fn((s: string) => {
        const map: Record<string, string> = {
            pending_payment: 'Menunggu Pembayaran',
            payment_verified: 'Pembayaran Terverifikasi',
            confirmed: 'Terkonfirmasi',
            in_progress: 'Berlangsung',
            completed: 'Selesai',
            cancelled: 'Dibatalkan',
        };
        return map[s] ?? s;
    }),
}));

// ============================================================
// 7. MOCK — BOOKING RULES
// ============================================================
vi.mock('@/utils/bookingRules', () => ({
    isPendingPayment: vi.fn((s: string) => s === 'pending_payment'),
    isConfirmed: vi.fn((s: string) => s === 'confirmed'),
    requiresETicket: vi.fn((s: string) =>
        ['confirmed', 'in_progress', 'completed'].includes(s),
    ),
}));

// ============================================================
// 8. MOCK — FORMAT HELPERS (deterministik)
// ============================================================
vi.mock('@/utils/formatCurrency', () => ({
    formatCurrency: vi.fn(
        (v: string | number) => `Rp ${Number(v).toLocaleString('id-ID')}`,
    ),
}));

vi.mock('@/utils/formatDate', () => ({
    formatDate: vi.fn((iso: string) =>
        new Date(iso).toLocaleDateString('id-ID', {
            day: 'numeric',
            month: 'long',
            year: 'numeric',
        }),
    ),
}));

vi.mock('@/utils/duration', () => ({
    formatDurasi: vi.fn(
        (hari: number, malam: number) => `${hari}H / ${malam}M`,
    ),
}));

// ============================================================
// 9. MOCK — DASHBOARD LAYOUT
// ============================================================
vi.mock('@/components/layout/DashboardLayout.svelte', () => ({
    default: (anchor: Node, props: { children?: (anchor: Node) => void }) => {
        props.children?.(anchor);
        return {};
    },
}));

// ============================================================
// 10. MOCK — BACK BUTTON
// ============================================================
vi.mock('@/global/BackButton.svelte', () => ({
    default: (anchor: Node, props: { href: string; label?: string }) => {
        const link = document.createElement('a');
        link.href = props.href;
        link.textContent = props.label ?? 'Kembali';
        link.setAttribute('data-testid', 'back-button');
        anchor.parentNode?.insertBefore(link, anchor);
        return {};
    },
}));

// ============================================================
// 11. MOCK — PARTICIPANT LIST
// ============================================================
vi.mock('@/components/booking/ParticipantList.svelte', () => ({
    default: (anchor: Node, props: { participants: any[] }) => {
        const el = document.createElement('div');
        el.setAttribute('data-testid', 'participant-list');
        el.textContent = `${props.participants.length} peserta`;
        anchor.parentNode?.insertBefore(el, anchor);
        return {};
    },
}));

// ============================================================
// 12. MOCK — PAYMENT HISTORY
// ============================================================
vi.mock('@/components/booking/PaymentHistory.svelte', () => ({
    default: (anchor: Node, props: { payments: any[] }) => {
        const el = document.createElement('div');
        el.setAttribute('data-testid', 'payment-history');
        el.textContent = `${props.payments.length} transaksi`;
        anchor.parentNode?.insertBefore(el, anchor);
        return {};
    },
}));

// ============================================================
// 13. MOCK — PAYMENT PROOF PREVIEW
// ============================================================
vi.mock('@/components/shared/PaymentProofPreview.svelte', () => ({
    default: (
        anchor: Node,
        props: { hasProof: boolean; emptyMessage?: string },
    ) => {
        const el = document.createElement('div');
        el.setAttribute('data-testid', 'payment-proof');
        el.setAttribute('data-has-proof', String(props.hasProof));
        el.textContent = props.hasProof
            ? 'Bukti tersedia'
            : (props.emptyMessage ?? '');
        anchor.parentNode?.insertBefore(el, anchor);
        return {};
    },
}));

// ============================================================
// 14. MOCK — CONFIRM MODAL (reactive, callback)
// ============================================================
vi.mock('@/global/ConfirmModal.svelte', () => ({
    default: (
        anchor: Node,
        props: {
            open: boolean;
            title: string;
            description: string;
            confirmLabel: string;
            processing?: boolean;
            onConfirm?: () => void;
        },
    ) => {
        const el = document.createElement('div');
        el.setAttribute('data-testid', 'confirm-modal');
        anchor.parentNode?.insertBefore(el, anchor);

        const update = () => {
            el.innerHTML = '';
            if (!props.open) return;

            const heading = document.createElement('h3');
            heading.textContent = props.title;

            const desc = document.createElement('p');
            desc.textContent = props.description;

            const btn = document.createElement('button');
            btn.type = 'button';
            btn.textContent = props.confirmLabel;
            btn.disabled = Boolean(props.processing);
            btn.onclick = () => props.onConfirm?.();

            el.append(heading, desc, btn);
        };

        update();
        mockUpdaters.add(update);
        return {};
    },
}));

// ============================================================
// 15. STATIC IMPORT
// ============================================================
import { regenerateETicket } from '@/api/admin';
import { showToast } from '@/utils/toast.svelte';
import { formatCurrency } from '@/utils/formatCurrency';
import { formatDate } from '@/utils/formatDate';
import { formatDurasi } from '@/utils/duration';

// ============================================================
// 16. HELPERS
// ============================================================
async function syncUI(): Promise<void> {
    await tick();
    mockUpdaters.forEach((fn) => fn());
}

// ============================================================
// 17. FIXTURES
// ============================================================
const makeBooking = (overrides: Record<string, unknown> = {}) =>
    ({
        id: 1,
        kode: 'BK-2026-001',
        status: 'pending_payment',
        statusLabel: 'Menunggu Pembayaran',
        created_at: '2026-01-15T00:00:00Z',
        tanggal_keberangkatan: '2026-02-01T00:00:00Z',
        jumlah_peserta: 2,
        total_harga: 1500000,
        expired_at: '2026-01-20T12:00:00Z',
        catatan: 'Tolong siapkan kamar twin bed',
        has_payment_proof: false,
        client: { name: 'Budi Santoso', email: 'budi@test.com' },
        package: {
            nama: 'Paket Bali 3H2M',
            kode: 'PKT-BALI-001',
            durasi_hari: 3,
            durasi_malam: 2,
            harga_per_orang: 750000,
        },
        participants: [
            { id: 1, nama: 'Budi Santoso' },
            { id: 2, nama: 'Ani Santoso' },
        ],
        payments: [],
        ...overrides,
    }) as any;

const renderPage = (booking = makeBooking()) =>
    render(Show, { props: { booking } });

// ============================================================
// 18. TEST SUITE
// ============================================================
describe('Admin Bookings — Show Page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        mockUpdaters.clear();
    });

    // ----------------------------------------------------------
    // 18.1 RENDERING — Header & Status Badge
    // ----------------------------------------------------------
    describe('Rendering — Header', () => {
        it('menampilkan kode, judul paket, dan back button', () => {
            renderPage();

            expect(screen.getByText('BK-2026-001')).toBeInTheDocument();
            expect(
                screen.getByRole('heading', {
                    level: 1,
                    name: 'Paket Bali 3H2M',
                }),
            ).toBeInTheDocument();
            expect(screen.getByTestId('back-button')).toHaveAttribute(
                'href',
                '/admin/bookings',
            );
        });

        it('menampilkan badge status (via mock enums)', () => {
            renderPage();
            expect(screen.getByText('Menunggu Pembayaran')).toBeInTheDocument();
        });

        it('menampilkan tanggal berangkat, jumlah peserta, dan total harga', () => {
            renderPage();

            expect(formatDate).toHaveBeenCalledWith('2026-02-01T00:00:00Z');
            expect(screen.getByText('2 orang')).toBeInTheDocument();
            expect(formatCurrency).toHaveBeenCalledWith(1500000);
        });

        it('menampilkan placeholder saat package null', () => {
            renderPage(makeBooking({ package: null }));
            expect(
                screen.getByRole('heading', {
                    level: 1,
                    name: 'Paket dihapus',
                }),
            ).toBeInTheDocument();
        });
    });

    // ----------------------------------------------------------
    // 18.2 RENDERING — Banner Pending
    // ----------------------------------------------------------
    describe('Rendering — Banner', () => {
        it('menampilkan banner "Menunggu Konfirmasi" saat status pending_payment', () => {
            renderPage();

            expect(
                screen.getByText('Menunggu Konfirmasi Pembayaran dari Mitra'),
            ).toBeInTheDocument();
        });

        it('TIDAK menampilkan banner pending saat status confirmed', () => {
            renderPage(makeBooking({ status: 'confirmed' }));
            expect(
                screen.queryByText('Menunggu Konfirmasi Pembayaran dari Mitra'),
            ).not.toBeInTheDocument();
        });

        it('menampilkan banner "Pesanan Telah Dikonfirmasi" saat status confirmed', () => {
            renderPage(makeBooking({ status: 'confirmed' }));
            expect(
                screen.getByText('Pesanan Telah Dikonfirmasi'),
            ).toBeInTheDocument();
        });
    });

    // ----------------------------------------------------------
    // 18.3 RENDERING — Data Client
    // ----------------------------------------------------------
    describe('Rendering — Client', () => {
        it('menampilkan nama dan email client', () => {
            renderPage();

            expect(screen.getByText('Budi Santoso')).toBeInTheDocument();
            expect(screen.getByText('budi@test.com')).toBeInTheDocument();
        });

        it('menampilkan placeholder saat client null', () => {
            renderPage(makeBooking({ client: null }));
            expect(
                screen.getByText('Data client sudah tidak tersedia.'),
            ).toBeInTheDocument();
        });
    });

    // ----------------------------------------------------------
    // 18.4 RENDERING — Participants & Payments
    // ----------------------------------------------------------
    describe('Rendering — Participant & Payment', () => {
        it('merender ParticipantList dengan jumlah peserta benar', () => {
            renderPage();
            expect(screen.getByTestId('participant-list')).toHaveTextContent(
                '2 peserta',
            );
        });

        it('merender PaymentProofPreview dengan hasProof=false', () => {
            renderPage();
            const proof = screen.getByTestId('payment-proof');
            expect(proof).toHaveAttribute('data-has-proof', 'false');
            expect(proof).toHaveTextContent(
                'Client belum mengunggah bukti transfer.',
            );
        });

        it('merender PaymentProofPreview dengan hasProof=true', () => {
            renderPage(makeBooking({ has_payment_proof: true }));
            expect(screen.getByTestId('payment-proof')).toHaveAttribute(
                'data-has-proof',
                'true',
            );
        });

        it('TIDAK merender PaymentHistory saat payments kosong', () => {
            renderPage();
            expect(
                screen.queryByTestId('payment-history'),
            ).not.toBeInTheDocument();
        });

        it('merender PaymentHistory saat payments ada', () => {
            renderPage(
                makeBooking({
                    payments: [{ id: 1 }, { id: 2 }],
                }),
            );
            expect(screen.getByTestId('payment-history')).toHaveTextContent(
                '2 transaksi',
            );
        });
    });

    // ----------------------------------------------------------
    // 18.5 RENDERING — Info Paket & Waktu
    // ----------------------------------------------------------
    describe('Rendering — Info Paket & Waktu', () => {
        it('menampilkan info paket dengan durasi & harga', () => {
            renderPage();

            expect(screen.getByText('Info Paket')).toBeInTheDocument();
            expect(screen.getByText('PKT-BALI-001')).toBeInTheDocument();
            expect(formatDurasi).toHaveBeenCalledWith(3, 2);
            expect(formatCurrency).toHaveBeenCalledWith(750000);
        });

        it('TIDAK merender Info Paket saat package null', () => {
            renderPage(makeBooking({ package: null }));
            expect(screen.queryByText('Info Paket')).not.toBeInTheDocument();
        });

        it('menampilkan batas transfer & catatan', () => {
            renderPage();

            expect(screen.getByText('Batas Transfer')).toBeInTheDocument();
            expect(screen.getByText('Catatan Tambahan')).toBeInTheDocument();
            expect(
                screen.getByText('Tolong siapkan kamar twin bed'),
            ).toBeInTheDocument();
        });

        it('menampilkan placeholder "Tidak ada catatan" saat catatan null', () => {
            renderPage(makeBooking({ catatan: null }));
            expect(
                screen.getByText('Tidak ada catatan dari pelanggan.'),
            ).toBeInTheDocument();
        });

        it('menampilkan "Tidak ada batas waktu" saat expired_at null', () => {
            renderPage(makeBooking({ expired_at: null }));
            expect(
                screen.getByText('Tidak ada batas waktu'),
            ).toBeInTheDocument();
        });
    });

    // ----------------------------------------------------------
    // 18.6 RENDERING — Regenerate Button (conditional)
    // ----------------------------------------------------------
    describe('Rendering — Regenerate Button', () => {
        it('TIDAK menampilkan tombol regenerate saat status pending_payment', () => {
            renderPage();
            expect(
                screen.queryByRole('button', { name: /Regenerate E-Ticket/i }),
            ).not.toBeInTheDocument();
        });

        it('menampilkan tombol regenerate saat status confirmed', () => {
            renderPage(makeBooking({ status: 'confirmed' }));
            expect(
                screen.getByRole('button', { name: /Regenerate E-Ticket/i }),
            ).toBeInTheDocument();
        });

        it('menampilkan tombol regenerate saat status completed', () => {
            renderPage(makeBooking({ status: 'completed' }));
            expect(
                screen.getByRole('button', { name: /Regenerate E-Ticket/i }),
            ).toBeInTheDocument();
        });
    });

    // ----------------------------------------------------------
    // 18.7 INTERACTION — Modal Open/Close
    // ----------------------------------------------------------
    describe('Interaction — Modal', () => {
        it('klik tombol regenerate membuka ConfirmModal', async () => {
            renderPage(makeBooking({ status: 'confirmed' }));

            await fireEvent.click(
                screen.getByRole('button', { name: /Regenerate E-Ticket/i }),
            );
            await syncUI();

            const modal = screen.getByTestId('confirm-modal');
            expect(modal).toHaveTextContent('Regenerate E-Ticket?');
            expect(modal).toHaveTextContent('Ya, Regenerate');
        });

        it('modal tidak muncul saat modal closed (default)', () => {
            renderPage(makeBooking({ status: 'confirmed' }));
            const modal = screen.getByTestId('confirm-modal');
            expect(modal).toBeEmptyDOMElement();
        });
    });

    // ----------------------------------------------------------
    // 18.8 INTERACTION — Regenerate Flow
    // ----------------------------------------------------------
    describe('Interaction — Regenerate', () => {
        it('klik "Ya, Regenerate" memanggil API & menampilkan toast success', async () => {
            (regenerateETicket as any).mockImplementation(
                (_id: number, opts: any) => {
                    opts.onSuccess?.();
                    opts.onFinish?.();
                },
            );

            renderPage(makeBooking({ status: 'confirmed' }));
            await fireEvent.click(
                screen.getByRole('button', { name: /Regenerate E-Ticket/i }),
            );
            await syncUI();
            await fireEvent.click(
                screen.getByRole('button', { name: 'Ya, Regenerate' }),
            );
            await syncUI();

            expect(regenerateETicket).toHaveBeenCalledWith(
                1,
                expect.any(Object),
            );
            expect(showToast).toHaveBeenCalledWith(
                'E-Ticket berhasil di-generate ulang.',
                'success',
            );
        });

        it('menonaktifkan tombol selama processing', async () => {
            let finishCallback: (() => void) | undefined;
            (regenerateETicket as any).mockImplementation(
                (_id: number, opts: any) => {
                    finishCallback = opts.onFinish;
                },
            );

            renderPage(makeBooking({ status: 'confirmed' }));
            await fireEvent.click(
                screen.getByRole('button', { name: /Regenerate E-Ticket/i }),
            );
            await syncUI();
            await fireEvent.click(
                screen.getByRole('button', { name: 'Ya, Regenerate' }),
            );
            await syncUI();

            // Query ulang — mock re-render innerHTML
            expect(
                screen.getByRole('button', { name: 'Ya, Regenerate' }),
            ).toBeDisabled();

            finishCallback?.();
            await syncUI();

            expect(
                screen.getByRole('button', { name: 'Ya, Regenerate' }),
            ).not.toBeDisabled();
        });

        it('menampilkan toast error saat API reject', async () => {
            (regenerateETicket as any).mockImplementation(
                (_id: number, opts: any) => {
                    opts.onError?.({ error: 'Queue worker mati' });
                    opts.onFinish?.();
                },
            );

            renderPage(makeBooking({ status: 'confirmed' }));
            await fireEvent.click(
                screen.getByRole('button', { name: /Regenerate E-Ticket/i }),
            );
            await syncUI();
            await fireEvent.click(
                screen.getByRole('button', { name: 'Ya, Regenerate' }),
            );
            await syncUI();

            expect(showToast).toHaveBeenCalledWith(
                'Queue worker mati',
                'error',
            );
        });

        it('menampilkan pesan error fallback jika onError tanpa field error', async () => {
            (regenerateETicket as any).mockImplementation(
                (_id: number, opts: any) => {
                    opts.onError?.({});
                    opts.onFinish?.();
                },
            );

            renderPage(makeBooking({ status: 'confirmed' }));
            await fireEvent.click(
                screen.getByRole('button', { name: /Regenerate E-Ticket/i }),
            );
            await syncUI();
            await fireEvent.click(
                screen.getByRole('button', { name: 'Ya, Regenerate' }),
            );
            await syncUI();

            expect(showToast).toHaveBeenCalledWith(
                'Gagal regenerate E-Ticket.',
                'error',
            );
        });

        it('modal tertutup setelah onSuccess', async () => {
            (regenerateETicket as any).mockImplementation(
                (_id: number, opts: any) => {
                    opts.onSuccess?.();
                    opts.onFinish?.();
                },
            );

            renderPage(makeBooking({ status: 'confirmed' }));
            await fireEvent.click(
                screen.getByRole('button', { name: /Regenerate E-Ticket/i }),
            );
            await syncUI();
            await fireEvent.click(
                screen.getByRole('button', { name: 'Ya, Regenerate' }),
            );
            await syncUI();

            expect(screen.getByTestId('confirm-modal')).toBeEmptyDOMElement();
        });
    });

    // ----------------------------------------------------------
    // 18.9 A11Y
    // ----------------------------------------------------------
    describe('Accessibility', () => {
        it('heading h1 untuk nama paket', () => {
            renderPage();
            expect(
                screen.getByRole('heading', { level: 1 }),
            ).toBeInTheDocument();
        });

        it('heading h2 untuk section Data Pemesan, Peserta, Pembayaran', () => {
            renderPage();
            expect(
                screen.getByRole('heading', { level: 2, name: 'Data Pemesan' }),
            ).toBeInTheDocument();
            expect(
                screen.getByRole('heading', {
                    level: 2,
                    name: 'Daftar Peserta',
                }),
            ).toBeInTheDocument();
            expect(
                screen.getByRole('heading', { level: 2, name: 'Pembayaran' }),
            ).toBeInTheDocument();
        });
    });
});
