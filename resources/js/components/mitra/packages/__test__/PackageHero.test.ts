import { render, screen } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import PackageHero from '../PackageHero.svelte';

describe('PackageHero Component', () => {
    const defaultProps = {
        nama: 'Paket Bali',
        deskripsi: 'Liburan seru di Bali',
        kategori: 'wisata_alam',
        status: 'aktif',
        coverImage: 'cover.jpg',
        durasiHari: 3,
        durasiMalam: 2,
    };

    beforeEach(() => {
        vi.clearAllMocks();
    });

    // ==========================================
    // 1. RENDERING DASAR
    // ==========================================
    describe('Rendering', () => {
        it('harus merender nama paket', () => {
            render(PackageHero, { props: defaultProps });
            expect(screen.getByText('Paket Bali')).toBeInTheDocument();
        });

        it('harus merender deskripsi jika ada', () => {
            render(PackageHero, { props: defaultProps });
            expect(
                screen.getByText('Liburan seru di Bali'),
            ).toBeInTheDocument();
        });

        it('harus merender gambar jika coverImage ada', () => {
            render(PackageHero, { props: defaultProps });

            const img = screen.getByAltText('Sampul paket Paket Bali');
            expect(img).toBeInTheDocument();
            expect(img).toHaveAttribute('src', '/storage/cover.jpg');
        });

        it('TIDAK boleh merender gambar jika coverImage null', () => {
            render(PackageHero, {
                props: { ...defaultProps, coverImage: null },
            });

            expect(screen.queryByRole('img')).not.toBeInTheDocument();
        });
    });

    // ==========================================
    // 2. BADGE STATUS & KATEGORI
    // ==========================================
    describe('Badges', () => {
        it('harus merender badge status', () => {
            render(PackageHero, {
                props: { ...defaultProps, status: 'aktif' },
            });
            expect(screen.getByText('Aktif')).toBeInTheDocument();
        });

        it('harus merender badge kategori', () => {
            render(PackageHero, {
                props: { ...defaultProps, kategori: 'wisata_alam' },
            });
            expect(screen.getByText('Wisata Alam')).toBeInTheDocument();
        });

        it('harus merender durasi', () => {
            render(PackageHero, { props: defaultProps });
            expect(screen.getByText('3D2N')).toBeInTheDocument();
        });
    });

    // ==========================================
    // 3. AKSESIBILITAS (A11Y)
    // ==========================================
    describe('Accessibility', () => {
        it('ikon SVG dekoratif harus disembunyikan dari screen reader', () => {
            const { container } = render(PackageHero, { props: defaultProps });

            const svgs = container.querySelectorAll('svg');
            svgs.forEach((svg) => {
                expect(svg).toHaveAttribute('aria-hidden', 'true');
            });
        });
    });
});
