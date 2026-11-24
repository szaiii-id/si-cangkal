import { render, screen } from '@testing-library/svelte';
import { describe, it, expect } from 'vitest';
import ExportButtons from '../ExportButtons.svelte';

describe('ExportButtons Component', () => {
    const defaultProps = {
        pdfUrl: '/admin/reports/export-pdf',
        excelUrl: '/admin/reports/export-excel',
    };

    // ==========================================
    // 1. RENDERING DASAR
    // ==========================================
    describe('Rendering', () => {
        it('harus merender dua tombol export (PDF dan Excel)', () => {
            render(ExportButtons, { props: defaultProps });

            expect(
                screen.getByRole('link', { name: /Export PDF/i }),
            ).toBeInTheDocument();
            expect(
                screen.getByRole('link', { name: /Export Excel/i }),
            ).toBeInTheDocument();
        });

        it('harus merender ikon SVG di dalam tombol', () => {
            const { container } = render(ExportButtons, {
                props: defaultProps,
            });

            const svgs = container.querySelectorAll('svg');
            expect(svgs).toHaveLength(2);
        });
    });

    // ==========================================
    // 2. ATRIBUT LINK & KEAMANAN
    // ==========================================
    describe('Link Attributes', () => {
        it('tombol PDF harus memiliki href yang benar, target="_blank", dan rel="noopener noreferrer"', () => {
            render(ExportButtons, { props: defaultProps });

            const pdfLink = screen.getByRole('link', { name: /Export PDF/i });
            expect(pdfLink).toHaveAttribute(
                'href',
                '/admin/reports/export-pdf',
            );
            expect(pdfLink).toHaveAttribute('target', '_blank');
            expect(pdfLink).toHaveAttribute('rel', 'noopener noreferrer');
        });

        it('tombol Excel harus memiliki href yang benar dan atribut download', () => {
            render(ExportButtons, { props: defaultProps });

            const excelLink = screen.getByRole('link', {
                name: /Export Excel/i,
            });
            expect(excelLink).toHaveAttribute(
                'href',
                '/admin/reports/export-excel',
            );
            expect(excelLink).toHaveAttribute('download');
        });
    });

    // ==========================================
    // 3. AKSESIBILITAS (A11Y)
    // ==========================================
    describe('Accessibility', () => {
        it('ikon SVG harus disembunyikan dari screen reader', () => {
            const { container } = render(ExportButtons, {
                props: defaultProps,
            });

            const svgs = container.querySelectorAll('svg');
            svgs.forEach((svg) => {
                expect(svg).toHaveAttribute('aria-hidden', 'true');
            });
        });
    });
});
