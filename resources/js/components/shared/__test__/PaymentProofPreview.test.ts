import { render, screen } from '@testing-library/svelte';
import { describe, it, expect } from 'vitest';
import PaymentProofPreview from '../PaymentProofPreview.svelte';

describe('PaymentProofPreview Component', () => {
    // ==========================================
    // 1. KONDISI "ADA BUKTI" (hasProof = true)
    // ==========================================
    describe('When Proof Exists', () => {
        it('harus merender judul "Bukti Transfer"', () => {
            render(PaymentProofPreview, {
                props: { bookingId: 123, role: 'client', hasProof: true },
            });
            expect(screen.getByText('Bukti Transfer')).toBeInTheDocument();
        });

        it('harus membentuk URL yang benar untuk role "client"', () => {
            render(PaymentProofPreview, {
                props: { bookingId: 123, role: 'client', hasProof: true },
            });

            const link = screen.getByLabelText(
                /Buka bukti transfer booking 123/i,
            );
            expect(link).toHaveAttribute(
                'href',
                '/client/bookings/123/payment-proof',
            );
        });

        it('harus membentuk URL yang benar untuk role "mitra"', () => {
            render(PaymentProofPreview, {
                props: { bookingId: 456, role: 'mitra', hasProof: true },
            });

            const link = screen.getByLabelText(
                /Buka bukti transfer booking 456/i,
            );
            expect(link).toHaveAttribute(
                'href',
                '/mitra/bookings/456/payment-proof',
            );
        });

        it('harus membentuk URL yang benar untuk role "admin"', () => {
            render(PaymentProofPreview, {
                props: { bookingId: 789, role: 'admin', hasProof: true },
            });

            const link = screen.getByLabelText(
                /Buka bukti transfer booking 789/i,
            );
            expect(link).toHaveAttribute(
                'href',
                '/admin/bookings/789/payment-proof',
            );
        });

        it('harus merender gambar dengan alt text yang deskriptif', () => {
            render(PaymentProofPreview, {
                props: { bookingId: 123, role: 'client', hasProof: true },
            });

            const img = screen.getByAltText(
                /Bukti Transfer untuk Booking #123/i,
            );
            expect(img).toBeInTheDocument();
        });

        it('TIDAK boleh merender tautan "Buka di tab baru" jika showLinkToNewTab=false', () => {
            render(PaymentProofPreview, {
                props: {
                    bookingId: 123,
                    role: 'client',
                    hasProof: true,
                    showLinkToNewTab: false,
                },
            });

            expect(
                screen.queryByText('Buka di tab baru'),
            ).not.toBeInTheDocument();
        });
    });

    // ==========================================
    // 2. KONDISI "TIDAK ADA BUKTI" (hasProof = false)
    // ==========================================
    describe('When Proof Does Not Exist', () => {
        it('harus menampilkan pesan default "Client belum mengunggah..."', () => {
            render(PaymentProofPreview, {
                props: { bookingId: 123, role: 'client', hasProof: false },
            });

            expect(
                screen.getByText('Client belum mengunggah bukti transfer.'),
            ).toBeInTheDocument();
        });

        it('harus menampilkan pesan kustom jika emptyMessage diberikan', () => {
            render(PaymentProofPreview, {
                props: {
                    bookingId: 123,
                    role: 'admin',
                    hasProof: false,
                    emptyMessage: 'Belum ada bukti untuk admin.',
                },
            });

            expect(
                screen.getByText('Belum ada bukti untuk admin.'),
            ).toBeInTheDocument();
        });

        it('TIDAK boleh merender link atau gambar apa pun', () => {
            render(PaymentProofPreview, {
                props: { bookingId: 123, role: 'client', hasProof: false },
            });

            expect(screen.queryByRole('link')).not.toBeInTheDocument();
            expect(screen.queryByRole('img')).not.toBeInTheDocument();
        });
    });

    // ==========================================
    // 3. AKSESIBILITAS (A11Y)
    // ==========================================
    describe('Accessibility', () => {
        it('ikon SVG harus disembunyikan dari screen reader', () => {
            const { container } = render(PaymentProofPreview, {
                props: { bookingId: 123, role: 'client', hasProof: true },
            });

            const svgs = container.querySelectorAll('svg');
            svgs.forEach((svg) => {
                expect(svg).toHaveAttribute('aria-hidden', 'true');
            });
        });

        it('tautan gambar harus memiliki aria-label yang jelas', () => {
            render(PaymentProofPreview, {
                props: { bookingId: 123, role: 'client', hasProof: true },
            });

            const imgLink = screen.getByLabelText(
                /Lihat gambar bukti transfer booking 123/i,
            );
            expect(imgLink).toBeInTheDocument();
            expect(imgLink).toHaveAttribute('target', '_blank');
            expect(imgLink).toHaveAttribute('rel', 'noopener noreferrer');
        });
    });
});
