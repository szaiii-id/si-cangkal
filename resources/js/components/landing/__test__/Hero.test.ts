import { render, screen } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import Hero from '../Hero.svelte';

// ==========================================
// MOCK INERTIA (page)
// ==========================================
const mockPage = vi.hoisted(() => ({
    props: { auth: { user: null as any } },
}));

vi.mock('@inertiajs/svelte', () => ({
    page: mockPage,
    inertia: vi.fn(),
}));

describe('Hero Component', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        mockPage.props.auth.user = null; // Reset ke guest
    });

    // ==========================================
    // 1. RENDERING UNTUK GUEST (Belum Login)
    // ==========================================
    describe('Guest View', () => {
        it('harus menampilkan judul dan deskripsi utama', () => {
            render(Hero);

            expect(screen.getByRole('heading', { level: 1 })).toHaveTextContent(
                /Sinergi Pariwisata Banua/i,
            );
            expect(
                screen.getByText(/Sistem Implementasi Capaian Akselerasi/i),
            ).toBeInTheDocument();
        });

        it('harus menampilkan tombol "Gabung Mitra Travel" dan "Masuk Sistem"', () => {
            render(Hero);

            const registerBtn = screen.getByRole('link', {
                name: /Gabung Mitra Travel/i,
            });
            expect(registerBtn).toBeInTheDocument();
            expect(registerBtn).toHaveAttribute('href', '/register/mitra');

            const loginBtn = screen.getByRole('link', {
                name: /Masuk Sistem/i,
            });
            expect(loginBtn).toBeInTheDocument();
            expect(loginBtn).toHaveAttribute('href', '/login');
        });

        it('TIDAK boleh menampilkan tombol "Kembali ke Dashboard"', () => {
            render(Hero);
            expect(
                screen.queryByText(/Kembali ke Dashboard/i),
            ).not.toBeInTheDocument();
        });
    });

    // ==========================================
    // 2. RENDERING UNTUK USER (Sudah Login)
    // ==========================================
    describe('Authenticated View', () => {
        it('harus menampilkan tombol "Kembali ke Dashboard" untuk Admin', () => {
            mockPage.props.auth.user = { role: 'admin' };
            render(Hero);

            const dashBtn = screen.getByRole('link', {
                name: /Kembali ke Dashboard/i,
            });
            expect(dashBtn).toBeInTheDocument();
            expect(dashBtn).toHaveAttribute('href', '/admin/dashboard');
        });

        it('harus menampilkan tombol "Kembali ke Dashboard" untuk Mitra', () => {
            mockPage.props.auth.user = { role: 'mitra' };
            render(Hero);

            const dashBtn = screen.getByRole('link', {
                name: /Kembali ke Dashboard/i,
            });
            expect(dashBtn).toHaveAttribute('href', '/mitra/dashboard');
        });

        it('harus menampilkan tombol "Kembali ke Dashboard" untuk Client', () => {
            mockPage.props.auth.user = { role: 'client' };
            render(Hero);

            const dashBtn = screen.getByRole('link', {
                name: /Kembali ke Dashboard/i,
            });
            expect(dashBtn).toHaveAttribute('href', '/client/dashboard');
        });

        it('TIDAK boleh menampilkan tombol "Gabung Mitra Travel" atau "Masuk Sistem"', () => {
            mockPage.props.auth.user = { role: 'client' };
            render(Hero);

            expect(
                screen.queryByText(/Gabung Mitra Travel/i),
            ).not.toBeInTheDocument();
            expect(screen.queryByText(/Masuk Sistem/i)).not.toBeInTheDocument();
        });
    });

    // ==========================================
    // 3. AKSESIBILITAS (A11Y)
    // ==========================================
    describe('Accessibility', () => {
        it('semua gambar harus memiliki alt text', () => {
            render(Hero);

            const images = screen.getAllByRole('img');
            expect(images.length).toBe(3);
            images.forEach((img) => {
                expect(img).toHaveAttribute('alt');
                expect(img.getAttribute('alt')).not.toBe('');
            });
        });

        it('gambar utama harus memiliki fetchpriority="high"', () => {
            render(Hero);

            // Gambar kedua (tengah) adalah yang utama
            const mainImage = screen.getByAltText(/Destinasi Wisata Utama/i);
            expect(mainImage).toHaveAttribute('fetchpriority', 'high');
        });

        it('semua gambar hero dimuat eager dengan dimensi eksplisit', () => {
            render(Hero);

            const images = screen.getAllByRole('img');

            images.forEach((img) => {
                expect(img).toHaveAttribute('loading', 'eager');
                expect(img).toHaveAttribute('fetchpriority', 'high');
                expect(img).toHaveAttribute('width');
                expect(img).toHaveAttribute('height');
            });
        });
    });
});
