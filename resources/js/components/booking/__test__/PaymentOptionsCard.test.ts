import { render, screen, fireEvent, waitFor } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach } from 'vitest';

vi.mock('@/api/client', () => ({
    createDokuCharge: vi.fn(),
}));

vi.mock('@/utils/toast.svelte', () => ({
    showToast: vi.fn(),
}));

import PaymentOptionsCard from '../PaymentOptionsCard.svelte';
import { createDokuCharge } from '@/api/client';
import { showToast } from '@/utils/toast.svelte';

describe('PaymentOptionsCard', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('menampilkan opsi gateway dan transfer manual', () => {
        render(PaymentOptionsCard, {
            props: { bookingId: 42, canUseGateway: true },
        });

        expect(screen.getByText(/Bayar dengan DOKU/i)).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: /Bayar Sekarang/i }),
        ).toBeInTheDocument();
        expect(screen.getByText(/Transfer Manual/i)).toBeInTheDocument();
    });

    it('menyembunyikan tombol gateway bila booking tidak pending', () => {
        render(PaymentOptionsCard, {
            props: { bookingId: 42, canUseGateway: false },
        });

        expect(
            screen.queryByRole('button', { name: /Bayar Sekarang/i }),
        ).not.toBeInTheDocument();
        expect(screen.getByText(/Transfer Manual/i)).toBeInTheDocument();
    });

    it('memanggil createDokuCharge saat tombol ditekan', async () => {
        render(PaymentOptionsCard, {
            props: { bookingId: 42, canUseGateway: true },
        });

        await fireEvent.click(
            screen.getByRole('button', { name: /Bayar Sekarang/i }),
        );

        expect(createDokuCharge).toHaveBeenCalledTimes(1);
        expect(createDokuCharge).toHaveBeenCalledWith(42, expect.any(Object));
    });

    it('menandai tombol sebagai processing saat request berjalan', async () => {
        render(PaymentOptionsCard, {
            props: { bookingId: 42, canUseGateway: true },
        });

        const button = screen.getByRole('button', { name: /Bayar Sekarang/i });

        await fireEvent.click(button);

        await waitFor(() => {
            expect(button).toBeDisabled();
            expect(button).toHaveAttribute('aria-busy', 'true');
        });
    });

    it('menampilkan toast error saat gateway gagal', async () => {
        vi.mocked(createDokuCharge).mockImplementation((_id, options) => {
            options?.onError?.({ payment: 'Gagal membuat link pembayaran.' });
        });

        render(PaymentOptionsCard, {
            props: { bookingId: 42, canUseGateway: true },
        });

        await fireEvent.click(
            screen.getByRole('button', { name: /Bayar Sekarang/i }),
        );

        expect(showToast).toHaveBeenCalledWith(
            expect.stringContaining('Gagal'),
            'error',
        );
    });
});
