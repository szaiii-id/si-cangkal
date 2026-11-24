import { render, screen } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import StatCard from '../StatCard.svelte';

// ==========================================
// MOCK INERTIA
// ==========================================
vi.mock('@inertiajs/svelte', () => ({
    inertia: vi.fn(),
}));

describe('StatCard Component', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    // ==========================================
    // 1. RENDERING DASAR
    // ==========================================
    describe('Rendering', () => {
        it('harus merender label dan value dengan benar', () => {
            render(StatCard, { props: { label: 'Total Booking', value: 150 } });

            expect(screen.getByText('Total Booking')).toBeInTheDocument();
            expect(screen.getByText('150')).toBeInTheDocument();
        });

        it('harus menerapkan warna default (pink) jika color tidak diberikan', () => {
            const { container } = render(StatCard, {
                props: { label: 'Test', value: 10 },
            });

            const card = container.firstElementChild;
            expect(card).toHaveClass('border-pink/20');
        });

        it('harus menerapkan warna kustom jika color diberikan', () => {
            const { container } = render(StatCard, {
                props: { label: 'Test', value: 10, color: 'green' },
            });

            const card = container.firstElementChild;
            expect(card).toHaveClass('border-green/20');
        });
    });

    // ==========================================
    // 2. MODE STATIS vs LINK
    // ==========================================
    describe('Static vs Link Mode', () => {
        it('harus merender sebagai <div> (statis) jika href tidak diberikan', () => {
            const { container } = render(StatCard, {
                props: { label: 'Test', value: 10 },
            });

            const card = container.firstElementChild;
            expect(card?.tagName).toBe('DIV');
            expect(screen.queryByRole('link')).not.toBeInTheDocument();
        });

        it('harus merender sebagai <a> (tautan) jika href diberikan', () => {
            render(StatCard, {
                props: { label: 'Test', value: 10, href: '/admin/bookings' },
            });

            const link = screen.getByRole('link');
            expect(link).toBeInTheDocument();
            expect(link.tagName).toBe('A');
            expect(link).toHaveAttribute('href', '/admin/bookings');
        });

        it('harus menerapkan class "group hover:shadow-md" hanya pada mode tautan', async () => {
            const { container, rerender } = render(StatCard, {
                props: { label: 'Test', value: 10 },
            });

            let card = container.firstElementChild;
            expect(card).not.toHaveClass('group');
            expect(card).not.toHaveClass('hover:shadow-md');

            // Ubah ke mode tautan
            await rerender({ label: 'Test', value: 10, href: '/test' });
            card = container.firstElementChild;
            expect(card).toHaveClass('group');
            expect(card).toHaveClass('hover:shadow-md');
        });
    });

    // ==========================================
    // 3. AKSESIBILITAS (A11Y)
    // ==========================================
    describe('Accessibility', () => {
        it('ikon SVG harus disembunyikan dari screen reader (aria-hidden)', () => {
            const { container } = render(StatCard, {
                props: { label: 'Test', value: 10 },
            });
            const svg = container.querySelector('svg');
            expect(svg).toHaveAttribute('aria-hidden', 'true');
        });
    });
});
