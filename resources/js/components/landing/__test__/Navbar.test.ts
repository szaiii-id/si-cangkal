import { render, screen, fireEvent, waitFor } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import Navbar from '../Navbar.svelte';

// ==========================================
// 1. MOCK TRANSISI SVELTE (agar langsung hilang)
// ==========================================
vi.mock('svelte/transition', () => ({
    fade: vi.fn(() => ({ duration: 0 })),
    fly: vi.fn(() => ({ duration: 0 })),
}));

// ==========================================
// 2. MOCK INTERSECTION OBSERVER
// ==========================================
class MockIntersectionObserver {
    observe = vi.fn();
    disconnect = vi.fn();
    unobserve = vi.fn();
}
vi.stubGlobal('IntersectionObserver', MockIntersectionObserver);

// ==========================================
// 3. MOCK ELEMENT.ANIMATE
// ==========================================
beforeEach(() => {
    // @ts-ignore
    window.HTMLElement.prototype.animate = vi.fn().mockImplementation(() => {
        const animation: any = {
            onfinish: null,
            cancel: vi.fn(),
            finish: vi.fn(),
        };
        setTimeout(() => {
            if (animation.onfinish) animation.onfinish();
        }, 0);
        return animation;
    });
});

// ==========================================
// 4. MOCK INERTIA
// ==========================================
const mockPage = vi.hoisted(() => ({
    props: {
        auth: {
            user: null as any,
        },
    },
}));

vi.mock('@inertiajs/svelte', () => ({
    page: mockPage,
    inertia: vi.fn(),
}));

describe('Navbar Component', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        mockPage.props.auth.user = null;
    });

    // ==========================================
    // 1. RENDERING UNTUK GUEST
    // ==========================================
    describe('Guest View', () => {
        it('harus merender tombol Login dan Daftar', () => {
            render(Navbar);
            expect(screen.getAllByText('Login').length).toBeGreaterThan(0);
            expect(screen.getAllByText('Daftar').length).toBeGreaterThan(0);
        });

        it('TIDAK boleh merender tombol Dashboard', () => {
            render(Navbar);
            expect(screen.queryByText('Dashboard')).not.toBeInTheDocument();
        });
    });

    // ==========================================
    // 2. RENDERING UNTUK USER
    // ==========================================
    describe('Authenticated View', () => {
        it('harus merender tombol Dashboard untuk Admin', () => {
            mockPage.props.auth.user = { role: 'admin' };
            render(Navbar);
            const dashboardLink = screen.getByRole('link', { name: 'Dashboard' });
            expect(dashboardLink).toHaveAttribute('href', '/admin/dashboard');
        });

        it('harus merender tombol Dashboard untuk Mitra', () => {
            mockPage.props.auth.user = { role: 'mitra' };
            render(Navbar);
            const dashboardLink = screen.getByRole('link', { name: 'Dashboard' });
            expect(dashboardLink).toHaveAttribute('href', '/mitra/dashboard');
        });

        it('harus merender tombol Dashboard untuk Client', () => {
            mockPage.props.auth.user = { role: 'client' };
            render(Navbar);
            const dashboardLink = screen.getByRole('link', { name: 'Dashboard' });
            expect(dashboardLink).toHaveAttribute('href', '/client/dashboard');
        });

        it('TIDAK boleh merender tombol Login/Daftar', () => {
            mockPage.props.auth.user = { role: 'admin' };
            render(Navbar);
            expect(screen.queryByText('Login')).not.toBeInTheDocument();
            expect(screen.queryByText('Daftar')).not.toBeInTheDocument();
        });
    });

    // ==========================================
    // 3. INTERAKSI MOBILE MENU
    // ==========================================
    describe('Mobile Menu Interaction', () => {
        it('harus membuka menu mobile saat tombol hamburger diklik', async () => {
            render(Navbar);
            const menuBtn = screen.getByLabelText('Buka menu navigasi');
            await fireEvent.click(menuBtn);

            const berandaLinks = screen.getAllByText('Beranda');
            expect(berandaLinks.length).toBe(2);
        });

        it('harus menutup menu mobile saat backdrop diklik', async () => {
            render(Navbar);

            // Buka menu
            const menuBtn = screen.getByLabelText('Buka menu navigasi');
            await fireEvent.click(menuBtn);

            const backdrop = document.querySelector('[role="presentation"]');
            expect(backdrop).toBeInTheDocument();

            // Klik backdrop
            await fireEvent.click(backdrop!);

            // Tunggu hingga menu benar-benar hilang dari DOM
            await waitFor(() => {
                expect(screen.queryAllByText('Beranda')).toHaveLength(1);
            });
        });
    });

    // ==========================================
    // 4. AKSESIBILITAS
    // ==========================================
    describe('Accessibility', () => {
        it('ikon SVG harus disembunyikan dari screen reader', () => {
            const { container } = render(Navbar);
            const svgs = container.querySelectorAll('svg');
            svgs.forEach((svg) => {
                expect(svg).toHaveAttribute('aria-hidden', 'true');
            });
        });

        it('tombol hamburger harus memiliki aria-expanded', () => {
            render(Navbar);
            const menuBtn = screen.getByLabelText('Buka menu navigasi');
            expect(menuBtn).toHaveAttribute('aria-expanded', 'false');
        });
    });
});