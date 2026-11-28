import { render, screen, fireEvent } from '@testing-library/svelte';
import { describe, expect, it, vi } from 'vitest';
import ScrollControls from '../ScrollControls.svelte';

function setScroll(value: number): void {
    Object.defineProperty(window, 'scrollY', { value, configurable: true });
}

describe('ScrollControls', () => {
    it('di posisi atas hanya menampilkan tombol ke bawah', async () => {
        setScroll(0);
        render(ScrollControls, { props: { variant: 'client' } });
        await fireEvent.scroll(window);

        expect(
            screen.getByRole('button', { name: 'Ke bawah' }),
        ).toBeInTheDocument();
        expect(screen.queryByRole('button', { name: 'Ke atas' })).toBeNull();
    });

    it('setelah scroll hanya menampilkan tombol ke atas', async () => {
        setScroll(0);
        render(ScrollControls, { props: { variant: 'client' } });

        setScroll(400);
        await fireEvent.scroll(window);

        expect(
            screen.getByRole('button', { name: 'Ke atas' }),
        ).toBeInTheDocument();
        expect(screen.queryByRole('button', { name: 'Ke bawah' })).toBeNull();
    });

    it('tombol ke atas menggulir ke posisi 0', async () => {
        const scrollTo = vi
            .spyOn(window, 'scrollTo')
            .mockImplementation(() => {});

        setScroll(400);
        render(ScrollControls, { props: { variant: 'client' } });
        await fireEvent.scroll(window);
        await fireEvent.click(screen.getByRole('button', { name: 'Ke atas' }));

        expect(scrollTo).toHaveBeenCalledWith(
            expect.objectContaining({ top: 0 }),
        );
        scrollTo.mockRestore();
    });

    it('tombol ke bawah menggulir ke bawah halaman', async () => {
        const scrollTo = vi
            .spyOn(window, 'scrollTo')
            .mockImplementation(() => {});

        Object.defineProperty(document.documentElement, 'scrollHeight', {
            value: 2000,
            configurable: true,
        });
        setScroll(0);
        render(ScrollControls, { props: { variant: 'client' } });
        await fireEvent.scroll(window);
        await fireEvent.click(screen.getByRole('button', { name: 'Ke bawah' }));

        expect(scrollTo).toHaveBeenCalledWith(
            expect.objectContaining({ top: 2000 }),
        );
        scrollTo.mockRestore();
    });
});
