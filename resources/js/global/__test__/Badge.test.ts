import { render, screen } from '@testing-library/svelte';
import { describe, it, expect } from 'vitest';
import Badge from '../Badge.svelte';

describe('Badge Component', () => {
    // ==========================================
    // 1. STANDALONE RENDERING
    // ==========================================
    describe('Standalone Props', () => {
        it('harus merender label teks biasa', () => {
            render(Badge, { props: { label: 'Custom Label', class: 'bg-red-100' } });
            expect(screen.getByText('Custom Label')).toBeInTheDocument();
        });

        it('harus menerapkan class eksternal dan class internal', () => {
            const { container } = render(Badge, {
                props: { label: 'Test', class: 'custom-class bg-blue-500' },
            });

            const span = container.querySelector('span');
            expect(span).toHaveClass('custom-class');
            expect(span).toHaveClass('bg-blue-500');
            // Pastikan class bawaan juga ada
            expect(span).toHaveClass('inline-flex');
            expect(span).toHaveClass('rounded-full');
        });
    });

    // ==========================================
    // 2. SHORTCUT VARIANT RESOLUTION
    // ==========================================
    describe('Variant Shortcut', () => {
        it('harus me-resolve label & class dari variant "booking_status"', () => {
            const { container } = render(Badge, {
                props: { variant: 'booking_status', value: 'confirmed' },
            });

            // Memastikan teks label ter-resolve dengan benar
            expect(screen.getByText('Terkonfirmasi')).toBeInTheDocument();
            
            // Memastikan class warna dari helper masuk
            const span = container.querySelector('span');
            expect(span).toHaveClass('bg-green/10');
            expect(span).toHaveClass('text-green-text');
        });

        it('harus me-resolve variant "package_kategori" dengan benar', () => {
            render(Badge, {
                props: { variant: 'package_kategori', value: 'budaya' },
            });
            expect(screen.getByText('Budaya')).toBeInTheDocument();
        });

        it('harus kembali ke label default jika variant tidak dikenal (Edge Case)', () => {
            render(Badge, {
                props: { variant: 'booking_status' as any, value: 'UNKNOWN_STATUS' },
            });
            // Karena helper tidak menemukan label, ia mengembalikan string asli
            expect(screen.getByText('UNKNOWN_STATUS')).toBeInTheDocument();
        });
    });

    // ==========================================
    // 3. ICONS
    // ==========================================
    describe('Icons', () => {
        it('harus merender ikon jika props icon diberikan', () => {
            const { container } = render(Badge, {
                props: { label: 'Sukses', icon: 'check' },
            });
            const svg = container.querySelector('svg');
            expect(svg).toBeInTheDocument();
            expect(svg).toHaveAttribute('aria-hidden', 'true'); // Memastikan a11y
        });

        it('TIDAK boleh merender ikon jika props icon null (default)', () => {
            const { container } = render(Badge, {
                props: { label: 'Tanpa Ikon' },
            });
            const svg = container.querySelector('svg');
            expect(svg).not.toBeInTheDocument();
        });
    });

    // ==========================================
    // 4. SIZE
    // ==========================================
    describe('Sizes', () => {
        it('harus menerapkan class ukuran "sm"', () => {
            const { container } = render(Badge, {
                props: { label: 'Kecil', size: 'sm' },
            });
            expect(container.querySelector('span')).toHaveClass('px-2', 'py-0.5', 'text-[10px]');
        });

        it('harus menerapkan class ukuran "md" secara default', () => {
            const { container } = render(Badge, {
                props: { label: 'Sedang' },
            });
            expect(container.querySelector('span')).toHaveClass('px-3', 'py-1.5', 'text-[11px]');
        });
    });
});