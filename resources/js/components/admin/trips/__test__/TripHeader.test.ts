import { render, screen } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import TripHeader from '../TripHeader.svelte';
import type { TripStatus } from '@/types';

// ==========================================
// MOCK INERTIA
// ==========================================
vi.mock('@inertiajs/svelte', () => ({
    inertia: vi.fn(),
}));

// ==========================================
// MOCK DATA
// ==========================================
const createMockTrip = (overrides: Record<string, unknown> = {}) =>
    ({
        id: 1,
        kode: 'TRIP-123',
        status: 'scheduled' as TripStatus,
        tanggal_mulai: '2026-10-20',
        tanggal_selesai: '2026-10-22',
        jumlah_peserta: 10,
        timezone: 'Asia/Makassar',
        mitra: { id: 1, name: 'Mitra A' },
        package: { nama: 'Paket Bali' },
        booking: { id: 100 },
        ...overrides,
    }) as any;

const createMockSnapshot = (overrides: Record<string, unknown> = {}) => ({
    nama: 'Snapshot Package',
    ...overrides,
});

describe('TripHeader Component', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    // ==========================================
    // 1. RENDERING DASAR
    // ==========================================
    describe('Rendering', () => {
        it('harus merender kode trip dan nama paket dari snapshot', () => {
            render(TripHeader, {
                props: { trip: createMockTrip(), snapshot: createMockSnapshot() },
            });

            expect(screen.getByText('TRIP-123')).toBeInTheDocument();
            expect(screen.getByText('Snapshot Package')).toBeInTheDocument();
        });

        it('harus merender nama paket dari trip.package jika snapshot kosong', () => {
            render(TripHeader, {
                props: { trip: createMockTrip(), snapshot: {} },
            });

            expect(screen.getByText('Paket Bali')).toBeInTheDocument();
        });

        it('harus merender tanggal mulai dan selesai', () => {
            render(TripHeader, {
                props: { trip: createMockTrip(), snapshot: createMockSnapshot() },
            });

            // FormatDate menghasilkan "20 Okt 2026"
            expect(screen.getByText(/20 Okt 2026/)).toBeInTheDocument();
            expect(screen.getByText(/22 Okt 2026/)).toBeInTheDocument();
        });

        it('harus merender jumlah peserta', () => {
            render(TripHeader, {
                props: { trip: createMockTrip(), snapshot: createMockSnapshot() },
            });

            expect(screen.getByText('10 orang')).toBeInTheDocument();
        });

        it('harus merender timezone dalam format panjang', () => {
            render(TripHeader, {
                props: { trip: createMockTrip(), snapshot: createMockSnapshot() },
            });

            expect(screen.getByText(/WITA/)).toBeInTheDocument();
        });
    });

    // ==========================================
    // 2. TOMBOL AKSI
    // ==========================================
    describe('Action Buttons', () => {
        it('harus merender tombol "Cetak Daftar Peserta" dengan link yang benar', () => {
            render(TripHeader, {
                props: { trip: createMockTrip(), snapshot: createMockSnapshot() },
            });

            const btn = screen.getByText('Cetak Daftar Peserta');
            expect(btn).toBeInTheDocument();
            expect(btn.closest('a')).toHaveAttribute('href', '/admin/trips/1/manifest');
            expect(btn.closest('a')).toHaveAttribute('target', '_blank');
            expect(btn.closest('a')).toHaveAttribute('rel', 'noopener noreferrer');
        });

        it('harus merender tombol "Lihat Pesanan" jika trip.booking ada', () => {
            render(TripHeader, {
                props: { trip: createMockTrip(), snapshot: createMockSnapshot() },
            });

            expect(screen.getByText('Lihat Pesanan')).toBeInTheDocument();
        });

        it('TIDAK boleh merender tombol "Lihat Pesanan" jika trip.booking null', () => {
            render(TripHeader, {
                props: {
                    trip: createMockTrip({ booking: null }),
                    snapshot: createMockSnapshot(),
                },
            });

            expect(screen.queryByText('Lihat Pesanan')).not.toBeInTheDocument();
        });
    });

    // ==========================================
    // 3. PERINGATAN MITRA
    // ==========================================
    describe('Mitra Warning', () => {
        it('TIDAK boleh merender peringatan jika trip.mitra ada', () => {
            render(TripHeader, {
                props: { trip: createMockTrip(), snapshot: createMockSnapshot() },
            });

            expect(screen.queryByText(/belum terhubung dengan Mitra/)).not.toBeInTheDocument();
        });

        it('harus merender peringatan jika trip.mitra null', () => {
            render(TripHeader, {
                props: {
                    trip: createMockTrip({ mitra: null }),
                    snapshot: createMockSnapshot(),
                },
            });

            expect(screen.getByText(/belum terhubung dengan Mitra/)).toBeInTheDocument();
        });
    });

    // ==========================================
    // 4. AKSESIBILITAS (A11Y)
    // ==========================================
    describe('Accessibility', () => {
        it('ikon SVG harus disembunyikan dari screen reader', () => {
            const { container } = render(TripHeader, {
                props: {
                    trip: createMockTrip({ mitra: null }),
                    snapshot: createMockSnapshot(),
                },
            });

            const svgs = container.querySelectorAll('svg');
            svgs.forEach((svg) => {
                expect(svg).toHaveAttribute('aria-hidden', 'true');
            });
        });
    });
});