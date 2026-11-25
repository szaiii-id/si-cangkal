import { render, screen, fireEvent, waitFor } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach } from 'vitest';

vi.mock('@/api/mitra', () => ({
    createPaymentLink: vi.fn(),
}));

vi.mock('@/utils/toast.svelte', () => ({
    showToast: vi.fn(),
}));

import PaymentLinkCard from '../PaymentLinkCard.svelte';
import { createPaymentLink } from '@/api/mitra';
import { showToast } from '@/utils/toast.svelte';

describe('PaymentLinkCard', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        Object.assign(navigator, {
            clipboard: { writeText: vi.fn().mockResolvedValue(undefined) },
        });
    });

    it('hanya muncul untuk booking pending', () => {
        render(PaymentLinkCard, {
            props: { bookingId: 7, canCreate: true },
        });

        expect(
            screen.getByRole('button', { name: /Salin Tautan Pembayaran/i }),
        ).toBeInTheDocument();
    });

    it('tidak merender tombol bila booking bukan pending', () => {
        render(PaymentLinkCard, {
            props: { bookingId: 7, canCreate: false },
        });

        expect(
            screen.queryByRole('button', { name: /Salin Tautan Pembayaran/i }),
        ).not.toBeInTheDocument();
    });

    it('membuat link lalu menyalinnya ke clipboard', async () => {
        vi.mocked(createPaymentLink).mockResolvedValue({
            url: '/bayar/token123',
            expires_at: '2026-11-25T00:00:00Z',
            checkout_url: 'https://dev.xen.to/xyz789',
        });

        render(PaymentLinkCard, {
            props: { bookingId: 7, canCreate: true },
        });

        await fireEvent.click(
            screen.getByRole('button', { name: /Salin Tautan Pembayaran/i }),
        );

        expect(createPaymentLink).toHaveBeenCalledWith(7);
        await waitFor(() => {
            expect(navigator.clipboard.writeText).toHaveBeenCalledWith(
                '/bayar/token123',
            );
        });
        expect(showToast).toHaveBeenCalledWith(
            expect.stringContaining('tersalin'),
            'success',
        );
    });

    it('menandai tombol processing saat request berjalan', async () => {
        vi.mocked(createPaymentLink).mockImplementation(
            () => new Promise(() => {}),
        );

        render(PaymentLinkCard, {
            props: { bookingId: 7, canCreate: true },
        });

        const button = screen.getByRole('button', {
            name: /Salin Tautan Pembayaran/i,
        });
        await fireEvent.click(button);

        await waitFor(() => {
            expect(button).toBeDisabled();
            expect(button).toHaveAttribute('aria-busy', 'true');
        });
    });

    it('menampilkan toast error saat gagal', async () => {
        vi.mocked(createPaymentLink).mockRejectedValue(new Error('Gagal'));

        render(PaymentLinkCard, {
            props: { bookingId: 7, canCreate: true },
        });

        await fireEvent.click(
            screen.getByRole('button', { name: /Salin Tautan Pembayaran/i }),
        );

        await waitFor(() => {
            expect(showToast).toHaveBeenCalledWith(
                expect.stringContaining('Gagal'),
                'error',
            );
        });
    });
});
