import { describe, it, expect } from 'vitest';
import {
    RESEND_COOLDOWN_SECONDS,
    formatCooldownLabel,
    parseRetryAfter,
} from '../verification';

describe('Verifikasi Email — pure logic', () => {
    describe('parseRetryAfter() — Boundary Value Analysis', () => {
        it('fallback untuk input kosong atau null', () => {
            expect(parseRetryAfter(null)).toBe(RESEND_COOLDOWN_SECONDS);
            expect(parseRetryAfter(undefined)).toBe(RESEND_COOLDOWN_SECONDS);
            expect(parseRetryAfter('')).toBe(RESEND_COOLDOWN_SECONDS);
        });

        it('fallback untuk angka 0, negatif, NaN, dan Infinity', () => {
            expect(parseRetryAfter(0)).toBe(RESEND_COOLDOWN_SECONDS);
            expect(parseRetryAfter(-5)).toBe(RESEND_COOLDOWN_SECONDS);
            expect(parseRetryAfter('not-a-number')).toBe(
                RESEND_COOLDOWN_SECONDS,
            );
            expect(parseRetryAfter(Number.POSITIVE_INFINITY)).toBe(
                RESEND_COOLDOWN_SECONDS,
            );
        });

        it('menerima string numerik dari header HTTP', () => {
            expect(parseRetryAfter('42')).toBe(42);
            expect(parseRetryAfter('7')).toBe(7);
        });

        it('membulatkan angka desimal ke bawah', () => {
            expect(parseRetryAfter(42.9)).toBe(42);
        });

        it('menghormati fallback kustom', () => {
            expect(parseRetryAfter(null, 30)).toBe(30);
        });
    });

    describe('formatCooldownLabel()', () => {
        it('menampilkan label hitung mundur', () => {
            expect(formatCooldownLabel(60)).toBe('Tunggu 60 detik…');
            expect(formatCooldownLabel(1)).toBe('Tunggu 1 detik…');
        });

        it('tidak menampilkan angka negatif', () => {
            expect(formatCooldownLabel(-3)).toBe('Tunggu 0 detik…');
        });
    });
});
