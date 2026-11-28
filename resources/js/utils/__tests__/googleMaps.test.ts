import { describe, expect, it } from 'vitest';
import {
    extractCoordinatesFromGoogleMapsUrl,
    isGoogleMapsUrl,
    isShortGoogleMapsUrl,
} from '../googleMaps';

describe('extractCoordinatesFromGoogleMapsUrl', () => {
    it('membaca koordinat dari parameter q', () => {
        expect(
            extractCoordinatesFromGoogleMapsUrl(
                'https://www.google.com/maps?q=-8.7236110,115.1688890',
            ),
        ).toEqual({ latitude: '-8.7236110', longitude: '115.1688890' });
    });

    it('membaca koordinat dari parameter query Maps URLs', () => {
        expect(
            extractCoordinatesFromGoogleMapsUrl(
                'https://www.google.com/maps/search/?api=1&query=-8.65%2C115.22',
            ),
        ).toEqual({ latitude: '-8.65', longitude: '115.22' });
    });

    it('membaca koordinat dari pola @ pada URL place', () => {
        expect(
            extractCoordinatesFromGoogleMapsUrl(
                'https://www.google.com/maps/place/Pantai+Kuta/@-8.723611,115.168889,17z/data=!3m1!4b1',
            ),
        ).toEqual({ latitude: '-8.723611', longitude: '115.168889' });
    });

    it('membaca koordinat dari pola !3d !4d', () => {
        expect(
            extractCoordinatesFromGoogleMapsUrl(
                'https://www.google.com/maps/place/X/data=!4m2!3m1!1s0x0:0x1!3d-8.7!4d115.2',
            ),
        ).toEqual({ latitude: '-8.7', longitude: '115.2' });
    });

    it('mengembalikan null untuk URL tanpa koordinat', () => {
        expect(
            extractCoordinatesFromGoogleMapsUrl('https://www.google.com/maps'),
        ).toBeNull();
        expect(extractCoordinatesFromGoogleMapsUrl('bukan-url')).toBeNull();
    });
});

describe('deteksi URL', () => {
    it('mengenali tautan pendek', () => {
        expect(isShortGoogleMapsUrl('https://maps.app.goo.gl/abc123')).toBe(
            true,
        );
    });

    it('hanya menerima domain Google', () => {
        expect(isGoogleMapsUrl('https://www.google.com/maps?q=1,2')).toBe(true);
        expect(isGoogleMapsUrl('https://maps.app.goo.gl/abc')).toBe(true);
        expect(isGoogleMapsUrl('https://example.com/maps?q=1,2')).toBe(false);
    });
});
