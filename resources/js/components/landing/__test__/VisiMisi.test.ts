import { render, screen } from '@testing-library/svelte';
import { describe, it, expect } from 'vitest';
import VisiMisi from '../VisiMisi.svelte';

describe('VisiMisi Component', () => {
    // ==========================================
    // 1. RENDERING DASAR
    // ==========================================
    describe('Rendering', () => {
        it('harus merender judul "Visi & Misi SI-CANGKAL"', () => {
            render(VisiMisi);
            
            // Judul menggunakan tag h2 dengan teks terpecah
            const heading = screen.getByRole('heading', { level: 2 });
            expect(heading).toHaveTextContent(/Visi & Misi SI-CANGKAL/i);
        });

        it('harus merender deskripsi header', () => {
            render(VisiMisi);
            
            expect(screen.getByText(/Membangun ekosistem pariwisata Banua/i)).toBeInTheDocument();
        });
    });

    // ==========================================
    // 2. VISI
    // ==========================================
    describe('Visi Section', () => {
        it('harus merender label "Pernyataan Visi"', () => {
            render(VisiMisi);
            expect(screen.getByText('Pernyataan Visi')).toBeInTheDocument();
        });

        it('harus merender teks visi dengan lengkap', () => {
            render(VisiMisi);
            
            const visiText = screen.getByText(/Menjadi platform rujukan utama/i);
            expect(visiText).toBeInTheDocument();
            expect(visiText.textContent).toContain('transparan');
            expect(visiText.textContent).toContain('akuntabel');
        });
    });

    // ==========================================
    // 3. MISI
    // ==========================================
    describe('Misi Section', () => {
        it('harus merender 3 judul misi', () => {
            render(VisiMisi);
            
            expect(screen.getByText('Digitalisasi Terpadu')).toBeInTheDocument();
            expect(screen.getByText('Pengawalan & Akuntabilitas')).toBeInTheDocument();
            expect(screen.getByText('Kolaborasi Strategis')).toBeInTheDocument();
        });

        it('harus merender deskripsi untuk setiap misi', () => {
            render(VisiMisi);
            
            expect(screen.getByText(/Mengintegrasikan seluruh dokumen perjanjian/i)).toBeInTheDocument();
            expect(screen.getByText(/Menyediakan dasbor pemantauan real-time/i)).toBeInTheDocument();
            expect(screen.getByText(/Memangkas birokrasi melalui alur kerja digital/i)).toBeInTheDocument();
        });

        it('harus merender nomor urut misi (01, 02, 03)', () => {
            render(VisiMisi);
            
            expect(screen.getByText('01')).toBeInTheDocument();
            expect(screen.getByText('02')).toBeInTheDocument();
            expect(screen.getByText('03')).toBeInTheDocument();
        });
    });

    // ==========================================
    // 4. AKSESIBILITAS (A11Y)
    // ==========================================
    describe('Accessibility', () => {
        it('semua ikon SVG harus disembunyikan dari screen reader', () => {
            const { container } = render(VisiMisi);
            
            const svgs = container.querySelectorAll('svg');
            svgs.forEach((svg) => {
                expect(svg.closest('[aria-hidden="true"]')).toBeInTheDocument();
            });
        });
    });
});