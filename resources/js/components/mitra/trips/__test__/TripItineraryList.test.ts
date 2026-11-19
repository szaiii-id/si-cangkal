import { render, screen } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import TripItineraryList from '../TripItineraryList.svelte';
import type { TripDay } from '@/types';

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

describe('TripItineraryList Component', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    // ==========================================
    // 1. RENDERING AWAL (KOSONG)
    // ==========================================
    describe('Empty State', () => {
        it('harus menampilkan pesan kosong jika days kosong', () => {
            render(TripItineraryList, { props: { days: [] } });
            expect(screen.getByText('Rencana perjalanan belum tersedia.')).toBeInTheDocument();
        });
    });

    // ==========================================
    // 2. MENAMPILKAN DAFTAR HARI
    // ==========================================
    describe('Displaying Days', () => {
        it('harus merender jumlah hari', () => {
            const days = [
                createMockDay({ id: 1, hari_ke: 1 }),
                createMockDay({ id: 2, hari_ke: 2 }),
            ];
            render(TripItineraryList, { props: { days } });
            expect(screen.getByText('2 hari')).toBeInTheDocument();
        });

        it('harus merender judul, tanggal, dan deskripsi hari', () => {
            const days = [createMockDay()];
            render(TripItineraryList, { props: { days } });

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
            render(TripItineraryList, { props: { days } });
            expect(screen.getByText('Belum Dimulai')).toBeInTheDocument();
        });

        it('harus menampilkan "Sedang Berjalan" untuk status in_progress', () => {
            const days = [createMockDay({ status: 'in_progress' })];
            render(TripItineraryList, { props: { days } });
            expect(screen.getByText('Sedang Berjalan')).toBeInTheDocument();
        });

        it('harus menampilkan "Selesai" untuk status completed', () => {
            const days = [createMockDay({ status: 'completed' })];
            render(TripItineraryList, { props: { days } });
            expect(screen.getByText('Selesai')).toBeInTheDocument();
        });
    });

    // ==========================================
    // 4. AKSESIBILITAS (A11Y)
    // ==========================================
    describe('Accessibility', () => {
        it('ikon SVG dekoratif harus disembunyikan dari screen reader', () => {
            const { container } = render(TripItineraryList, { 
                props: { days: [createMockDay()] } 
            });
            
            const svgs = container.querySelectorAll('svg');
            svgs.forEach((svg) => {
                expect(svg).toHaveAttribute('aria-hidden', 'true');
            });
        });

        it('garis timeline dekoratif harus disembunyikan dari screen reader', () => {
            const { container } = render(TripItineraryList, { 
                props: { days: [createMockDay()] } 
            });
            
            const timelineLine = container.querySelector('.left-3');
            expect(timelineLine).toHaveAttribute('aria-hidden', 'true');
        });

        it('empty state harus memiliki role="status"', () => {
            render(TripItineraryList, { props: { days: [] } });
            
            const emptyState = screen.getByRole('status');
            expect(emptyState).toHaveTextContent('Rencana perjalanan belum tersedia.');
        });
    });
});