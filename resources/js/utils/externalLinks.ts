/** Normalisasi nomor Indonesia ke format angka internasional (62...). */
export function toWhatsAppNumber(
    phone: string | null | undefined,
): string | null {
    if (!phone) return null;

    let digits = phone.replace(/\D/g, '');

    if (digits.startsWith('0')) {
        digits = `62${digits.slice(1)}`;
    } else if (!digits.startsWith('62')) {
        digits = `62${digits}`;
    }

    return digits === '' ? null : digits;
}

/** Tautan pencarian Google Maps — tanpa API key dan tanpa SDK. */
export function googleMapsUrl(query: string): string {
    return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(query.trim())}`;
}

/** Deep link WhatsApp dengan pesan yang sudah di-encode. */
export function waMeUrl(
    phone: string | null | undefined,
    message: string,
): string | null {
    const number = toWhatsAppNumber(phone);

    if (!number) return null;

    return `https://wa.me/${number}?text=${encodeURIComponent(message)}`;
}
