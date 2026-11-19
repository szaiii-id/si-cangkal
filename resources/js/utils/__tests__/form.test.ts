import { describe, it, expect } from 'vitest';
import {
    parseRupiah,
    formatRupiahInput,
    parseIntInput,
    hasFormChanges,
    trimObject,
    isEmpty,
} from '../form';

describe('Form Utilities', () => {
    // ==========================================
    // 1. parseRupiah & formatRupiahInput
    // ==========================================
    describe('parseRupiah()', () => {
        it('harus meneruskan ke parseCurrency dan mengembalikan 0 jika kosong', () => {
            // Karena parseRupiah adalah alias dari parseCurrency
            expect(parseRupiah('')).toBe(0);
            expect(parseRupiah('1.500')).toBe(1500);
        });
    });

    describe('formatRupiahInput()', () => {
        it('harus memformat angka dengan titik ribuan', () => {
            expect(formatRupiahInput(850000)).toBe('850.000');
            expect(formatRupiahInput(1500000)).toBe('1.500.000');
        });

        it('harus mengembalikan string kosong untuk input invalid/null', () => {
            expect(formatRupiahInput(null)).toBe('');
            expect(formatRupiahInput(undefined)).toBe('');
            expect(formatRupiahInput(NaN)).toBe('');
        });

        it('harus menangani angka 0 dengan benar (Edge Case)', () => {
            // 0 adalah angka valid, bukan kosong
            expect(formatRupiahInput(0)).toBe('0');
        });
    });

    // ==========================================
    // 2. parseIntInput
    // ==========================================
    describe('parseIntInput()', () => {
        it('harus mem-parse string angka murni', () => {
            expect(parseIntInput('12')).toBe(12);
            expect(parseIntInput('0')).toBe(0);
        });

        it('harus membuang non-digit (titik, koma, spasi, huruf)', () => {
            expect(parseIntInput('1.500')).toBe(1500);
            expect(parseIntInput('Rp 20.000')).toBe(20000);
            expect(parseIntInput('12abc')).toBe(12);
            expect(parseIntInput('abc123')).toBe(123); // angka di belakang
        });

        it('harus mengembalikan 0 untuk input tidak valid/kosong', () => {
            expect(parseIntInput(null)).toBe(0);
            expect(parseIntInput(undefined)).toBe(0);
            expect(parseIntInput('')).toBe(0);
            expect(parseIntInput('abc')).toBe(0);
            expect(parseIntInput(NaN)).toBe(0);
        });

        it('harus memotong desimal (Math.trunc) jika input berupa number', () => {
            expect(parseIntInput(12.9)).toBe(12);
            expect(parseIntInput(-5.5)).toBe(-5);
        });
    });

    // ==========================================
    // 3. hasFormChanges
    // ==========================================
    describe('hasFormChanges()', () => {
        it('harus mendeteksi tidak ada perubahan (false)', () => {
            const initial = { name: 'Budi', age: 25 };
            const current = { name: 'Budi', age: 25 };
            expect(hasFormChanges(current, initial)).toBe(false);
        });

        it('harus mendeteksi perubahan nilai (true)', () => {
            const initial = { name: 'Budi', age: 25 };
            const current = { name: 'Budi Santoso', age: 25 };
            expect(hasFormChanges(current, initial)).toBe(true);
        });

        it('harus mendeteksi perubahan tipe data (number string vs number)', () => {
            const initial: { age: number | string } = { age: 25 };
            const current: { age: number | string } = { age: '25' }; // String vs Number
            // Karena normalizeForCompare mengubah '25' jadi 25, ini dianggap SAMA
            expect(hasFormChanges(current, initial)).toBe(false);
        });

        it('harus mendeteksi perbedaan spasi di ujung string (Edge Case)', () => {
            const initial = { name: 'Budi' };
            const current = { name: ' Budi ' };
            // Karena fungsi normalizeForCompare melakukan trim, ini dianggap SAMA
            expect(hasFormChanges(current, initial)).toBe(false);
        });

        it('harus mendeteksi perbedaan null vs string kosong', () => {
            const initial: { note: string | null } = { note: null };
            const current: { note: string | null } = { note: '' };
            // Di normalizeForCompare, null dan '' dianggap sama ('')
            expect(hasFormChanges(current, initial)).toBe(false);
        });
        
        it('harus mendeteksi perubahan pada array/object (JSON stringify)', () => {
            const initial = { tags: ['a', 'b'] };
            const current = { tags: ['a', 'b', 'c'] };
            expect(hasFormChanges(current, initial)).toBe(true);
        });
    });

    // ==========================================
    // 4. trimObject
    // ==========================================
    describe('trimObject()', () => {
        it('harus memangkas spasi pada semua string value', () => {
            const input = {
                name: '  Budi  ',
                email: ' a@b.c ',
                age: 25, // bukan string, tidak boleh berubah
            };
            const expected = {
                name: 'Budi',
                email: 'a@b.c',
                age: 25,
            };
            expect(trimObject(input)).toEqual(expected);
        });
    });

    // ==========================================
    // 5. isEmpty
    // ==========================================
    describe('isEmpty()', () => {
        it('harus mengembalikan true untuk nilai kosong', () => {
            expect(isEmpty(null)).toBe(true);
            expect(isEmpty(undefined)).toBe(true);
            expect(isEmpty('')).toBe(true);
            expect(isEmpty('   ')).toBe(true); // Spasi saja dianggap kosong
            expect(isEmpty([])).toBe(true);
        });

        it('harus mengembalikan false untuk nilai yang terisi', () => {
            expect(isEmpty('Budi')).toBe(false);
            expect(isEmpty(0)).toBe(false); // Angka 0 bukan kosong
            expect(isEmpty(false)).toBe(false);
            expect(isEmpty([1])).toBe(false);
            expect(isEmpty({})).toBe(false); // Object kosong bukan 'empty' di sini
        });
    });
});