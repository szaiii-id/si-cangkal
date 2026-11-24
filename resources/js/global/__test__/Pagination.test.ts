import { render, screen } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import Pagination from '../Pagination.svelte';
import type { PaginationMeta } from '@/types';

// ==========================================
// MOCK INERTIA
// ==========================================
vi.mock('@inertiajs/svelte', () => ({
    inertia: vi.fn(),
}));

// Helper dengan tipe eksplisit yang mengizinkan null
const createMeta = (
    lastPage: number,
    links: PaginationMeta['links'] = [],
    overrides: Partial<PaginationMeta> = {},
): PaginationMeta => ({
    current_page: 1,
    last_page: lastPage,
    from: 1,
    to: 10,
    total: 50,
    per_page: 10,
    links: [
        { url: null, label: '&laquo; Previous', active: false },
        ...links,
        {
            url: 'http://example.com/page/2',
            label: 'Next &raquo;',
            active: false,
        },
    ],
    ...overrides,
});

describe('Pagination Component', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    // ==========================================
    // 1. VISIBILITY LOGIC
    // ==========================================
    describe('Visibility', () => {
        it('TIDAK boleh merender apa pun jika last_page = 1 dan showWhenEmpty = false', () => {
            render(Pagination, { props: { meta: createMeta(1) } });
            expect(screen.queryByRole('navigation')).not.toBeInTheDocument();
        });

        it('harus merender jika last_page > 1', () => {
            render(Pagination, { props: { meta: createMeta(3) } });
            expect(screen.getByRole('navigation')).toBeInTheDocument();
        });

        it('harus merender meskipun last_page = 1 jika showWhenEmpty = true', () => {
            render(Pagination, {
                props: { meta: createMeta(1), showWhenEmpty: true },
            });
            expect(screen.getByRole('navigation')).toBeInTheDocument();
        });
    });

    // ==========================================
    // 2. RENDERING INFO
    // ==========================================
    describe('Rendering Info', () => {
        it('harus merender info "Menampilkan X-Y dari Z"', () => {
            render(Pagination, {
                props: { meta: createMeta(3), label: 'booking' },
            });

            const info = screen.getByText(/Menampilkan/i);
            expect(info).toHaveTextContent(/1–\s*10/);
            expect(info).toHaveTextContent(/dari/);
            expect(info).toHaveTextContent(/50/);
            expect(info).toHaveTextContent(/booking/);
        });

        it('harus menampilkan angka 0 jika from/to null', () => {
            // PERBAIKAN: Menggunakan overrides agar TypeScript tidak komplain
            render(Pagination, {
                props: {
                    meta: createMeta(3, [], { from: null, to: null }),
                },
            });

            const info = screen.getByText(/Menampilkan/i);
            expect(info).toHaveTextContent(/0–\s*0/);
        });
    });

    // ==========================================
    // 3. RENDERING LINKS
    // ==========================================
    describe('Links', () => {
        it('harus merender link aktif dengan warna pink dan aria-current="page"', () => {
            const meta = createMeta(3, [
                { url: 'http://example.com/page/1', label: '1', active: true },
            ]);

            render(Pagination, { props: { meta } });

            const activeLink = screen.getByRole('link', { name: 'Halaman 1' });
            expect(activeLink).toHaveClass('bg-pink-dark');
            expect(activeLink).toHaveAttribute('aria-current', 'page');
        });

        it('harus merender link non-aktif tanpa aria-current', () => {
            const meta = createMeta(3, [
                { url: 'http://example.com/page/2', label: '2', active: false },
            ]);

            render(Pagination, { props: { meta } });

            const link = screen.getByRole('link', { name: 'Halaman 2' });
            expect(link).not.toHaveClass('bg-pink-dark');
            expect(link).not.toHaveAttribute('aria-current');
        });

        it('harus merender tombol disabled (span) jika link.url = null', () => {
            const meta = createMeta(3);
            render(Pagination, { props: { meta } });

            const prevLink = screen.getByLabelText('Halaman sebelumnya');
            expect(prevLink.tagName).toBe('SPAN');
            expect(prevLink).toHaveAttribute('aria-disabled', 'true');
        });

        it('harus memiliki aria-label yang sesuai untuk aksesibilitas', () => {
            const meta = createMeta(3, [
                { url: 'http://example.com/page/1', label: '1', active: false },
            ]);

            render(Pagination, { props: { meta } });

            expect(screen.getByLabelText('Halaman 1')).toBeInTheDocument();
            expect(
                screen.getByLabelText('Halaman berikutnya'),
            ).toBeInTheDocument();
            expect(
                screen.getByLabelText('Halaman sebelumnya'),
            ).toBeInTheDocument();
        });
    });

    // ==========================================
    // 4. INTERAKSI KLIK
    // ==========================================
    describe('Interaction', () => {
        it('harus memiliki atribut href yang benar pada link', () => {
            const meta = createMeta(3, [
                { url: 'http://example.com/page/2', label: '2', active: false },
            ]);

            render(Pagination, { props: { meta } });

            const link = screen.getByRole('link', { name: 'Halaman 2' });
            expect(link).toHaveAttribute('href', 'http://example.com/page/2');
        });
    });
});
