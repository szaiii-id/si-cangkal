import { render, screen, fireEvent } from '@testing-library/svelte';
import { describe, it, expect, vi } from 'vitest';
import StarRating from '../StarRating.svelte';

describe('StarRating Component', () => {
    it('klik bintang ke-4 menghasilkan rating = 4', async () => {
        const onChange = vi.fn();
        render(StarRating, { props: { onChange } });

        await fireEvent.click(
            screen.getByRole('button', { name: '4 bintang' }),
        );

        expect(onChange).toHaveBeenCalledWith(4);
        expect(
            screen.getByRole('button', { name: '4 bintang' }),
        ).toHaveAttribute('aria-pressed', 'true');
        expect(screen.getByText('Bagus')).toBeInTheDocument();
    });

    it('mode readonly tidak merender tombol', () => {
        render(StarRating, { props: { value: 3, readonly: true } });

        expect(screen.queryByRole('button')).not.toBeInTheDocument();
        expect(
            screen.getByRole('img', { name: 'Rating 3 dari 5 bintang' }),
        ).toBeInTheDocument();
    });
});
