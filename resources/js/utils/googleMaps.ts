export type Coordinates = {
    latitude: string;
    longitude: string;
};

const SHORT_HOSTS = ['maps.app.goo.gl', 'goo.gl'];
const MAP_HOSTS = [
    'google.com',
    'www.google.com',
    'maps.google.com',
    'google.co.id',
    'www.google.co.id',
];

function isCoordinate(value: string, min: number, max: number): boolean {
    const number = Number(value);
    return (
        value.trim() !== '' &&
        Number.isFinite(number) &&
        number >= min &&
        number <= max
    );
}

/** Tautan pendek hasil tombol Bagikan di Google Maps butuh redirect server. */
export function isShortGoogleMapsUrl(url: string): boolean {
    try {
        return SHORT_HOSTS.includes(new URL(url).hostname.toLowerCase());
    } catch {
        return false;
    }
}

/** Apakah URL berasal dari domain Google Maps yang diizinkan. */
export function isGoogleMapsUrl(url: string): boolean {
    try {
        const parsed = new URL(url);
        const host = parsed.hostname.toLowerCase();
        return MAP_HOSTS.includes(host) || SHORT_HOSTS.includes(host);
    } catch {
        return false;
    }
}

/**
 * Ambil koordinat dari URL Google Maps tanpa API key.
 *
 * Mendukung:
 * - `?q=-8.7,115.2` / `?api=1&query=-8.7,115.2`
 * - pola `/@-8.7,115.2,17z`
 * - pola `!3d-8.7!4d115.2`
 */
export function extractCoordinatesFromGoogleMapsUrl(
    url: string,
): Coordinates | null {
    try {
        const parsed = new URL(url);
        const candidates: string[] = [];

        for (const key of ['q', 'query', 'destination']) {
            const value = parsed.searchParams.get(key);
            if (value !== null) candidates.push(value);
        }

        const at = parsed.pathname.match(
            /@(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)/,
        );
        if (at !== null) candidates.push(`${at[1]},${at[2]}`);

        const data = parsed.pathname + parsed.search + parsed.hash;
        const threeD = data.match(/!3d(-?\d+(?:\.\d+)?)/i);
        const fourD = data.match(/!4d(-?\d+(?:\.\d+)?)/i);
        if (threeD !== null && fourD !== null) {
            candidates.push(`${threeD[1]},${fourD[1]}`);
        }

        for (const candidate of candidates) {
            const pair = candidate.match(
                /(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)/,
            );
            if (pair === null) continue;

            const latitude = pair[1];
            const longitude = pair[2];
            if (
                isCoordinate(latitude, -90, 90) &&
                isCoordinate(longitude, -180, 180)
            ) {
                return { latitude, longitude };
            }
        }

        return null;
    } catch {
        return null;
    }
}
