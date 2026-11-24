import { render, screen } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import TripFacilitiesCard from '../TripFacilitiesCard.svelte';

describe('TripFacilitiesCard Component', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    // ==========================================
    // 1. RENDERING DENGAN FASILITAS
    // ==========================================
    describe('With Facilities', () => {
        it('harus merender judul "Fasilitas"', () => {
            render(TripFacilitiesCard, {
                props: { include: ['Makan'], exclude: ['Tiket'] },
            });
            expect(screen.getByText('Fasilitas')).toBeInTheDocument();
        });

        it('harus merender kolom "Termasuk" dan "Tidak Termasuk"', () => {
            render(TripFacilitiesCard, {
                props: { include: ['Makan'], exclude: ['Tiket'] },
            });
            expect(screen.getByText('Termasuk')).toBeInTheDocument();
            expect(screen.getByText('Tidak Termasuk')).toBeInTheDocument();
        });

        it('harus merender item fasilitas "include"', () => {
            render(TripFacilitiesCard, {
                props: { include: ['Makan', 'Transportasi'], exclude: [] },
            });
            expect(screen.getByText('Makan')).toBeInTheDocument();
            expect(screen.getByText('Transportasi')).toBeInTheDocument();
        });

        it('harus merender item fasilitas "exclude"', () => {
            render(TripFacilitiesCard, {
                props: { include: [], exclude: ['Tiket Pesawat'] },
            });
            expect(screen.getByText('Tiket Pesawat')).toBeInTheDocument();
        });
    });

    // ==========================================
    // 2. HIDDEN STATE (KOSONG)
    // ==========================================
    describe('Hidden State', () => {
        it('TIDAK boleh merender elemen visual apa pun jika include dan exclude kosong', () => {
            const { container } = render(TripFacilitiesCard, {
                props: { include: [], exclude: [] },
            });

            // PERBAIKAN: Cek apakah ada elemen <div> yang dirender (selain komentar Svelte)
            const divElements = container.querySelectorAll('div');
            expect(divElements.length).toBe(0);
        });
    });

    // ==========================================
    // 3. AKSESIBILITAS (A11Y)
    // ==========================================
    describe('Accessibility', () => {
        it('ikon SVG dekoratif harus disembunyikan dari screen reader', () => {
            const { container } = render(TripFacilitiesCard, {
                props: { include: ['Makan'], exclude: ['Tiket'] },
            });

            const svgs = container.querySelectorAll('svg');
            svgs.forEach((svg) => {
                expect(svg).toHaveAttribute('aria-hidden', 'true');
            });
        });
    });
});
