import { render, screen, fireEvent, waitFor } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach } from 'vitest';

vi.mock('@/api/client', () => ({
    createXenditCharge: vi.fn(),
}));

vi.mock('@/utils/toast.svelte', () => ({
    showToast: vi.fn(),
}));

import PaymentOptionsCard from '../PaymentOptionsCard.svelte';
import { createXenditCharge } from '@/api/client';
import { showToast } from '@/utils/toast.svelte';

describe('PaymentOptionsCard', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('menampilkan opsi online lebih menonjol dan transfer sebagai opsi kedua', () => {
        render(PaymentOptionsCard, {
            props: { bookingId: '42', canUseGateway: true },
        });

        expect(screen.getByText(/Bayar Online/i)).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: /Bayar Sekarang/i }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('heading', {
                name: /Transfer ke Rekening Mitra/i,
            }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: /Pilih Transfer/i }),
        ).toBeInTheDocument();
        expect(screen.queryByText(/Xendit/i)).not.toBeInTheDocument();
    });

    it('menyembunyikan tombol gateway bila booking tidak pending', () => {
        render(PaymentOptionsCard, {
            props: { bookingId: '42', canUseGateway: false },
        });

        expect(
            screen.queryByRole('button', { name: /Bayar Sekarang/i }),
        ).not.toBeInTheDocument();
        expect(
            screen.getByRole('heading', {
                name: /Transfer ke Rekening Mitra/i,
            }),
        ).toBeInTheDocument();
    });

    it('memanggil createXenditCharge saat tombol ditekan', async () => {
        render(PaymentOptionsCard, {
            props: { bookingId: '42', canUseGateway: true },
        });

        await fireEvent.click(
            screen.getByRole('button', { name: /Bayar Sekarang/i }),
        );

        expect(createXenditCharge).toHaveBeenCalledTimes(1);
        expect(createXenditCharge).toHaveBeenCalledWith(
            '42',
            expect.any(Object),
        );
    });

    it('menandai tombol sebagai processing saat request berjalan', async () => {
        render(PaymentOptionsCard, {
            props: { bookingId: '42', canUseGateway: true },
        });

        const button = screen.getByRole('button', { name: /Bayar Sekarang/i });

        await fireEvent.click(button);

        await waitFor(() => {
            expect(button).toBeDisabled();
            expect(button).toHaveAttribute('aria-busy', 'true');
        });
    });

    it('menampilkan toast error saat gateway gagal', async () => {
        vi.mocked(createXenditCharge).mockImplementation((_id, options) => {
            options?.onError?.({ payment: 'Gagal membuat link pembayaran.' });
        });

        render(PaymentOptionsCard, {
            props: { bookingId: '42', canUseGateway: true },
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

describe('pilihan metode', () => {
    it('menandai transfer sebagai dipilih setelah tombol ditekan', async () => {
        render(PaymentOptionsCard, {
            props: { bookingId: '42', canUseGateway: true },
        });

        await fireEvent.click(
            screen.getByRole('button', { name: /Pilih Transfer/i }),
        );

        expect(
            screen.queryByRole('button', { name: /Pilih Transfer/i }),
        ).not.toBeInTheDocument();
        expect(screen.getByText(/Transfer Dipilih/i)).toBeInTheDocument();
    });

    it('otomatis memilih transfer bila bukti sudah ada', () => {
        render(PaymentOptionsCard, {
            props: { bookingId: '42', canUseGateway: true, hasProof: true },
        });

        expect(screen.getByText(/Transfer Dipilih/i)).toBeInTheDocument();
    });
});

it('tidak memakai istilah teknis pada teks yang terlihat', () => {
    render(PaymentOptionsCard, {
        props: { bookingId: '42', canUseGateway: true },
    });

    const teks = document.body.textContent ?? '';
    expect(teks).not.toMatch(/Xendit|QRIS|E-WALLET|E-Ticket|webhook|gateway/i);
});
