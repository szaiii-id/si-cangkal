import { render, screen, fireEvent } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { createRawSnippet } from 'svelte';
import DashboardLayout from '../DashboardLayout.svelte';

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

// ==========================================
// MOCK PAGE (Dengan Tipe Eksplisit)
// ==========================================
const mockPage = vi.hoisted(() => ({
    props: {
        auth: {
            user: {
                id: 1,
                name: 'Budi',
                username: 'budi',
                email: 'budi@test.com',
                role: 'admin',
                has_fcm_token: false,
            },
        },
        flash: {
            status: null as string | null,
            error: null as string | null,
        },
        unread_notifications: 5,
    },
    url: '/admin/dashboard',
}));

vi.mock('@inertiajs/svelte', () => ({
    page: mockPage,
    router: { visit: vi.fn() },
    inertia: vi.fn(),
}));

import { logout } from '@/api/auth';

// Helper untuk membuat children snippet
const mockChildren = createRawSnippet(() => ({
    render: () => '<div data-testid="mock-children">Konten Halaman</div>',
}));

describe('DashboardLayout Component', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        mockPage.props.flash = { status: null, error: null };
        mockPage.props.unread_notifications = 5;
        mockPage.url = '/admin/dashboard';
    });

    afterEach(() => {
        vi.restoreAllMocks();
    });

    // ==========================================
    // 1. RENDERING DASAR
    // ==========================================
    describe('Rendering', () => {
        it('harus merender judul halaman (title)', () => {
            render(DashboardLayout, {
                props: { title: 'Dashboard Admin', menuItems: [], children: mockChildren },
            });
            expect(screen.getByRole('heading', { name: 'Dashboard Admin' })).toBeInTheDocument();
        });

        it('harus merender children (konten utama)', () => {
            render(DashboardLayout, {
                props: { title: 'Test', menuItems: [], children: mockChildren },
            });
            expect(screen.getByTestId('mock-children')).toBeInTheDocument();
            expect(screen.getByText('Konten Halaman')).toBeInTheDocument();
        });

        it('harus merender informasi user di header dan sidebar', () => {
            render(DashboardLayout, {
                props: { title: 'Test', menuItems: [], children: mockChildren },
            });
            expect(screen.getAllByText(/Budi/).length).toBeGreaterThan(0);
            expect(screen.getByText('@budi')).toBeInTheDocument();
            expect(screen.getAllByText('budi@test.com').length).toBeGreaterThan(0);
        });

        it('harus merender menu items di sidebar dan menandai menu aktif', () => {
            const menuItems = [
                { label: 'Dashboard', href: '/admin/dashboard' },
                { label: 'Pengguna', href: '/admin/users' },
            ];
            render(DashboardLayout, {
                props: { title: 'Test', menuItems, children: mockChildren },
            });

            const activeLink = screen.getByRole('link', { name: 'Dashboard' });
            const inactiveLink = screen.getByRole('link', { name: 'Pengguna' });

            expect(activeLink).toHaveAttribute('aria-current', 'page');
            expect(inactiveLink).not.toHaveAttribute('aria-current');
        });
    });

    // ==========================================
    // 2. FLASH MESSAGES
    // ==========================================
    describe('Flash Messages', () => {
        it('harus menampilkan flash status (sukses) jika ada', () => {
            mockPage.props.flash = { status: 'Data berhasil disimpan', error: null };
            render(DashboardLayout, {
                props: { title: 'Test', menuItems: [], children: mockChildren },
            });

            expect(screen.getByRole('status')).toHaveTextContent('Data berhasil disimpan');
        });

        it('harus menampilkan flash error jika ada', () => {
            mockPage.props.flash = { status: null, error: 'Terjadi kesalahan' };
            render(DashboardLayout, {
                props: { title: 'Test', menuItems: [], children: mockChildren },
            });

            expect(screen.getByRole('alert')).toHaveTextContent('Terjadi kesalahan');
        });
    });

    // ==========================================
    // 3. INTERAKSI LOGOUT
    // ==========================================
    describe('Logout', () => {
        it('harus memanggil API logout saat tombol Keluar diklik', async () => {
            render(DashboardLayout, {
                props: { title: 'Test', menuItems: [], children: mockChildren },
            });

            const logoutBtn = screen.getByRole('button', { name: /Keluar dari akun/i });
            await fireEvent.click(logoutBtn);

            expect(logout).toHaveBeenCalledTimes(1);
        });
    });

    // ==========================================
    // 4. INTERAKSI SIDEBAR (Toggle)
    // ==========================================
    describe('Sidebar Toggle', () => {
        it('harus memiliki tombol "Buka menu" di mobile', () => {
            render(DashboardLayout, {
                props: { title: 'Test', menuItems: [], children: mockChildren },
            });
            const openBtn = screen.getByRole('button', { name: /Buka menu/i });
            expect(openBtn).toBeInTheDocument();
            expect(openBtn).toHaveAttribute('aria-expanded', 'false');
        });

        it('harus membuka dan menutup sidebar saat tombol toggle / backdrop diklik', async () => {
            render(DashboardLayout, {
                props: { title: 'Test', menuItems: [], children: mockChildren },
            });

            const openBtn = screen.getByRole('button', { name: /Buka menu/i });
            await fireEvent.click(openBtn);

            expect(openBtn).toHaveAttribute('aria-expanded', 'true');
            const backdropBtn = screen.getByRole('button', { name: /Tutup sidebar/i });
            expect(backdropBtn).toBeInTheDocument();

            await fireEvent.click(backdropBtn);

            expect(openBtn).toHaveAttribute('aria-expanded', 'false');
            expect(screen.queryByRole('button', { name: /Tutup sidebar/i })).not.toBeInTheDocument();
        });
    });

    // ==========================================
    // 5. NOTIFIKASI BADGE
    // ==========================================
    describe('Notification Badge', () => {
        it('harus menampilkan badge unread jika unread_notifications > 0', () => {
            mockPage.props.unread_notifications = 5;
            render(DashboardLayout, {
                props: { title: 'Test', menuItems: [], children: mockChildren },
            });

            const notifLink = screen.getByRole('link', {
                name: /Notifikasi 5 belum dibaca/i,
            });
            expect(notifLink).toBeInTheDocument();
            expect(notifLink).toHaveTextContent('5');
        });

        it('TIDAK menampilkan badge jika unread_notifications = 0', () => {
            mockPage.props.unread_notifications = 0;
            render(DashboardLayout, {
                props: { title: 'Test', menuItems: [], children: mockChildren },
            });

            const notifLink = screen.getByRole('link', { name: 'Notifikasi' });
            expect(notifLink).toBeInTheDocument();
            expect(notifLink.querySelector('.bg-red-600')).not.toBeInTheDocument();
            expect(notifLink).not.toHaveTextContent(/belum dibaca/i);
        });
    });
});