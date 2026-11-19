import { render, screen } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import TripItinerary from '../TripItinerary.svelte';
import type { TripDay } from '@/types/models';

// ==========================================
// MOCK DATA
// ==========================================
const createMockDay = (overrides: Partial<TripDay> = {}): TripDay => ({
    id: 1,
    trip_id: 1,
    hari_ke: 1,
    tanggal: '2026-10-20',
    judul: 'Penjemputan',
    deskripsi: 'Jemput di bandara',
    status: 'pending',
    created_at: '2026-10-16T10:00:00Z',
    updated_at: '2026-10-16T10:00:00Z',
    ...overrides,
});

describe('TripItinerary Component', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    // ==========================================
    // 1. RENDERING AWAL (KOSONG)
    // ==========================================
    describe('Empty State', () => {
        it('harus menampilkan pesan kosong jika days kosong', () => {
            render(TripItinerary, { props: { days: [] } });
            expect(screen.getByText('Rencana perjalanan belum tersedia.')).toBeInTheDocument();
        });
    });

    // ==========================================
    // 2. MENAMPILKAN DAFTAR HARI
    // ==========================================
    describe('Displaying Days', () => {
        it('harus merender jumlah hari', () => {
            const days = [createMockDay(), createMockDay({ id: 2, hari_ke: 2 })];
            render(TripItinerary, { props: { days } });
            expect(screen.getByText('2 hari')).toBeInTheDocument();
        });

        it('harus merender judul, tanggal, dan deskripsi hari', () => {
            const days = [createMockDay()];
            render(TripItinerary, { props: { days } });

            expect(screen.getByText('Penjemputan')).toBeInTheDocument();
            expect(screen.getByText(/20 Okt 2026/)).toBeInTheDocument();
            expect(screen.getByText('Jemput di bandara')).toBeInTheDocument();
        });
    });

    // ==========================================
    // 3. STATUS BADGE
    // ==========================================
    describe('Status Badge', () => {
        it('harus menampilkan "Belum Dimulai" untuk status pending', () => {
            const days = [createMockDay({ status: 'pending' })];
            render(TripItinerary, { props: { days } });
            expect(screen.getByText('Belum Dimulai')).toBeInTheDocument();
        });

        it('harus menampilkan "Sedang Berjalan" untuk status in_progress', () => {
            const days = [createMockDay({ status: 'in_progress' })];
            render(TripItinerary, { props: { days } });
            expect(screen.getByText('Sedang Berjalan')).toBeInTheDocument();
        });

        it('harus menampilkan "Selesai" untuk status completed', () => {
            const days = [createMockDay({ status: 'completed' })];
            render(TripItinerary, { props: { days } });
            expect(screen.getByText('Selesai')).toBeInTheDocument();
        });
    });

    // ==========================================
    // 4. AKSESIBILITAS (A11Y)
    // ==========================================
    describe('Accessibility', () => {
        it('ikon SVG harus disembunyikan dari screen reader', () => {
            const { container } = render(TripItinerary, { 
                props: { days: [createMockDay()] } 
            });

            const svgs = container.querySelectorAll('svg');
            svgs.forEach((svg) => {
                expect(svg).toHaveAttribute('aria-hidden', 'true');
            });
        });

        it('garis timeline dekoratif harus disembunyikan dari screen reader', () => {
            const { container } = render(TripItinerary, { 
                props: { days: [createMockDay()] } 
            });

            // Garis timeline adalah div dengan class absolute left-3
            const timelineLine = container.querySelector('.left-3');
            expect(timelineLine).toHaveAttribute('aria-hidden', 'true');
        });
    });
});