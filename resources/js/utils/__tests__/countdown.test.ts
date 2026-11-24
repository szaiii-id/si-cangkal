import { describe, it, expect } from 'vitest';
import {
    formatBookingCountdown,
    isBookingExpired,
    getRemainingMinutes,
} from '../countdown';

describe('Countdown Utilities', () => {
    // Waktu acuan tetap untuk pengujian (Deterministic)
    // 16 Okt 2026, 10:00:00 UTC
    const NOW = new Date('2026-10-16T10:00:00Z').getTime();

    // ==========================================
    // 1. formatBookingCountdown()
    // ==========================================
    describe('formatBookingCountdown()', () => {
        it('harus mengembalikan null jika input null/invalid', () => {
            expect(formatBookingCountdown(null, NOW)).toBeNull();
            expect(formatBookingCountdown(undefined, NOW)).toBeNull();
            expect(formatBookingCountdown('', NOW)).toBeNull();
            expect(formatBookingCountdown('invalid-date', NOW)).toBeNull();
        });

        it('harus mendeteksi waktu yang sudah lewat (Expired)', () => {
            // 09:00 UTC (lewat 1 jam dari NOW)
            const result = formatBookingCountdown('2026-10-16T09:00:00Z', NOW);
            expect(result).toEqual({
                label: 'Expired',
                isExpired: true,
                isExpiringSoon: false,
                remainingMs: -3600000, // -1 jam
            });
        });

        it('harus mendeteksi waktu yang TEPAT sama dengan sekarang sebagai Expired (BVA)', () => {
            const result = formatBookingCountdown('2026-10-16T10:00:00Z', NOW);
            expect(result?.isExpired).toBe(true);
            expect(result?.label).toBe('Expired');
        });

        it('harus memformat sisa waktu > 1 jam ("X jam Y menit")', () => {
            // 10:00 + 2 jam 30 menit = 12:30
            const result = formatBookingCountdown('2026-10-16T12:30:00Z', NOW);
            expect(result?.label).toBe('2 jam 30 menit');
            expect(result?.isExpired).toBe(false);
            expect(result?.isExpiringSoon).toBe(true); // 2.5 jam < 6 jam
        });

        it('harus memformat sisa waktu >= 1 menit ("Y menit Z detik")', () => {
            // 10:00 + 45 menit 12 detik = 10:45:12
            const result = formatBookingCountdown('2026-10-16T10:45:12Z', NOW);
            expect(result?.label).toBe('45 menit 12 detik');
            expect(result?.isExpiringSoon).toBe(true);
        });

        it('harus memformat sisa waktu < 1 menit ("Z detik")', () => {
            // 10:00 + 30 detik
            const result = formatBookingCountdown('2026-10-16T10:00:30Z', NOW);
            expect(result?.label).toBe('30 detik');
            expect(result?.isExpiringSoon).toBe(true);
        });

        it('harus mendeteksi isExpiringSoon (sisa < 6 jam) dengan tepat (BVA)', () => {
            // 5 jam 59 menit 59 detik -> Harus TRUE (Expiring Soon)
            const nearlySixHours = formatBookingCountdown(
                '2026-10-16T15:59:59Z',
                NOW,
            );
            expect(nearlySixHours?.isExpiringSoon).toBe(true);

            // Tepat 6 jam -> Harus FALSE (karena rumusnya < threshold)
            const exactlySixHours = formatBookingCountdown(
                '2026-10-16T16:00:00Z',
                NOW,
            );
            expect(exactlySixHours?.isExpiringSoon).toBe(false);

            // 7 jam -> FALSE
            const sevenHours = formatBookingCountdown(
                '2026-10-16T17:00:00Z',
                NOW,
            );
            expect(sevenHours?.isExpiringSoon).toBe(false);
        });
    });

    // ==========================================
    // 2. isBookingExpired()
    // ==========================================
    describe('isBookingExpired()', () => {
        it('harus mengembalikan false untuk input null/invalid', () => {
            expect(isBookingExpired(null, NOW)).toBe(false);
            expect(isBookingExpired('invalid', NOW)).toBe(false);
        });

        it('harus mengembalikan true jika sudah lewat', () => {
            expect(isBookingExpired('2026-10-16T09:00:00Z', NOW)).toBe(true);
        });

        it('harus mengembalikan true jika tepat di waktu sekarang (BVA)', () => {
            expect(isBookingExpired('2026-10-16T10:00:00Z', NOW)).toBe(true);
        });

        it('harus mengembalikan false jika masih di masa depan', () => {
            expect(isBookingExpired('2026-10-16T10:01:00Z', NOW)).toBe(false);
        });
    });

    // ==========================================
    // 3. getRemainingMinutes()
    // ==========================================
    describe('getRemainingMinutes()', () => {
        it('harus mengembalikan null untuk input invalid', () => {
            expect(getRemainingMinutes(null, NOW)).toBeNull();
            expect(getRemainingMinutes('invalid', NOW)).toBeNull();
        });

        it('harus mengembalikan sisa menit yang dibulatkan ke bawah', () => {
            // Sisa 2 menit 30 detik (150.000 ms) -> Harus 2 menit
            expect(getRemainingMinutes('2026-10-16T10:02:30Z', NOW)).toBe(2);
        });

        it('harus mengembalikan nilai negatif jika waktu sudah lewat', () => {
            // Lewat 1 menit -> -1
            expect(getRemainingMinutes('2026-10-16T09:59:00Z', NOW)).toBe(-1);
        });

        it('harus mengembalikan 0 jika sisa waktu < 1 menit', () => {
            expect(getRemainingMinutes('2026-10-16T10:00:30Z', NOW)).toBe(0);
        });
    });
});
