import { render, screen } from '@testing-library/svelte';
import { describe, it, expect, vi } from 'vitest';
import ReportTabs from '../ReportTabs.svelte';

// ==========================================
// MOCK INERTIA (untuk use:inertia)
// ==========================================
vi.mock('@inertiajs/svelte', () => ({
    inertia: vi.fn(),
}));

describe('ReportTabs Component', () => {
    // ==========================================
    // 1. RENDERING DASAR
    // ==========================================
    describe('Rendering', () => {
        it('harus merender 2 tab navigasi', () => {
            render(ReportTabs, { props: { active: 'pendapatan' } });
            
            expect(screen.getByText('Pendapatan')).toBeInTheDocument();
            expect(screen.getByText('Kinerja Mitra')).toBeInTheDocument();
        });

        it('harus merender tautan dengan href yang benar', () => {
            render(ReportTabs, { props: { active: 'pendapatan' } });
            
            const pendapatanLink = screen.getByRole('link', { name: 'Pendapatan' });
            expect(pendapatanLink).toHaveAttribute('href', '/admin/reports');

            const mitraLink = screen.getByRole('link', { name: 'Kinerja Mitra' });
            expect(mitraLink).toHaveAttribute('href', '/admin/reports/mitra');
        });
    });

    // ==========================================
    // 2. ACTIVE STATE
    // ==========================================
    describe('Active State', () => {
        it('harus menandai tab "pendapatan" sebagai aktif dengan aria-current="page"', () => {
            render(ReportTabs, { props: { active: 'pendapatan' } });
            
            const activeLink = screen.getByRole('link', { name: 'Pendapatan' });
            expect(activeLink).toHaveAttribute('aria-current', 'page');
            expect(activeLink).toHaveClass('bg-pink-dark');
        });

        it('harus menandai tab "mitra" sebagai aktif dengan aria-current="page"', () => {
            render(ReportTabs, { props: { active: 'mitra' } });
            
            const activeLink = screen.getByRole('link', { name: 'Kinerja Mitra' });
            expect(activeLink).toHaveAttribute('aria-current', 'page');
            expect(activeLink).toHaveClass('bg-pink-dark');
        });

        it('TIDAK boleh menandai tab yang tidak aktif dengan aria-current', () => {
            render(ReportTabs, { props: { active: 'pendapatan' } });
            
            const inactiveLink = screen.getByRole('link', { name: 'Kinerja Mitra' });
            expect(inactiveLink).not.toHaveAttribute('aria-current');
            expect(inactiveLink).not.toHaveClass('bg-pink-dark');
        });
    });

    // ==========================================
    // 3. AKSESIBILITAS (A11Y)
    // ==========================================
    describe('Accessibility', () => {
        it('harus memiliki elemen <nav> dengan aria-label', () => {
            render(ReportTabs, { props: { active: 'pendapatan' } });
            
            const nav = screen.getByRole('navigation', { name: /Navigasi Laporan/i });
            expect(nav).toBeInTheDocument();
        });
    });
});