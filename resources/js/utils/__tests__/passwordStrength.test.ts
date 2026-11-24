import { describe, it, expect } from 'vitest';
import {
    calculatePasswordStrength,
    isPasswordMismatch,
} from '../passwordStrength';

describe('calculatePasswordStrength', () => {
    it('mengembalikan level 0 untuk password kosong', () => {
        const result = calculatePasswordStrength('');
        expect(result.level).toBe(0);
        expect(result.label).toBe('');
        expect(result.barColor).toBe(''); // Diperbaiki
    });

    it('menilai "Lemah" untuk password pendek', () => {
        const result = calculatePasswordStrength('abc');
        expect(result.level).toBe(1);
        expect(result.label).toBe('Lemah');
        expect(result.barColor).toBe('bg-red-500'); // Diperbaiki
    });

    it('menilai "Cukup" untuk password dengan 2 kriteria', () => {
        // Kode sumber Anda menggunakan 'bg-sun' untuk "Cukup"
        const result = calculatePasswordStrength('abcdefg1'); // >= 8, ada angka
        expect(result.level).toBe(2);
        expect(result.label).toBe('Cukup');
        expect(result.barColor).toBe('bg-sun'); // Diperbaiki (sebelumnya bg-yellow-500)
    });

    it('menilai "Kuat" untuk password dengan 3 kriteria', () => {
        // Kode sumber Anda menggunakan 'bg-green' untuk "Kuat"
        const result = calculatePasswordStrength('Abcdefgh1'); // >= 8, mixed, angka
        expect(result.level).toBe(3);
        expect(result.label).toBe('Kuat');
        expect(result.barColor).toBe('bg-green'); // Diperbaiki (sebelumnya bg-green-500)
    });

    it('menilai "Sangat Kuat" untuk password dengan 4+ kriteria', () => {
        // Kode sumber Anda menggunakan 'bg-green-dark' untuk "Sangat Kuat"
        const result = calculatePasswordStrength('Abcdefgh123!'); // >= 12, mixed, angka, simbol
        expect(result.level).toBe(4);
        expect(result.label).toBe('Sangat Kuat');
        expect(result.barColor).toBe('bg-green-dark'); // Diperbaiki (sebelumnya bg-green-600)
    });

    it('boundary: password tepat 8 karakter huruf kecil = Lemah', () => {
        const result = calculatePasswordStrength('abcdefgh');
        // hanya >= 8 (1 kriteria) -> Lemah
        expect(result.level).toBe(1);
    });

    it('boundary: password tepat 12 karakter huruf kecil = Cukup', () => {
        const result = calculatePasswordStrength('abcdefghijkl');
        // >= 8 (1) + >= 12 (1) = 2 kriteria -> Cukup
        expect(result.level).toBe(2);
    });
});

describe('isPasswordMismatch', () => {
    it('false jika konfirmasi kosong', () => {
        expect(isPasswordMismatch('abc', '')).toBe(false);
    });

    it('false jika password dan konfirmasi cocok', () => {
        expect(isPasswordMismatch('abc', 'abc')).toBe(false);
    });

    it('true jika password dan konfirmasi tidak cocok', () => {
        expect(isPasswordMismatch('abc', 'xyz')).toBe(true);
    });

    it('true jika hanya beda huruf besar/kecil', () => {
        expect(isPasswordMismatch('Abc', 'abc')).toBe(true);
    });
});
