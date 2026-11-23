import { render, screen } from '@testing-library/svelte';
import { describe, it, expect, vi } from 'vitest';
import { tick } from 'svelte';
import Error from '../Error.svelte';

vi.mock('@inertiajs/svelte', () => ({
    inertia: vi.fn(),
}));

describe('Error Page', () => {
    it('menampilkan badge dan pesan sesuai status', () => {
        render(Error, { props: { status: 403 } });

        expect(screen.getByText('Akses Dilarang')).toBeInTheDocument();
        expect(screen.getByText('403')).toBeInTheDocument();
    });

    it('menampilkan hitung mundur Retry-After untuk 429', async () => {
        render(Error, { props: { status: 429, retry_after: 42 } });
        await tick();

        expect(screen.getByText('Kabanyakan Permintaan')).toBeInTheDocument();
        expect(screen.getByText(/Coba lagi dalam 42 detik/i)).toBeInTheDocument();
    });

    it('tidak menampilkan hitung mundur jika retry_after null', async () => {
        render(Error, { props: { status: 429, retry_after: null } });
        await tick();

        expect(screen.queryByText(/Coba lagi dalam/i)).not.toBeInTheDocument();
    });
});
