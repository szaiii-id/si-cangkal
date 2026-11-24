import { render, screen, fireEvent } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import Create from '../Create.svelte';

const mockApi = vi.hoisted(() => ({
    createReview: vi.fn(),
}));

vi.mock('@/api/client', () => mockApi);

vi.mock('@inertiajs/svelte', () => ({
    inertia: vi.fn(),
}));

vi.mock('@/components/layout/ClientLayout.svelte', () => ({
    default: (anchor: Node, props: any) => {
        props.children?.(anchor);
        return {};
    },
}));

const booking = {
    id: 42,
    kode: 'BKG-2026-042',
    paket: 'Paket Review Test',
};

describe('Client Reviews — Create Page (Component Test)', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('merender info booking dan tautan kembali dari props', () => {
        render(Create, { props: { booking } });

        expect(
            screen.getByRole('heading', { name: 'Bagaimana pengalaman kamu?' }),
        ).toBeInTheDocument();
        expect(screen.getByText('Paket Review Test')).toBeInTheDocument();
        expect(screen.getByText('BKG-2026-042')).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: /Kembali ke Detail Booking/ }),
        ).toHaveAttribute('href', '/client/bookings/42');
    });

    it('state lokal: bintang dan komentar mengontrol tombol submit', async () => {
        render(Create, { props: { booking } });

        const submit = screen.getByRole('button', { name: 'Kirim Review' });
        expect(submit).toBeDisabled();

        await fireEvent.click(
            screen.getByRole('button', { name: '4 bintang' }),
        );
        expect(screen.getByText('Bagus')).toBeInTheDocument();
        expect(submit).toBeDisabled(); // komentar belum diisi

        const komentar = screen.getByRole('textbox', { name: /Komentar/ });
        await fireEvent.input(komentar, {
            target: { value: 'Perjalanan sangat menyenangkan.' },
        });

        expect(submit).toBeEnabled();
        await fireEvent.click(submit);

        expect(mockApi.createReview).toHaveBeenCalledWith(
            42,
            { rating: 4, komentar: 'Perjalanan sangat menyenangkan.' },
            expect.any(Object),
        );
    });

    it('error handling UI: pesan validasi backend ditampilkan ramah', async () => {
        render(Create, { props: { booking } });

        await fireEvent.click(
            screen.getByRole('button', { name: '5 bintang' }),
        );
        await fireEvent.input(
            screen.getByRole('textbox', { name: /Komentar/ }),
            {
                target: { value: 'Perjalanan sangat menyenangkan.' },
            },
        );
        await fireEvent.click(
            screen.getByRole('button', { name: 'Kirim Review' }),
        );

        const options = mockApi.createReview.mock.calls[0][2];
        options.onError({
            rating: 'Bintang wajib dipilih.',
            komentar: 'Komentar minimal 10 karakter.',
        });

        expect(
            await screen.findByText('Bintang wajib dipilih.'),
        ).toHaveAttribute('role', 'alert');
        expect(
            screen.getByText('Komentar minimal 10 karakter.'),
        ).toHaveAttribute('role', 'alert');
    });
});
