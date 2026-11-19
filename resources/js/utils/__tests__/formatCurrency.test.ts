import { describe, it, expect, vi, afterEach } from 'vitest';
import {
    formatCurrency,
    formatNumber,
    parseCurrency,
    maskAccountNumber,
    copyToClipboard,
} from '../formatCurrency';

describe('FormatCurrency & FormatNumber', () => {
    describe('formatCurrency()', () => {
        it('harus memformat angka dengan benar (Happy Path)', () => {
            expect(formatCurrency(850000)).toBe('Rp 850.000');
            expect(formatCurrency(1500000.50)).toBe('Rp 1.500.001'); // dibulatkan
            expect(formatCurrency(0)).toBe('Rp 0');
        });

        it('harus menangani input string (dari backend)', () => {
            expect(formatCurrency('1500000.00')).toBe('Rp 1.500.000');
        });

        it('harus mengembalikan "—" untuk input null/undefined/invalid (Edge Case)', () => {
            expect(formatCurrency(null)).toBe('—');
            expect(formatCurrency(undefined)).toBe('—');
            expect(formatCurrency('')).toBe('—');
            expect(formatCurrency('abc')).toBe('—');
        });
    });

    describe('formatNumber()', () => {
        it('harus memformat angka tanpa "Rp"', () => {
            expect(formatNumber(850000)).toBe('850.000');
            expect(formatNumber('12000')).toBe('12.000');
        });

        it('harus mengembalikan "—" untuk input kosong', () => {
            expect(formatNumber(null)).toBe('—');
        });
    });
});

describe('parseCurrency()', () => {
    describe('Happy Path', () => {
        it('harus mem-parse angka murni', () => {
            expect(parseCurrency(1500)).toBe(1500);
            expect(parseCurrency('1500')).toBe(1500);
        });

        it('harus mem-parse format Rupiah Indonesia (titik ribuan)', () => {
            expect(parseCurrency('850.000')).toBe(850000);
            expect(parseCurrency('1.500.000')).toBe(1500000);
            expect(parseCurrency('Rp 1.500.000')).toBe(1500000);
        });

        it('harus mem-parse format desimal (koma)', () => {
            expect(parseCurrency('1,5')).toBe(1.5);
            expect(parseCurrency('2,75')).toBe(2.75);
        });
    });

    describe('Edge Cases', () => {
        it('harus mengembalikan 0 untuk input kosong/null', () => {
            expect(parseCurrency(null)).toBe(0);
            expect(parseCurrency(undefined)).toBe(0);
            expect(parseCurrency('')).toBe(0);
        });

        it('harus mengembalikan 0 untuk string yang bukan angka', () => {
            expect(parseCurrency('abc')).toBe(0);
            expect(parseCurrency('Rp. ')).toBe(0);
        });

        it('harus menangani format titik dan koma bersamaan (ribuan & desimal)', () => {
            // Contoh: 1.500,50 (standar Indonesia)
            expect(parseCurrency('1.500,50')).toBe(1500.5);
            // Contoh: 1,500.50 (standar Inggris/Amerika)
            expect(parseCurrency('1,500.50')).toBe(1500.5);
        });
    });
});

describe('maskAccountNumber()', () => {
    it('harus menutupi semua digit kecuali 4 digit terakhir', () => {
        expect(maskAccountNumber('1234567890')).toBe('•••• •••• 7890');
        expect(maskAccountNumber('1234')).toBe('****'); // <= 4 digit disensor semua
    });

    it('harus mengembalikan "-" untuk input kosong', () => {
        expect(maskAccountNumber(null)).toBe('-');
        expect(maskAccountNumber('')).toBe('-');
    });
});

describe('copyToClipboard()', () => {
    afterEach(() => {
        vi.restoreAllMocks();
    });

    it('harus memanggil navigator.clipboard.writeText', async () => {
        // 1. Mock API Clipboard
        const writeTextMock = vi.fn().mockResolvedValue(undefined);
        vi.stubGlobal('navigator', {
            clipboard: { writeText: writeTextMock },
        });

        // 2. Panggil fungsi
        await copyToClipboard('teks-rahasia');

        // 3. Verifikasi
        expect(writeTextMock).toHaveBeenCalledWith('teks-rahasia');
    });

    it('harus error jika API Clipboard tidak didukung (Edge Case)', async () => {
        // Mock navigator tanpa clipboard
        vi.stubGlobal('navigator', {});

        await expect(copyToClipboard('text')).rejects.toThrow(
            'Clipboard API tidak tersedia'
        );
    });
});