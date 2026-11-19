import { render, screen, fireEvent } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import Index from '../Index.svelte';

// ============================================================
// MOCKS
// ============================================================
const mockRouter = vi.hoisted(() => ({
    post: vi.fn(),
    visit: vi.fn(),
}));

vi.mock('@inertiajs/svelte', () => ({ router: mockRouter }));

vi.mock('@/utils/formatDate', () => ({
    formatDateTime: vi.fn((iso: string) => `Formatted: ${iso}`),
}));

vi.mock('@/utils/notificationConfig', () => ({
    getTipeConfig: vi.fn((tipe: string) => {
        if (tipe === 'booking_confirmed') {
            return { icon: 'icon-confirmed', bg: 'bg-green', text: 'text-green' };
        }
        if (tipe === 'payment_verified') {
            return { icon: 'icon-payment', bg: 'bg-blue', text: 'text-blue' };
        }
        return { icon: 'icon-default', bg: 'bg-gray-100', text: 'text-gray-500' };
    }),
}));

vi.mock('@/components/layout/ClientLayout.svelte', () => ({
    default: (anchor: Node, props: { children?: (anchor: Node) => void }) => {
        props.children?.(anchor);
        return {};
    },
}));

vi.mock('@/global/Pagination.svelte', () => ({
    default: (anchor: Node, props: { meta: { total: number }; label: string }) => {
        const el = document.createElement('div');
        el.setAttribute('data-testid', 'pagination');
        el.textContent = `Total ${props.meta.total} ${props.label}`;
        anchor.parentNode?.insertBefore(el, anchor);
        return {};
    },
}));

// ============================================================
// HELPERS
// ============================================================
const makeNotification = (overrides = {}) => ({
    id: 1,
    tipe: 'booking_confirmed',
    judul: 'Booking Terkonfirmasi',
    pesan: 'Pembayaran Anda telah diverifikasi.',
    data: { url: '/client/bookings/1' } as { url?: string } | null,
    read_at: null as string | null,
    created_at: '2026-01-15T10:00:00Z',
    ...overrides,
});

const makeNotifications = (data: any[], overrides = {}) => ({
    current_page: 1,
    last_page: 1,
    per_page: 15,
    total: data.length,
    from: data.length ? 1 : null,
    to: data.length ? data.length : null,
    links: [],
    data,
    ...overrides,
});

const renderPage = (
    notifications = makeNotifications([makeNotification()]),
    unreadCount = 1,
) =>
    render(Index, {
        props: { notifications, unreadCount },
    });

/**
 * Cari tombol notifikasi (bukan "Tandai Semua Dibaca").
 * Karena 2 tombol sama-sama role="button", kita filter berdasarkan konten.
 */
const getNotificationButton = () =>
    screen
        .getAllByRole('button')
        .find((btn) => btn.textContent?.includes('Booking Terkonfirmasi'))!;

// ============================================================
// TEST SUITE
// ============================================================
describe('Client Notifications — Index Page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        document.body.innerHTML = ''; // cleanup explicit
    });

    // ----------------------------------------------------------
    // HEADER
    // ----------------------------------------------------------
    describe('Rendering — Header', () => {
        it('menampilkan judul "Notifikasi"', () => {
            renderPage();
            expect(
                screen.getByRole('heading', { level: 1, name: 'Notifikasi' }),
            ).toBeInTheDocument();
        });

        it('menampilkan jumlah unread saat unreadCount > 0', () => {
            renderPage(makeNotifications([makeNotification()]), 3);
            expect(
                screen.getByText(/Anda memiliki 3 notifikasi belum dibaca/i),
            ).toBeInTheDocument();
        });

        it('menampilkan "Semua notifikasi sudah dibaca" saat unreadCount=0', () => {
            renderPage(makeNotifications([makeNotification()]), 0);
            expect(
                screen.getByText('Semua notifikasi sudah dibaca.'),
            ).toBeInTheDocument();
        });

        // ✅ FIX: pisah jadi 2 test (bukan render 2x dalam 1 it)
        it('menampilkan tombol "Tandai Semua Dibaca" saat unreadCount > 0', () => {
            renderPage(makeNotifications([makeNotification()]), 1);
            expect(
                screen.getByRole('button', { name: /Tandai Semua Dibaca/i }),
            ).toBeInTheDocument();
        });

        it('TIDAK menampilkan tombol "Tandai Semua Dibaca" saat unreadCount = 0', () => {
            renderPage(makeNotifications([makeNotification()]), 0);
            expect(
                screen.queryByRole('button', { name: /Tandai Semua Dibaca/i }),
            ).not.toBeInTheDocument();
        });
    });

    // ----------------------------------------------------------
    // EMPTY STATE
    // ----------------------------------------------------------
    describe('Rendering — Empty State', () => {
        it('menampilkan empty state saat data kosong', () => {
            renderPage(makeNotifications([]), 0);
            expect(screen.getByText('Belum ada notifikasi')).toBeInTheDocument();
        });

        it('TIDAK merender pagination saat data kosong', () => {
            renderPage(makeNotifications([]), 0);
            expect(screen.queryByTestId('pagination')).not.toBeInTheDocument();
        });
    });

    // ----------------------------------------------------------
    // LIST ITEM
    // ----------------------------------------------------------
    describe('Rendering — List Item', () => {
        it('menampilkan judul, pesan, dan tanggal', () => {
            renderPage();
            expect(screen.getByText('Booking Terkonfirmasi')).toBeInTheDocument();
            expect(screen.getByText('Pembayaran Anda telah diverifikasi.')).toBeInTheDocument();
            expect(screen.getByText('Formatted: 2026-01-15T10:00:00Z')).toBeInTheDocument();
        });

        it('menampilkan unread dot untuk notifikasi unread', () => {
            renderPage();
            expect(screen.getByLabelText('Belum dibaca')).toBeInTheDocument();
        });

        it('TIDAK menampilkan unread dot untuk notifikasi read', () => {
            renderPage(
                makeNotifications([makeNotification({ read_at: '2026-01-16T10:00:00Z' })]),
                0,
            );
            expect(screen.queryByLabelText('Belum dibaca')).not.toBeInTheDocument();
        });

        it('menampilkan "Lihat Detail" jika data.url ada', () => {
            renderPage();
            expect(screen.getByText('Lihat Detail')).toBeInTheDocument();
        });

        it('TIDAK menampilkan "Lihat Detail" jika data.url null', () => {
            renderPage(
                makeNotifications([makeNotification({ data: null })]),
                1,
            );
            expect(screen.queryByText('Lihat Detail')).not.toBeInTheDocument();
        });

        it('menggunakan config tipe untuk icon (getTipeConfig)', () => {
            renderPage();
            expect(document.querySelector('svg path[d="icon-confirmed"]')).toBeInTheDocument();
        });

        it('fallback ke config default jika tipe tidak dikenal', () => {
            renderPage(
                makeNotifications([makeNotification({ tipe: 'tipe_unknown' })]),
                1,
            );
            expect(document.querySelector('svg path[d="icon-default"]')).toBeInTheDocument();
        });
    });

    // ----------------------------------------------------------
    // MARK ALL READ
    // ----------------------------------------------------------
    describe('Interaction — Mark All Read', () => {
        it('klik tombol memanggil router.post dengan endpoint benar', async () => {
            renderPage(makeNotifications([makeNotification()]), 2);

            await fireEvent.click(
                screen.getByRole('button', { name: /Tandai Semua Dibaca/i }),
            );

            expect(mockRouter.post).toHaveBeenCalledWith(
                '/notifications/mark-all-read',
                {},
                { preserveScroll: true },
            );
        });
    });

    // ----------------------------------------------------------
    // CLICK READ
    // ----------------------------------------------------------
    describe('Interaction — Click (Read Notification)', () => {
        it('langsung visit URL jika sudah dibaca & punya URL', async () => {
            renderPage(
                makeNotifications([
                    makeNotification({ read_at: '2026-01-16T10:00:00Z' }),
                ]),
                0,
            );

            const btn = getNotificationButton();
            await fireEvent.click(btn);

            expect(mockRouter.visit).toHaveBeenCalledWith('/client/bookings/1');
            expect(mockRouter.post).not.toHaveBeenCalled();
        });

        it('TIDAK visit jika sudah dibaca tapi tidak ada URL', async () => {
            renderPage(
                makeNotifications([
                    makeNotification({
                        read_at: '2026-01-16T10:00:00Z',
                        data: null,
                    }),
                ]),
                0,
            );

            const btn = getNotificationButton();
            await fireEvent.click(btn);

            expect(mockRouter.visit).not.toHaveBeenCalled();
        });
    });

    // ----------------------------------------------------------
    // CLICK UNREAD
    // ----------------------------------------------------------
    describe('Interaction — Click (Unread Notification)', () => {
        it('memanggil router.post /read dengan onSuccess yang visit URL', async () => {
            renderPage();

            const btn = getNotificationButton(); // ✅ pakai helper
            await fireEvent.click(btn);

            expect(mockRouter.post).toHaveBeenCalledWith(
                '/notifications/1/read',
                {},
                expect.objectContaining({
                    preserveScroll: true,
                    onSuccess: expect.any(Function),
                }),
            );

            expect(mockRouter.visit).not.toHaveBeenCalled();

            const [, , options] = mockRouter.post.mock.calls[0];
            options.onSuccess();

            expect(mockRouter.visit).toHaveBeenCalledWith('/client/bookings/1');
        });

        it('TIDAK visit URL setelah onSuccess jika data.url tidak ada', async () => {
            renderPage(
                makeNotifications([makeNotification({ data: null })]),
                1,
            );

            const btn = getNotificationButton(); // ✅ pakai helper
            await fireEvent.click(btn);

            expect(mockRouter.post).toHaveBeenCalled();

            const [, , options] = mockRouter.post.mock.calls[0];
            options.onSuccess();

            expect(mockRouter.visit).not.toHaveBeenCalled();
        });
    });

    // ----------------------------------------------------------
    // PAGINATION
    // ----------------------------------------------------------
    describe('Rendering — Pagination', () => {
        it('merender pagination saat data ada', () => {
            renderPage();
            expect(screen.getByTestId('pagination')).toHaveTextContent(
                'Total 1 notifikasi',
            );
        });
    });
});