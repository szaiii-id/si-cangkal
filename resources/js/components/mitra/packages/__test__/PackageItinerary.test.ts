import { render, screen } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import PackageItinerary from '../PackageItinerary.svelte';
import type { PackageDay } from '@/types';

// ==========================================
// MOCK DATA
// ==========================================
const createMockDay = (overrides: Partial<PackageDay> = {}): PackageDay => ({
    id: 1,
    package_id: 1,
    hari_ke: 1,
    judul: 'Penjemputan',
    deskripsi: 'Jemput di bandara',
    jam_mulai: '08:00',
    jam_selesai: '09:00',
    lokasi: 'Bandara',
    ...overrides,
});

describe('PackageItinerary Component', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    // ==========================================
    // 1. RENDERING AWAL (KOSONG)
    // ==========================================
    describe('Empty State', () => {
        it('harus menampilkan pesan kosong jika days kosong', () => {
            render(PackageItinerary, { props: { days: [] } });
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
            render(PackageItinerary, { props: { days } });
            expect(screen.getByText('2 hari')).toBeInTheDocument();
        });

        it('harus merender judul, jam, dan lokasi hari', () => {
            const days = [createMockDay()];
            render(PackageItinerary, { props: { days } });

            expect(screen.getByText('Penjemputan')).toBeInTheDocument();

            // PERBAIKAN: Gunakan matcher kustom untuk teks yang dipecah
            const timeSpan = screen.getByText((_content, element) => {
                return Boolean(
                    element?.tagName === 'SPAN' &&
                        element.textContent?.includes('08:00') &&
                        element.textContent?.includes('09:00'),
                );
            });
            expect(timeSpan).toBeInTheDocument();

            expect(screen.getByText('Bandara')).toBeInTheDocument();
        });

        it('harus merender deskripsi jika ada', () => {
            const days = [createMockDay()];
            render(PackageItinerary, { props: { days } });
            expect(screen.getByText('Jemput di bandara')).toBeInTheDocument();
        });
    });

    // ==========================================
    // 3. AKSESIBILITAS (A11Y)
    // ==========================================
    describe('Accessibility', () => {
        it('ikon SVG dekoratif harus disembunyikan dari screen reader', () => {
            const { container } = render(PackageItinerary, {
                props: { days: [createMockDay()] },
            });

            const svgs = container.querySelectorAll('svg');
            svgs.forEach((svg) => {
                expect(svg).toHaveAttribute('aria-hidden', 'true');
            });
        });

        it('garis timeline dekoratif harus disembunyikan dari screen reader', () => {
            const { container } = render(PackageItinerary, {
                props: { days: [createMockDay()] },
            });

            const timelineLine = container.querySelector('.left-3');
            expect(timelineLine).toHaveAttribute('aria-hidden', 'true');
        });

        it('empty state harus memiliki role="status"', () => {
            render(PackageItinerary, { props: { days: [] } });

            const emptyState = screen.getByRole('status');
            expect(emptyState).toHaveTextContent('Rencana perjalanan belum tersedia.');
        });
    });
});