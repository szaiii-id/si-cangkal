import { render, screen } from '@testing-library/svelte';
import { describe, it, expect, vi } from 'vitest';
import NotificationDropdown from '../NotificationDropdown.svelte';

// ==========================================
// MOCK INERTIA (page & inertia)
// ==========================================
// Kita gunakan vi.hoisted agar bisa mengubah nilai page.props di dalam test
const mockPage = vi.hoisted(() => ({
    props: { unread_notifications: 0 },
}));

vi.mock('@inertiajs/svelte', () => ({
    page: mockPage,
    inertia: vi.fn(),
}));

describe('NotificationDropdown Component', () => {
    // ==========================================
    // 1. RENDERING DASAR
    // ==========================================
    describe('Rendering', () => {
        it('harus merender tautan menuju /notifications', () => {
            mockPage.props.unread_notifications = 0;
            render(NotificationDropdown);

            const link = screen.getByRole('link');
            expect(link).toHaveAttribute('href', '/notifications');
        });

        it('harus merender ikon SVG yang disembunyikan dari screen reader', () => {
            const { container } = render(NotificationDropdown);
            const svg = container.querySelector('svg');
            expect(svg).toHaveAttribute('aria-hidden', 'true');
        });
    });

    // ==========================================
    // 2. KONDISI UNREAD (Badge)
    // ==========================================
    describe('Unread Badge', () => {
        it('TIDAK boleh menampilkan badge jika unread = 0', () => {
            mockPage.props.unread_notifications = 0;
            const { container } = render(NotificationDropdown);

            const badge = container.querySelector('.bg-red-500');
            expect(badge).not.toBeInTheDocument();
        });

        it('harus menampilkan badge angka jika unread > 0', () => {
            mockPage.props.unread_notifications = 5;
            render(NotificationDropdown);

            expect(screen.getByText('5')).toBeInTheDocument();
        });

        it('harus menampilkan "99+" jika unread > 99', () => {
            mockPage.props.unread_notifications = 150;
            render(NotificationDropdown);

            expect(screen.getByText('99+')).toBeInTheDocument();
        });

        it('harus menampilkan "99+" jika unread = 100 (batas)', () => {
            mockPage.props.unread_notifications = 100;
            render(NotificationDropdown);

            expect(screen.getByText('99+')).toBeInTheDocument();
        });

        it('harus menampilkan "99" jika unread = 99 (belum melebihi batas)', () => {
            mockPage.props.unread_notifications = 99;
            render(NotificationDropdown);

            expect(screen.getByText('99')).toBeInTheDocument();
        });
    });

    // ==========================================
    // 3. AKSESIBILITAS (A11Y)
    // ==========================================
    describe('Accessibility', () => {
        it('harus memiliki aria-label "Notifikasi" jika tidak ada unread', () => {
            mockPage.props.unread_notifications = 0;
            render(NotificationDropdown);

            expect(screen.getByLabelText('Notifikasi')).toBeInTheDocument();
        });

        it('harus memiliki aria-label yang menyertakan jumlah unread', () => {
            mockPage.props.unread_notifications = 3;
            render(NotificationDropdown);

            expect(
                screen.getByLabelText('Notifikasi, 3 belum dibaca'),
            ).toBeInTheDocument();
        });
    });
});
