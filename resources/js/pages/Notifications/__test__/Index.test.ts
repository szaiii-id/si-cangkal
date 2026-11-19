import { render, screen, fireEvent } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import NotificationsIndex from '../Index.svelte';

// ==========================================
// 1. MOCK DEPENDENSI INTERNAL DASHBOARDLAYOUT
// ==========================================
vi.mock('@/components/ui/ToastContainer.svelte', () => ({
    default: () => '<div data-testid="toast-container"></div>',
}));

vi.mock('@/components/NotificationPermissionBanner.svelte', () => ({
    default: () => '<div data-testid="notification-banner"></div>',
}));

vi.mock('@/lib/firebase', () => ({
    isFcmSupported: vi.fn().mockReturnValue(false),
}));

vi.mock('@/api/auth', () => ({
    logout: vi.fn(),
}));

// ==========================================
// 2. MOCK INERTIA (page, router, inertia)
// ==========================================
const mockPage = vi.hoisted(() => ({
    props: {
        auth: { user: { id: 1, name: 'Admin', role: 'admin', has_fcm_token: true } },
        flash: {},
        unread_notifications: 1,
    },
    url: '/notifications',
}));

vi.mock('@inertiajs/svelte', () => ({
    page: mockPage,
    router: { visit: vi.fn() },
    inertia: vi.fn(),
}));

// ==========================================
// 3. MOCK API NOTIFICATIONS
// ==========================================
vi.mock('@/api/notifications', () => ({
    markNotificationAsRead: vi.fn(),
    markAllNotificationsAsRead: vi.fn(),
}));

import { markNotificationAsRead, markAllNotificationsAsRead } from '@/api/notifications';
import { router } from '@inertiajs/svelte';

// ==========================================
// 4. MOCK DATA
// ==========================================
const mockNotification = (overrides = {}) => ({
    id: 'uuid-123',
    tipe: 'booking_confirmed',
    judul: 'Booking Terkonfirmasi',
    pesan: 'Paket Anda telah dikonfirmasi.',
    data: { url: '/client/bookings/123' },
    read_at: null,
    created_at: '2026-10-16T10:00:00Z',
    ...overrides,
});

const mockProps = (overrides = {}) => ({
    notifications: {
        data: [mockNotification()],
        current_page: 1,
        last_page: 2,
        from: 1,
        to: 10,
        total: 15,
        links: [],
    },
    unreadCount: 1,
    ...overrides,
});

describe('Notifications Index Page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    // ==========================================
    // 1. RENDERING DASAR
    // ==========================================
    describe('Rendering', () => {
        it('harus merender judul halaman dan jumlah belum dibaca', () => {
            render(NotificationsIndex, { props: mockProps() });
            
            // PERBAIKAN: Gunakan getAllByRole atau spesifik ke teks deskripsi
            // "Notifikasi" muncul dua kali (di header layout dan di PageHeader)
            const headings = screen.getAllByRole('heading', { name: /Notifikasi/i });
            expect(headings.length).toBeGreaterThanOrEqual(1);
            
            // Cek deskripsi unik
            expect(screen.getByText('1 belum dibaca')).toBeInTheDocument();
        });

        it('harus merender daftar notifikasi', () => {
            render(NotificationsIndex, { props: mockProps() });
            expect(screen.getByText('Booking Terkonfirmasi')).toBeInTheDocument();
        });

        it('harus merender EmptyState jika data kosong', () => {
            render(NotificationsIndex, {
                props: mockProps({ notifications: { ...mockProps().notifications, data: [] } }),
            });
            expect(screen.getByText('Belum ada notifikasi')).toBeInTheDocument();
        });
    });

    // ==========================================
    // 2. INTERAKSI MARK ALL
    // ==========================================
    describe('Mark All As Read', () => {
        it('harus memanggil API markAllNotificationsAsRead saat tombol diklik', async () => {
            render(NotificationsIndex, { props: mockProps({ unreadCount: 5 }) });
            
            const btn = screen.getByRole('button', { name: /Tandai Semua Dibaca/i });
            await fireEvent.click(btn);
            
            expect(markAllNotificationsAsRead).toHaveBeenCalledTimes(1);
        });

        it('TIDAK merender tombol "Tandai Semua" jika unreadCount = 0', () => {
            render(NotificationsIndex, { props: mockProps({ unreadCount: 0 }) });
            expect(screen.queryByRole('button', { name: /Tandai Semua Dibaca/i })).not.toBeInTheDocument();
        });
    });

    // ==========================================
    // 3. INTERAKSI KLIK ITEM
    // ==========================================
    describe('Notification Item Click', () => {
        it('harus memanggil API markNotificationAsRead jika item belum dibaca', async () => {
            render(NotificationsIndex, { props: mockProps() });
            
            const item = screen.getByText('Booking Terkonfirmasi').closest('button')!;
            await fireEvent.click(item);

            expect(markNotificationAsRead).toHaveBeenCalledWith(
                'uuid-123',
                expect.any(Object)
            );
        });

        it('harus langsung redirect (router.visit) jika item SUDAH dibaca', async () => {
            render(NotificationsIndex, {
                props: mockProps({
                    notifications: {
                        ...mockProps().notifications,
                        data: [mockNotification({ read_at: '2026-10-16T10:05:00Z' })],
                    },
                }),
            });

            const item = screen.getByText('Booking Terkonfirmasi').closest('button')!;
            await fireEvent.click(item);

            expect(markNotificationAsRead).not.toHaveBeenCalled();
            expect(router.visit).toHaveBeenCalledWith('/client/bookings/123');
        });
    });
});