import { render, screen } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import TripMitraCard from '../TripMitraCard.svelte';

// ==========================================
// MOCK INERTIA
// ==========================================
vi.mock('@inertiajs/svelte', () => ({
    inertia: vi.fn(),
}));

// ==========================================
// MOCK DATA
// ==========================================
const createMockMitra = (overrides = {}) => ({
    id: '1',
    name: 'Mitra Travel',
    email: 'mitra@test.com',
    username: 'mitratravel',
    ...overrides,
});

describe('TripMitraCard Component', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    // ==========================================
    // 1. RENDERING DENGAN MITRA
    // ==========================================
    describe('With Mitra', () => {
        it('harus merender nama dan email mitra', () => {
            render(TripMitraCard, { props: { mitra: createMockMitra() } });

            expect(screen.getByText('Mitra Travel')).toBeInTheDocument();
            expect(screen.getByText('mitra@test.com')).toBeInTheDocument();
        });

        it('harus merender tautan ke halaman detail mitra', () => {
            render(TripMitraCard, { props: { mitra: createMockMitra() } });

            const link = screen.getByLabelText(
                /Lihat detail mitra Mitra Travel/i,
            );
            expect(link).toBeInTheDocument();
            expect(link).toHaveAttribute('href', '/admin/users/1');
        });
    });

    // ==========================================
    // 2. RENDERING TANPA MITRA
    // ==========================================
    describe('Without Mitra', () => {
        it('harus menampilkan pesan "Data mitra tidak ditemukan" jika mitra null', () => {
            render(TripMitraCard, { props: { mitra: null } });

            expect(
                screen.getByText('Data mitra tidak ditemukan'),
            ).toBeInTheDocument();
        });

        it('TIDAK boleh merender tautan jika mitra null', () => {
            render(TripMitraCard, { props: { mitra: null } });

            expect(screen.queryByRole('link')).not.toBeInTheDocument();
        });
    });

    // ==========================================
    // 3. AKSESIBILITAS (A11Y)
    // ==========================================
    describe('Accessibility', () => {
        it('ikon SVG harus disembunyikan dari screen reader', () => {
            const { container } = render(TripMitraCard, {
                props: { mitra: createMockMitra() },
            });

            const svgs = container.querySelectorAll('svg');
            svgs.forEach((svg) => {
                expect(svg).toHaveAttribute('aria-hidden', 'true');
            });
        });

        it('pesan "Data mitra tidak ditemukan" harus memiliki role="status"', () => {
            render(TripMitraCard, { props: { mitra: null } });

            const status = screen.getByRole('status');
            expect(status).toHaveTextContent('Data mitra tidak ditemukan');
        });
    });
});
