import { render, screen, fireEvent } from '@testing-library/svelte';
import { flushSync } from 'svelte';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import TripBundle from '../TripBundle.svelte';

describe('TripBundle Component', () => {
    beforeEach(() => {
        vi.useFakeTimers();
    });

    afterEach(() => {
        vi.useRealTimers();
        vi.restoreAllMocks();
    });

    // ==========================================
    // 1. RENDERING DASAR
    // ==========================================
    describe('Rendering', () => {
        it('harus merender judul utama dan deskripsi', () => {
            render(TripBundle);

            expect(
                screen.getByRole('heading', {
                    name: /Destinasi Unggulan Daerah Kita/i,
                }),
            ).toBeInTheDocument();
            expect(
                screen.getByText(/Jelajahi berbagai paket perjalanan/i),
            ).toBeInTheDocument();
        });

        it('harus merender 3 tombol carousel utama (satu untuk setiap bundle)', () => {
            render(TripBundle);

            const meratusBtn = screen.getByRole('button', {
                name: /Pegunungan Meratus Ekspedisi Puncak Meratus/i,
            });
            const samberBtn = screen.getByRole('button', {
                name: /Kepulauan Kotabaru Pesona Samber Gelap/i,
            });
            const loksadoBtn = screen.getByRole('button', {
                name: /Kawasan Loksado Budaya & Alam Loksado/i,
            });

            expect(meratusBtn).toBeInTheDocument();
            expect(samberBtn).toBeInTheDocument();
            expect(loksadoBtn).toBeInTheDocument();
        });
    });

    // ==========================================
    // 2. ACTIVE STATE & HIGHLIGHT
    // ==========================================
    describe('Active State', () => {
        it('harus menampilkan info paket aktif di kolom kiri', () => {
            render(TripBundle);

            const locationTexts = screen.getAllByText('Pegunungan Meratus');
            expect(locationTexts.length).toBeGreaterThan(0);

            expect(
                screen.getByText(
                    'Eksplorasi alam liar dengan fasilitas premium.',
                ),
            ).toBeInTheDocument();
        });

        it('harus menandai tombol dengan aria-pressed=true untuk item aktif', () => {
            render(TripBundle);

            const activeBtn = screen.getByRole('button', {
                name: /Pegunungan Meratus Ekspedisi Puncak Meratus/i,
            });
            expect(activeBtn).toHaveAttribute('aria-pressed', 'true');
        });
    });

    // ==========================================
    // 3. INTERAKSI MANUAL
    // ==========================================
    describe('User Interaction', () => {
        it('harus mengganti paket aktif saat tombol carousel lain diklik', async () => {
            render(TripBundle);

            const secondBtn = screen.getByRole('button', {
                name: /Kepulauan Kotabaru Pesona Samber Gelap/i,
            });
            await fireEvent.click(secondBtn);

            const locationTexts = screen.getAllByText('Kepulauan Kotabaru');
            expect(locationTexts.length).toBeGreaterThan(0);
            expect(
                screen.getByText(
                    'Relaksasi eksklusif di surga bahari tersembunyi.',
                ),
            ).toBeInTheDocument();
        });
    });

    // ==========================================
    // 4. AUTOPLAY (FAKE TIMERS)
    // ==========================================
    describe('Autoplay', () => {
        it('harus berganti paket secara otomatis setiap 4 detik', () => {
            render(TripBundle);
            flushSync();

            // Awalnya paket 1 (Ekspedisi Puncak Meratus)
            expect(
                screen.getAllByText('Pegunungan Meratus').length,
            ).toBeGreaterThan(0);

            // Majukan waktu 4 detik -> previousBundle() pindah ke paket 3 (Budaya & Alam Loksado)
            vi.advanceTimersByTime(4000);
            flushSync();

            expect(
                screen.getAllByText('Kawasan Loksado').length,
            ).toBeGreaterThan(0);
        });

        it('harus berhenti autoplay saat mouse hover', async () => {
            render(TripBundle);
            flushSync();

            const carouselRegion = screen.getByRole('region', {
                name: /Galeri paket wisata unggulan/i,
            });

            await fireEvent.mouseEnter(carouselRegion);
            flushSync();

            vi.advanceTimersByTime(8000);
            flushSync();

            expect(
                screen.getAllByText('Pegunungan Meratus').length,
            ).toBeGreaterThan(0);
        });
    });

    // ==========================================
    // 5. AKSESIBILITAS (A11Y)
    // ==========================================
    describe('Accessibility', () => {
        it('gambar carousel harus memiliki atribut alt', () => {
            const { container } = render(TripBundle);

            const images = container.querySelectorAll('img');
            expect(images.length).toBeGreaterThan(0);
            images.forEach((img) => {
                expect(img).toHaveAttribute('alt');
            });
        });
    });
});
