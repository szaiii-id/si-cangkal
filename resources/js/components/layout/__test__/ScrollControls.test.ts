import { render, screen, fireEvent } from '@testing-library/svelte';
import { describe, expect, it, vi } from 'vitest';
import ScrollControls from '../ScrollControls.svelte';

describe('ScrollControls', () => {
    it('muncul setelah halaman discroll dan memakai tombol berlabel', async () => {
        render(ScrollControls, { props: { variant: 'client' } });

        expect(screen.queryByRole('button', { name: 'Ke atas' })).toBeNull();

        Object.defineProperty(window, 'scrollY', {
            value: 400,
            configurable: true,
        });
        Object.defineProperty(document.documentElement, 'scrollHeight', {
            value: 2000,
            configurable: true,
        });
        await fireEvent.scroll(window);

        expect(
            screen.getByRole('button', { name: 'Ke atas' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Ke bawah' }),
        ).toBeInTheDocument();
    });

    it('menggulir ke atas saat tombol "Ke atas" ditekan', async () => {
        const scrollTo = vi
            .spyOn(window, 'scrollTo')
            .mockImplementation(() => {});

        Object.defineProperty(window, 'scrollY', {
            value: 400,
            configurable: true,
        });

        render(ScrollControls, { props: { variant: 'client' } });
        await fireEvent.scroll(window);

        await fireEvent.click(screen.getByRole('button', { name: 'Ke atas' }));

        expect(scrollTo).toHaveBeenCalledWith(
            expect.objectContaining({ top: 0 }),
        );

        scrollTo.mockRestore();
    });
});
