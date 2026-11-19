import { render, screen } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import TripTable from '../TripTable.svelte';
import { todayInTimezone } from '@/utils/timezone';
import type { PaginationMeta } from '@/types';

// ==========================================
// MOCK INERTIA
// ==========================================
vi.mock('@inertiajs/svelte', () => ({
    inertia: vi.fn(),
}));

// ==========================================
// MOCK DATA
// ==========================================
const createMockTrip = (overrides = {}) => ({
    id: 1,
    kode: 'TRIP-123',
    status: 'active',
    tanggal_mulai: '2026-10-20',
    tanggal_selesai: '2026-10-22',
    jumlah_peserta: 10,
    timezone: 'Asia/Makassar',
    package: { nama: 'Paket Bali' },
    booking: { client: { name: 'Budi', email: 'budi@test.com' } },
    mitra: { name: 'Mitra A' },
    ...overrides,
});

const createMockPagination = (data: any[]): PaginationMeta & { data: any[] } => ({
    current_page: 1,
    last_page: 1,
    from: 1,
    to: data.length,
    total: data.length,
    per_page: 10,
    links: [],
    data,
});

describe('TripTable Component', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    // ==========================================
    // 1. RENDERING DASAR
    // ==========================================
    describe('Rendering', () => {
        it('harus merender kode trip dan nama paket', () => {
            render(TripTable, { props: { trips: createMockPagination([createMockTrip()]) } });

            // Kode muncul di mobile & desktop
            expect(screen.getAllByText('TRIP-123').length).toBeGreaterThan(0);
            expect(screen.getAllByText('Paket Bali').length).toBeGreaterThan(0);
        });

        it('harus merender nama pelanggan', () => {
            render(TripTable, { props: { trips: createMockPagination([createMockTrip()]) } });

            expect(screen.getAllByText('Budi').length).toBeGreaterThan(0);
        });

        it('harus merender nama mitra', () => {
            render(TripTable, { props: { trips: createMockPagination([createMockTrip()]) } });

            expect(screen.getAllByText('Mitra A').length).toBeGreaterThan(0);
        });
    });

    // ==========================================
    // 2. BADGE "HARI INI"
    // ==========================================
    describe('Today Badge', () => {
        it('harus menampilkan badge "Hari ini" jika trip sedang berlangsung', () => {
            const today = todayInTimezone('Asia/Makassar');
            render(TripTable, { 
                props: { 
                    trips: createMockPagination([
                        createMockTrip({ 
                            tanggal_mulai: today, 
                            tanggal_selesai: today,
                            status: 'active' 
                        })
                    ]) 
                } 
            });

            expect(screen.getAllByText('Hari ini').length).toBeGreaterThan(0);
        });

        it('TIDAK boleh menampilkan badge "Hari ini" jika status bukan active', () => {
            const today = todayInTimezone('Asia/Makassar');
            render(TripTable, { 
                props: { 
                    trips: createMockPagination([
                        createMockTrip({ 
                            tanggal_mulai: today, 
                            tanggal_selesai: today,
                            status: 'scheduled' 
                        })
                    ]) 
                } 
            });

            expect(screen.queryByText('Hari ini')).not.toBeInTheDocument();
        });
    });

    // ==========================================
    // 3. FORMATTING
    // ==========================================
    describe('Formatting', () => {
        it('harus memformat tanggal mulai dan selesai', () => {
            render(TripTable, { props: { trips: createMockPagination([createMockTrip()]) } });

            expect(screen.getAllByText(/20 Okt 2026/).length).toBeGreaterThan(0);
            expect(screen.getAllByText(/22 Okt 2026/).length).toBeGreaterThan(0);
        });

        it('harus memformat durasi', () => {
            render(TripTable, { props: { trips: createMockPagination([createMockTrip()]) } });

            // Durasi dari 20 Okt ke 22 Okt = 3D2N
            expect(screen.getAllByText(/3D2N/).length).toBeGreaterThan(0);
        });
    });

    // ==========================================
    // 4. AKSESIBILITAS (A11Y)
    // ==========================================
    describe('Accessibility', () => {
        it('tabel harus memiliki aria-label', () => {
            render(TripTable, { props: { trips: createMockPagination([createMockTrip()]) } });

            const table = screen.getByRole('table', { name: /Daftar Trip/i });
            expect(table).toBeInTheDocument();
        });

        it('indikator "Hari ini" harus disembunyikan dari screen reader', () => {
            const today = todayInTimezone('Asia/Makassar');
            const { container } = render(TripTable, { 
                props: { 
                    trips: createMockPagination([
                        createMockTrip({ 
                            tanggal_mulai: today, 
                            tanggal_selesai: today,
                            status: 'active' 
                        })
                    ]) 
                } 
            });

            // Cari elemen dot (span dengan animate-pulse)
            const dots = container.querySelectorAll('.animate-pulse');
            dots.forEach((dot) => {
                expect(dot).toHaveAttribute('aria-hidden', 'true');
            });
        });
    });
});