import { render, screen } from '@testing-library/svelte';
import { describe, it, expect } from 'vitest';
import Criterion from '../Criterion.svelte';

describe('Criterion Component', () => {
    // ==========================================
    // 1. RENDERING & PROPS
    // ==========================================
    describe('Rendering Props', () => {
        it('harus merender label dengan benar', () => {
            render(Criterion, { props: { label: 'Min. 8 karakter', passed: true } });
            expect(screen.getByText('Min. 8 karakter')).toBeInTheDocument();
        });

        it('harus merender ikon centang (check) jika passed=true', () => {
            const { container } = render(Criterion, { props: { label: 'Lulus', passed: true } });
            const path = container.querySelector('path');
            // Path SVG untuk check
            expect(path).toHaveAttribute('d', 'M5 13l4 4L19 7');
        });

        it('harus merender ikon silang (x) jika passed=false', () => {
            const { container } = render(Criterion, { props: { label: 'Gagal', passed: false } });
            const path = container.querySelector('path');
            // Path SVG untuk X
            expect(path).toHaveAttribute('d', 'M6 18L18 6M6 6l12 12');
        });
    });

    // ==========================================
    // 2. STYLING (CLASS CSS)
    // ==========================================
    describe('Styling', () => {
        it('harus menerapkan warna hijau jika passed=true', () => {
            const { container } = render(Criterion, { props: { label: 'Lulus', passed: true } });
            
            const iconWrapper = container.querySelector('span');
            expect(iconWrapper).toHaveClass('bg-green-100');
            expect(iconWrapper).toHaveClass('text-green-600');
            
            const textLabel = screen.getByText('Lulus');
            expect(textLabel).toHaveClass('text-green-700');
            expect(textLabel).toHaveClass('font-bold');
        });

        it('harus menerapkan warna abu-abu jika passed=false', () => {
            const { container } = render(Criterion, { props: { label: 'Gagal', passed: false } });
            
            const iconWrapper = container.querySelector('span');
            expect(iconWrapper).toHaveClass('bg-gray-100');
            expect(iconWrapper).toHaveClass('text-gray-400');
            
            const textLabel = screen.getByText('Gagal');
            expect(textLabel).toHaveClass('text-gray-500');
        });
    });

    // ==========================================
    // 3. AKSESIBILITAS (A11Y)
    // ==========================================
    describe('Accessibility', () => {
        it('harus memiliki aria-label "Memenuhi syarat" jika passed=true', () => {
            render(Criterion, { props: { label: 'Lulus', passed: true } });
            const container = screen.getByLabelText('Memenuhi syarat: Lulus');
            expect(container).toBeInTheDocument();
        });

        it('harus memiliki aria-label "Belum memenuhi" jika passed=false', () => {
            render(Criterion, { props: { label: 'Gagal', passed: false } });
            const container = screen.getByLabelText('Belum memenuhi: Gagal');
            expect(container).toBeInTheDocument();
        });

        it('ikon SVG harus disembunyikan dari screen reader (aria-hidden)', () => {
            const { container } = render(Criterion, { props: { label: 'Test', passed: true } });
            const iconWrapper = container.querySelector('span');
            expect(iconWrapper).toHaveAttribute('aria-hidden', 'true');
        });
    });
});