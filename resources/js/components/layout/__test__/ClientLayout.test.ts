import { render, screen, fireEvent } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { createRawSnippet } from 'svelte';
import ClientLayout from '../ClientLayout.svelte';

// ==========================================
// MOCK DEPENDENCIES (Svelte 5 Component Mocks)
// ==========================================
vi.mock('@/components/ui/ToastContainer.svelte', () => ({
    default: vi.fn(),
}));

vi.mock('@/components/NotificationPermissionBanner.svelte', () => ({
    default: vi.fn(),
}));

vi.mock('@/lib/firebase', () => ({
    isFcmSupported: vi.fn().mockReturnValue(false),
}));

vi.mock('@/api/auth', () => ({
    logout: vi.fn(),
}));

vi.mock('@/utils/menus', () => ({
    clientMenu: [
        {
            label: 'Beranda',
            href: '/client/dashboard',
            icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
        },
        {
            label: 'Katalog',
            href: '/client/catalog',
            icon: 'M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z',
        },
        {
            label: 'Pesanan',
            href: '/client/bookings',
            icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2',
        },
        {
            label: 'Profil',
            href: '/client/profile',
            icon: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
        },
    ],
}));

const mockPage = vi.hoisted(() => ({
    props: {
        auth: {
            user: {
                id: 1,
                name: 'Client',
                role: 'client',
                has_fcm_token: false,
            },
        },
        unread_notifications: 3,
    },
    url: '/client/dashboard',
}));

vi.mock('@inertiajs/svelte', () => ({
    page: mockPage,
    inertia: vi.fn(),
}));

import { logout } from '@/api/auth';

describe('ClientLayout Component', () => {
    const mockChildren = createRawSnippet(() => ({
        render: () => '<div data-testid="mock-children">Konten</div>',
    }));

    beforeEach(() => {
        vi.clearAllMocks();
        mockPage.url = '/client/dashboard';
        mockPage.props.unread_notifications = 3;
    });

    // ==========================================
    // 1. RENDERING DASAR
    // ==========================================
    describe('Rendering', () => {
        it('harus merender children', () => {
            render(ClientLayout, { props: { children: mockChildren } });
            expect(screen.getByTestId('mock-children')).toBeInTheDocument();
        });

        it('harus merender menu navigasi bawah', () => {
            render(ClientLayout, { props: { children: mockChildren } });

            expect(screen.getByLabelText('Beranda')).toBeInTheDocument();
            expect(screen.getByLabelText('Katalog')).toBeInTheDocument();
            expect(screen.getByLabelText('Pesanan')).toBeInTheDocument();
            expect(screen.getByLabelText('Profil')).toBeInTheDocument();
        });

        it('menyembunyikan header saat hideNavbar = true', () => {
            render(ClientLayout, {
                props: { children: mockChildren, hideNavbar: true },
            });

            expect(screen.queryByTitle('Keluar')).not.toBeInTheDocument();
            expect(screen.getByTestId('mock-children')).toBeInTheDocument();
        });
    });

    // ==========================================
    // 2. ACTIVE STATE & WARNA
    // ==========================================
    describe('Active State & Colors', () => {
        it('harus memberikan aria-current="page" pada menu aktif', () => {
            render(ClientLayout, { props: { children: mockChildren } });

            const activeLink = screen.getByLabelText('Beranda');
            expect(activeLink).toHaveAttribute('aria-current', 'page');
        });

        it('TIDAK memberikan aria-current pada menu non-aktif', () => {
            render(ClientLayout, { props: { children: mockChildren } });

            const inactiveLink = screen.getByLabelText('Katalog');
            expect(inactiveLink).not.toHaveAttribute('aria-current');
        });
    });

    // ==========================================
    // 3. INTERAKSI LOGOUT
    // ==========================================
    describe('Logout', () => {
        it('harus memanggil API logout saat tombol keluar diklik', async () => {
            render(ClientLayout, { props: { children: mockChildren } });

            const logoutBtn = screen.getByTitle('Keluar');
            await fireEvent.click(logoutBtn);

            expect(logout).toHaveBeenCalledTimes(1);
        });
    });

    // ==========================================
    // 4. NOTIFIKASI BADGE
    // ==========================================
    describe('Notification Badge', () => {
        it('harus menampilkan badge unread dengan accessible name yang sesuai', () => {
            mockPage.props.unread_notifications = 3;
            render(ClientLayout, { props: { children: mockChildren } });

            const notificationLink = screen.getByRole('link', {
                name: /Notifikasi 3 belum dibaca/i,
            });
            expect(notificationLink).toBeInTheDocument();
            expect(notificationLink).toHaveAttribute(
                'href',
                '/client/notifications',
            );
            expect(notificationLink).toHaveTextContent('3');
        });

        it('tidak menampilkan badge angka jika unread_notifications = 0', () => {
            mockPage.props.unread_notifications = 0;
            render(ClientLayout, { props: { children: mockChildren } });

            const notificationLink = screen.getByRole('link', {
                name: 'Notifikasi',
            });
            expect(notificationLink).toBeInTheDocument();
            expect(notificationLink).not.toHaveTextContent('3');
            expect(notificationLink).not.toHaveTextContent(/belum dibaca/i);
        });
    });
});
