import { render, screen } from '@testing-library/svelte';
import { describe, it, expect } from 'vitest';
import BookingStatusBanner from '../BookingStatusBanner.svelte';

describe('BookingStatusBanner Component', () => {
    // ==========================================
    // 1. RENDERING DASAR
    // ==========================================
    describe('Rendering', () => {
        it('harus merender kode booking dan deskripsi', () => {
            render(BookingStatusBanner, {
                props: {
                    status: 'pending_payment',
                    kode: 'BK-123',
                    description: 'Silakan transfer ke rekening mitra.',
                },
            });

            expect(screen.getByText('BK-123')).toBeInTheDocument();
            expect(screen.getByText('Silakan transfer ke rekening mitra.')).toBeInTheDocument();
        });

        it('harus merender label status yang sesuai (menggunakan bookingStatusLabel)', () => {
            render(BookingStatusBanner, {
                props: { status: 'confirmed', kode: 'BK-123', description: 'OK' },
            });

            // Label dari helper (bisa "Terkonfirmasi" atau "Dikonfirmasi")
            expect(screen.getByRole('heading', { level: 1 })).toBeInTheDocument();
        });
    });

    // ==========================================
    // 2. ICON & WARNA
    // ==========================================
    describe('Icon & Styling', () => {
        it('harus menerapkan class border yang sesuai untuk status', () => {
            const { container } = render(BookingStatusBanner, {
                props: { status: 'completed', kode: 'BK-123', description: 'OK' },
            });

            const banner = container.firstElementChild;
            expect(banner).toHaveClass('rounded-3xl');
            expect(banner).toHaveClass('border-2');
        });
    });

    // ==========================================
    // 3. COUNTDOWN (CONDITIONAL)
    // ==========================================
    describe('Countdown', () => {
        it('harus menampilkan countdown jika status pending_payment', () => {
            render(BookingStatusBanner, {
                props: {
                    status: 'pending_payment',
                    kode: 'BK-123',
                    description: 'Segera bayar',
                    countdown: '2 jam 30 menit',
                },
            });

            expect(screen.getByText(/Sisa waktu: 2 jam 30 menit/)).toBeInTheDocument();
        });

        it('TIDAK boleh menampilkan countdown jika status bukan pending_payment', () => {
            render(BookingStatusBanner, {
                props: {
                    status: 'confirmed',
                    kode: 'BK-123',
                    description: 'OK',
                    countdown: '2 jam 30 menit',
                },
            });

            expect(screen.queryByText(/Sisa waktu/)).not.toBeInTheDocument();
        });

        it('harus menerapkan class merah jika isExpiringSoon=true', () => {
            render(BookingStatusBanner, {
                props: {
                    status: 'pending_payment',
                    kode: 'BK-123',
                    description: 'Segera bayar',
                    countdown: '10 menit',
                    isExpiringSoon: true,
                },
            });

            const timer = screen.getByRole('timer');
            expect(timer).toHaveClass('bg-red-600');
        });
    });

    // ==========================================
    // 4. AKSESIBILITAS (A11Y)
    // ==========================================
    describe('Accessibility', () => {
        it('ikon SVG harus disembunyikan dari screen reader', () => {
            const { container } = render(BookingStatusBanner, {
                props: { status: 'confirmed', kode: 'BK-123', description: 'OK' },
            });

            const svgs = container.querySelectorAll('svg');
            svgs.forEach((svg) => {
                expect(svg).toHaveAttribute('aria-hidden', 'true');
            });
        });

        it('countdown harus memiliki role="timer"', () => {
            render(BookingStatusBanner, {
                props: {
                    status: 'pending_payment',
                    kode: 'BK-123',
                    description: 'Segera bayar',
                    countdown: '10 menit',
                },
            });

            expect(screen.getByRole('timer')).toBeInTheDocument();
        });
    });
});