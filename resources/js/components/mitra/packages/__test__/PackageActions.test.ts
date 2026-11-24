import { render, screen, fireEvent } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import PackageActions from '../PackageActions.svelte';

// ==========================================
// MOCK INERTIA
// ==========================================
vi.mock('@inertiajs/svelte', () => ({
    inertia: vi.fn(),
}));

describe('PackageActions Component', () => {
    const defaultProps = {
        packageId: 1,
        isDraft: false,
        isAktif: true,
        isArsip: false,
        onArchive: vi.fn(),
        onRestore: vi.fn(),
        onDelete: vi.fn(),
    };

    beforeEach(() => {
        vi.clearAllMocks();
    });

    // ==========================================
    // 1. RENDERING DASAR
    // ==========================================
    describe('Rendering', () => {
        it('harus merender tombol "Edit Paket" dan "Buat Pesanan Manual"', () => {
            render(PackageActions, { props: defaultProps });

            expect(screen.getByLabelText('Edit paket')).toBeInTheDocument();
            expect(
                screen.getByLabelText('Buat pesanan manual'),
            ).toBeInTheDocument();
        });
    });

    // ==========================================
    // 2. TOMBOL KONDISIONAL
    // ==========================================
    describe('Conditional Buttons', () => {
        it('harus merender "Lanjutkan Pembuatan" jika isDraft=true', () => {
            render(PackageActions, {
                props: { ...defaultProps, isDraft: true },
            });
            expect(
                screen.getByLabelText('Lanjutkan pembuatan paket'),
            ).toBeInTheDocument();
        });

        it('TIDAK boleh merender "Lanjutkan Pembuatan" jika isDraft=false', () => {
            render(PackageActions, {
                props: { ...defaultProps, isDraft: false },
            });
            expect(
                screen.queryByLabelText('Lanjutkan pembuatan paket'),
            ).not.toBeInTheDocument();
        });

        it('harus merender "Arsipkan" jika isAktif=true', () => {
            render(PackageActions, {
                props: { ...defaultProps, isAktif: true },
            });
            expect(screen.getByLabelText('Arsipkan paket')).toBeInTheDocument();
        });

        it('harus merender "Aktifkan Kembali" jika isArsip=true', () => {
            render(PackageActions, {
                props: { ...defaultProps, isArsip: true },
            });
            expect(
                screen.getByLabelText('Aktifkan kembali paket'),
            ).toBeInTheDocument();
        });
    });

    // ==========================================
    // 3. INTERAKSI
    // ==========================================
    describe('User Interaction', () => {
        it('harus memanggil onArchive saat tombol "Arsipkan" diklik', async () => {
            const onArchive = vi.fn();
            render(PackageActions, { props: { ...defaultProps, onArchive } });

            await fireEvent.click(screen.getByLabelText('Arsipkan paket'));
            expect(onArchive).toHaveBeenCalledTimes(1);
        });

        it('harus memanggil onRestore saat tombol "Aktifkan Kembali" diklik', async () => {
            const onRestore = vi.fn();
            render(PackageActions, {
                props: { ...defaultProps, isArsip: true, onRestore },
            });

            await fireEvent.click(
                screen.getByLabelText('Aktifkan kembali paket'),
            );
            expect(onRestore).toHaveBeenCalledTimes(1);
        });

        it('harus memanggil onDelete saat tombol "Hapus" diklik', async () => {
            const onDelete = vi.fn();
            render(PackageActions, { props: { ...defaultProps, onDelete } });

            await fireEvent.click(screen.getByLabelText('Hapus paket'));
            expect(onDelete).toHaveBeenCalledTimes(1);
        });
    });

    // ==========================================
    // 4. AKSESIBILITAS (A11Y)
    // ==========================================
    describe('Accessibility', () => {
        it('ikon SVG dekoratif harus disembunyikan dari screen reader', () => {
            const { container } = render(PackageActions, {
                props: defaultProps,
            });

            const svgs = container.querySelectorAll('svg');
            svgs.forEach((svg) => {
                expect(svg).toHaveAttribute('aria-hidden', 'true');
            });
        });
    });
});
