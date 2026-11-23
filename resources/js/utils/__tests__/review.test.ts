import { describe, it, expect } from 'vitest';
import { formatRataRata, ratingLabel } from '../review';

describe('ratingLabel()', () => {
    it('BVA — batas bawah dan atas rating valid', () => {
        expect(ratingLabel(1)).toBe('Sangat buruk');
        expect(ratingLabel(5)).toBe('Sangat bagus');
    });

    it('equivalence partitioning — nilai tengah valid', () => {
        expect(ratingLabel(2)).toBe('Buruk');
        expect(ratingLabel(3)).toBe('Cukup');
        expect(ratingLabel(4)).toBe('Bagus');
    });

    it('BVA — 0 dan 6 ditolak', () => {
        expect(ratingLabel(0)).toBeNull();
        expect(ratingLabel(6)).toBeNull();
    });

    it('edge cases — desimal, negatif, dan NaN ditolak', () => {
        expect(ratingLabel(3.5)).toBeNull();
        expect(ratingLabel(-1)).toBeNull();
        expect(ratingLabel(Number.NaN)).toBeNull();
        expect(ratingLabel(Number.POSITIVE_INFINITY)).toBeNull();
    });
});

describe('formatRataRata()', () => {
    it('happy path — satu angka desimal', () => {
        expect(formatRataRata(4.25)).toBe('4.3');
        expect(formatRataRata(4.5)).toBe('4.5');
    });

    it('edge cases — kosong, nol, dan nilai invalid', () => {
        expect(formatRataRata(0)).toBe('0.0');
        expect(formatRataRata(-1)).toBe('0.0');
        expect(formatRataRata(Number.NaN)).toBe('0.0');
    });
});
