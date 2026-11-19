import { render, screen } from '@testing-library/svelte';
import { describe, it, expect } from 'vitest';
import MitraSummary from '../MitraSummary.svelte';

describe('MitraSummary Component', () => {
    // ==========================================
    // 1. RENDERING DASAR
    // ==========================================
    describe('Rendering', () => {
        it('harus merender label "Total Mitra"', () => {
            render(MitraSummary, { props: { totalMitra: 10 } });
            expect(screen.getByText(/Total Mitra/i)).toBeInTheDocument();
        });

        it('harus merender nilai totalMitra', () => {
            render(MitraSummary, { props: { totalMitra: 10 } });
            expect(screen.getByText('10')).toBeInTheDocument();
        });

        it('harus merender nilai 0 jika totalMitra=0', () => {
            render(MitraSummary, { props: { totalMitra: 0 } });
            expect(screen.getByText('0')).toBeInTheDocument();
        });
    });

    // ==========================================
    // 2. AKSESIBILITAS (A11Y)
    // ==========================================
    describe('Accessibility', () => {
        it('ikon SVG dekoratif harus disembunyikan dari screen reader', () => {
            const { container } = render(MitraSummary, { props: { totalMitra: 10 } });
            
            // Cek apakah semua SVG berada di dalam elemen dengan aria-hidden
            const svgs = container.querySelectorAll('svg');
            svgs.forEach((svg) => {
                // Jika SVG tidak memiliki aria-hidden, pastikan parent-nya punya
                const hasAriaHidden = svg.hasAttribute('aria-hidden') || 
                                      svg.closest('[aria-hidden="true"]') !== null;
                expect(hasAriaHidden).toBe(true);
            });
        });
    });
});