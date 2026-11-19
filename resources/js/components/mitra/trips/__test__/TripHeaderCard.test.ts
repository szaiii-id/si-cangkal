import { render, screen } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import TripHeaderCard from '../TripHeaderCard.svelte';

describe('TripHeaderCard Component', () => {
    const defaultProps = {
        kode: 'TRIP-001',
        status: 'active',
        namaPaket: 'Paket Bali',
        tanggalMulai: '2026-10-20',
        tanggalSelesai: '2026-10-22',
        jumlahPeserta: 10,
        timezone: 'Asia/Makassar',
        manifestUrl: '/mitra/trips/1/manifest',
        client: { name: 'Budi', email: 'budi@test.com' },
    };

    beforeEach(() => {
        vi.clearAllMocks();
    });

    // ==========================================
    // 1. RENDERING DASAR
    // ==========================================
    describe('Rendering', () => {
        it('harus merender kode trip dan nama paket', () => {
            render(TripHeaderCard, { props: defaultProps });

            expect(screen.getByText('TRIP-001')).toBeInTheDocument();
            expect(screen.getByText('Paket Bali')).toBeInTheDocument();
        });

        it('harus merender tanggal mulai dan selesai', () => {
            render(TripHeaderCard, { props: defaultProps });

            expect(screen.getByText(/20 Okt 2026/)).toBeInTheDocument();
            expect(screen.getByText(/22 Okt 2026/)).toBeInTheDocument();
        });

        it('harus merender jumlah peserta', () => {
            render(TripHeaderCard, { props: defaultProps });
            expect(screen.getByText('10 orang')).toBeInTheDocument();
        });

        it('harus merender timezone dalam format panjang', () => {
            render(TripHeaderCard, { props: defaultProps });
            expect(screen.getByText(/WITA/)).toBeInTheDocument();
        });
    });

    // ==========================================
    // 2. TOMBOL MANIFEST
    // ==========================================
    describe('Manifest Button', () => {
        it('harus merender tombol "Cetak Daftar Peserta" dengan link yang benar', () => {
            render(TripHeaderCard, { props: defaultProps });

            const btn = screen.getByLabelText(/Cetak daftar peserta/);
            expect(btn).toBeInTheDocument();
            expect(btn).toHaveAttribute('href', '/mitra/trips/1/manifest');
            expect(btn).toHaveAttribute('target', '_blank');
            expect(btn).toHaveAttribute('rel', 'noopener noreferrer');
        });
    });

    // ==========================================
    // 3. INFO CLIENT (CONDITIONAL)
    // ==========================================
    describe('Client Info', () => {
        it('harus merender info client jika client ada', () => {
            render(TripHeaderCard, { props: defaultProps });

            expect(screen.getByText('Budi')).toBeInTheDocument();
            expect(screen.getByText('budi@test.com')).toBeInTheDocument();
        });

        it('TIDAK boleh merender info client jika client null', () => {
            render(TripHeaderCard, { props: { ...defaultProps, client: null } });

            expect(screen.queryByText('Budi')).not.toBeInTheDocument();
        });
    });

    // ==========================================
    // 4. AKSESIBILITAS (A11Y)
    // ==========================================
    describe('Accessibility', () => {
        it('ikon SVG dekoratif harus disembunyikan dari screen reader', () => {
            const { container } = render(TripHeaderCard, { props: defaultProps });
            
            const svgs = container.querySelectorAll('svg');
            svgs.forEach((svg) => {
                expect(svg).toHaveAttribute('aria-hidden', 'true');
            });
        });
    });
});