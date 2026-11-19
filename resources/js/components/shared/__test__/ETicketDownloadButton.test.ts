import { render, screen, fireEvent } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import ETicketDownloadButton from '../ETicketDownloadButton.svelte';
import type { ETicketInfo } from '@/types/models';

// ==========================================
// MOCK DEPENDENCIES
// ==========================================
vi.mock('@inertiajs/svelte', () => ({
    router: { reload: vi.fn() },
}));

vi.mock('@/utils/bookingRules', () => ({
    requiresETicket: vi.fn(),
}));

import { router } from '@inertiajs/svelte';
import { requiresETicket } from '@/utils/bookingRules';

// ==========================================
// MOCK DATA
// ==========================================
const createMockETicket = (overrides: Partial<ETicketInfo> = {}): ETicketInfo => ({
    uuid: 'uuid-123',
    download_url: '/eticket/download/uuid-123.pdf',
    generated_at: '2026-10-16T10:00:00Z',
    ...overrides,
});

describe('ETicketDownloadButton Component', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    // ==========================================
    // 1. KONDISI "E-TICKET TERSEDIA"
    // ==========================================
    describe('When E-Ticket Exists', () => {
        it('harus merender judul, tanggal, dan tombol download', () => {
            render(ETicketDownloadButton, { 
                props: { 
                    eticket: createMockETicket(), 
                    bookingStatus: 'confirmed',
                    title: 'E-Ticket Wisata' 
                } 
            });

            expect(screen.getByText('E-Ticket Wisata')).toBeInTheDocument();
            expect(screen.getByText(/Diterbitkan/i)).toBeInTheDocument();
            expect(screen.getByRole('link', { name: /Download E-Ticket/i })).toBeInTheDocument();
        });

        it('harus menerapkan atribut href dan target yang benar pada tombol download', () => {
            render(ETicketDownloadButton, { 
                props: { eticket: createMockETicket({ download_url: '/eticket/download/abc.pdf' }) } 
            });

            const link = screen.getByRole('link', { name: /Download E-Ticket/i });
            expect(link).toHaveAttribute('href', '/eticket/download/abc.pdf');
            expect(link).toHaveAttribute('target', '_blank');
            expect(link).toHaveAttribute('rel', 'noopener noreferrer');
        });
    });

    // ==========================================
    // 2. KONDISI "SEDANG DIPROSES"
    // ==========================================
    describe('When E-Ticket is Processing', () => {
        beforeEach(() => {
            (requiresETicket as any).mockReturnValue(true);
        });

        it('harus merender status "Sedang Diproses" dan tombol "Cek Ulang"', () => {
            render(ETicketDownloadButton, { 
                props: { 
                    eticket: null, 
                    bookingStatus: 'confirmed',
                    title: 'E-Ticket Wisata'
                } 
            });

            expect(screen.getByText(/E-Ticket Wisata Sedang Diproses/i)).toBeInTheDocument();
            expect(screen.getByRole('button', { name: /Refresh halaman/i })).toBeInTheDocument();
        });

        it('harus memanggil router.reload saat tombol "Cek Ulang" diklik', async () => {
            render(ETicketDownloadButton, { 
                props: { eticket: null, bookingStatus: 'confirmed' } 
            });

            const refreshBtn = screen.getByRole('button', { name: /Refresh halaman/i });
            await fireEvent.click(refreshBtn);

            expect(router.reload).toHaveBeenCalledWith({ only: ['eticket'] });
        });
    });

    // ==========================================
    // 3. KONDISI "TIDAK MEMBUTUHKAN E-TICKET"
    // ==========================================
    describe('When E-Ticket is Not Required', () => {
        beforeEach(() => {
            (requiresETicket as any).mockReturnValue(false);
        });

        it('TIDAK boleh merender elemen visual apa pun jika eticket null dan status tidak membutuhkan E-Ticket', () => {
            render(ETicketDownloadButton, { 
                props: { eticket: null, bookingStatus: 'pending_payment' } 
            });

            // PERBAIKAN: Cek tidak ada elemen semantik yang muncul
            expect(screen.queryByRole('link')).not.toBeInTheDocument();
            expect(screen.queryByRole('button')).not.toBeInTheDocument();
            expect(screen.queryByRole('img')).not.toBeInTheDocument();
            expect(screen.queryByRole('heading')).not.toBeInTheDocument();
        });
    });

    // ==========================================
    // 4. AKSESIBILITAS (A11Y)
    // ==========================================
    describe('Accessibility', () => {
        it('ikon SVG harus disembunyikan dari screen reader', () => {
            const { container } = render(ETicketDownloadButton, { 
                props: { eticket: createMockETicket() } 
            });

            const svgs = container.querySelectorAll('svg');
            svgs.forEach((svg) => {
                expect(svg).toHaveAttribute('aria-hidden', 'true');
            });
        });
    });
});