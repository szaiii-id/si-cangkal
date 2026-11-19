import { describe, it, expect} from 'vitest';
import {
    formatDate,
    formatDateTime,
    formatDateTimeTz,
    formatTime,
} from '../formatDate';

describe('FormatDate & Time Utilities', () => {
    // ==========================================
    // 1. formatDate() - Tanggal Murni (DATE)
    // ==========================================
    describe('formatDate()', () => {
        it('harus memformat string YYYY-MM-DD dengan benar tanpa pergeseran timezone', () => {
            // Meskipun mesin test berjalan di UTC, WIB, atau WITA, 
            // hasilnya harus tetap "16 Okt 2026"
            expect(formatDate('2026-10-16')).toBe('16 Okt 2026');
            expect(formatDate('2026-01-01')).toBe('1 Jan 2026');
            expect(formatDate('2026-12-31')).toBe('31 Des 2026');
        });

        it('harus menangani objek Date dengan benar', () => {
            const date = new Date('2026-10-16T12:00:00Z');
            expect(formatDate(date)).toBe('16 Okt 2026');
        });

        it('harus mengembalikan "—" untuk input null/undefined/invalid', () => {
            expect(formatDate(null)).toBe('—');
            expect(formatDate(undefined)).toBe('—');
            expect(formatDate('')).toBe('—');
            expect(formatDate('bukan-tanggal')).toBe('—');
        });
    });

    // ==========================================
    // 2. formatDateTime() - Timestamp UTC ke Timezone
    // ==========================================
    describe('formatDateTime()', () => {
        it('harus mengonversi UTC ke WITA (Asia/Makassar) dengan benar', () => {
            // 07:00 UTC = 15:00 WITA
            expect(
                formatDateTime('2026-10-16T07:00:00Z', 'Asia/Makassar')
            ).toBe('16 Okt 2026, 15:00');
        });

        it('harus mengonversi UTC ke WIB (Asia/Jakarta) dengan benar', () => {
            // 07:00 UTC = 14:00 WIB
            expect(
                formatDateTime('2026-10-16T07:00:00Z', 'Asia/Jakarta')
            ).toBe('16 Okt 2026, 14:00');
        });

        it('harus mengonversi UTC ke WIT (Asia/Jayapura) dengan benar', () => {
            // 07:00 UTC = 16:00 WIT
            expect(
                formatDateTime('2026-10-16T07:00:00Z', 'Asia/Jayapura')
            ).toBe('16 Okt 2026, 16:00');
        });

        it('harus mengembalikan "—" untuk input invalid', () => {
            expect(formatDateTime(null)).toBe('—');
            expect(formatDateTime('invalid-date')).toBe('—');
        });
    });

    // ==========================================
    // 3. formatDateTimeTz() - Dengan Label Timezone
    // ==========================================
    describe('formatDateTimeTz()', () => {
        it('harus menambahkan label WITA dengan benar', () => {
            expect(
                formatDateTimeTz('2026-10-16T07:00:00Z', 'Asia/Makassar')
            ).toBe('16 Okt 2026, 15:00 WITA');
        });

        it('harus menambahkan label WIB dengan benar', () => {
            expect(
                formatDateTimeTz('2026-10-16T07:00:00Z', 'Asia/Jakarta')
            ).toBe('16 Okt 2026, 14:00 WIB');
        });

        it('harus menampilkan timezone asing apa adanya jika tidak ada di daftar label', () => {
            // Jika input 'Europe/Paris', label TZ_LABELS tidak ada.
            // Maka tidak ada tambahan label WIB/WITA/WIT, atau sesuaikan ekspektasi.
            const result = formatDateTimeTz('2026-10-16T07:00:00Z', 'Europe/Paris');
            // Ekspektasi: 09:00 waktu Paris (CEST di bulan Oktober)
            expect(result).toContain('16 Okt 2026');
            expect(result).not.toContain('WIB');
            expect(result).not.toContain('WITA');
        });

        it('harus mengembalikan "—" untuk input invalid', () => {
            expect(formatDateTimeTz(null)).toBe('—');
        });
    });

    // ==========================================
    // 4. formatTime() - Jam Saja
    // ==========================================
    describe('formatTime()', () => {
        it('harus mengembalikan format "HH:mm" langsung jika input sudah berupa jam (Edge Case)', () => {
            // Ini untuk kolom TIME di DB (misal jam_mulai itinerary)
            expect(formatTime('08:30')).toBe('08:30');
            expect(formatTime('14:00:00')).toBe('14:00');
        });

        it('harus mengonversi timestamp UTC ke jam lokal', () => {
            expect(formatTime('2026-10-16T07:00:00Z', 'Asia/Makassar')).toBe('15:00');
        });

        it('harus mengembalikan "—" untuk input invalid', () => {
            expect(formatTime(null)).toBe('—');
            expect(formatTime('bukan-jam')).toBe('—');
        });
    });

    // ==========================================
    // 5. Edge Cases - Mengunci Timezone Mesin Test
    // ==========================================
    describe('Timezone Robustness (Machine Independent)', () => {
        it('formatDate() harus independen dari timezone mesin (TZ)', () => {
            // Memastikan formatDate('2026-01-01') TIDAK menghasilkan 
            // "31 Des 2025" jika mesin test berada di timezone negatif (misal US).
            expect(formatDate('2026-01-01')).toBe('1 Jan 2026');
        });
    });
});