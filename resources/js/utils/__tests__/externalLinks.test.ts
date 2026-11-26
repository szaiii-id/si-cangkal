import { describe, expect, it } from 'vitest';
import { googleMapsUrl, toWhatsAppNumber, waMeUrl } from '../externalLinks';

describe('toWhatsAppNumber', () => {
    it('mengubah 08xx menjadi 628xx', () => {
        expect(toWhatsAppNumber('0812-3456-7890')).toBe('6281234567890');
    });

    it('membiarkan nomor yang sudah 62', () => {
        expect(toWhatsAppNumber('+62 812 3456 7890')).toBe('6281234567890');
    });

    it('mengembalikan null untuk nomor kosong', () => {
        expect(toWhatsAppNumber(null)).toBeNull();
        expect(toWhatsAppNumber('')).toBeNull();
    });
});

describe('googleMapsUrl', () => {
    it('menyusun tautan pencarian tanpa API key', () => {
        expect(googleMapsUrl('Pulau Bali, Indonesia')).toBe(
            'https://www.google.com/maps/search/?api=1&query=Pulau%20Bali%2C%20Indonesia',
        );
    });

    it('mengencode karakter khusus', () => {
        expect(googleMapsUrl('Hotel A & B')).toContain('Hotel%20A%20%26%20B');
    });
});

describe('waMeUrl', () => {
    it('menyusun deep link WhatsApp dengan pesan terencode', () => {
        expect(waMeUrl('081234567890', 'Halo, tagihan Anda siap.')).toBe(
            'https://wa.me/6281234567890?text=Halo%2C%20tagihan%20Anda%20siap.',
        );
    });

    it('mengembalikan null bila nomor tidak ada', () => {
        expect(waMeUrl(null, 'Halo')).toBeNull();
    });
});
