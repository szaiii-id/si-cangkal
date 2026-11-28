import { googleMapsUrl } from './externalLinks';

export type CalendarEvent = {
    title: string;
    start: string;
    end?: string | null;
    location?: string | null;
    description?: string | null;
    uuid?: string | null;
    latitude?: string | null;
    longitude?: string | null;
};

function escapeIcs(value: string): string {
    return value
        .replace(/\\/g, '\\\\')
        .replace(/;/g, '\\;')
        .replace(/,/g, '\\,')
        .replace(/\r?\n/g, '\\n');
}

function toUtcStamp(value: string): string {
    return new Date(value)
        .toISOString()
        .replace(/[-:]/g, '')
        .replace(/\.\d{3}/, '');
}

/** Tanggal all-day (YYYYMMDD), bukan timestamp UTC. */
function toDateStamp(value: string): string {
    return new Date(value).toISOString().slice(0, 10).replace(/-/g, '');
}

/** Lipat baris sesuai RFC 5545 (maksimal 75 oktet per baris). */
function foldIcsLine(line: string): string {
    if (line.length <= 75) return line;

    const chunks: string[] = [line.slice(0, 73)];
    let rest = line.slice(73);

    while (rest.length > 72) {
        chunks.push(` ${rest.slice(0, 72)}`);
        rest = rest.slice(72);
    }

    if (rest !== '') chunks.push(` ${rest}`);

    return chunks.join('\r\n');
}

/** Bangun file .ics (RFC 5545) untuk sebuah jadwal trip. */
export function buildIcs(event: CalendarEvent): string {
    const stamp = toUtcStamp(new Date().toISOString());
    const end =
        event.end ??
        new Date(new Date(event.start).getTime() + 3_600_000).toISOString();
    const uid = event.uuid
        ? `booking-${event.uuid}@si-cangkal`
        : `${stamp}-${Math.random().toString(36).slice(2)}@si-cangkal`;

    const lines = [
        'BEGIN:VCALENDAR',
        'VERSION:2.0',
        'PRODID:-//SI-CANGKAL//ID',
        'CALSCALE:GREGORIAN',
        'METHOD:PUBLISH',
        'BEGIN:VEVENT',
        `UID:${uid}`,
        `DTSTAMP:${stamp}`,
        `DTSTART;VALUE=DATE:${toDateStamp(event.start)}`,
        `DTEND;VALUE=DATE:${toDateStamp(end)}`,
        `SUMMARY:${escapeIcs(event.title)}`,
    ];

    if (event.location) {
        lines.push(`LOCATION:${escapeIcs(event.location)}`);
    }

    if (event.description) {
        lines.push(`DESCRIPTION:${escapeIcs(event.description)}`);
    }

    if (event.latitude && event.longitude) {
        lines.push(`GEO:${event.latitude.trim()};${event.longitude.trim()}`);
        lines.push(
            `URL:${googleMapsUrl(event.title, {
                latitude: event.latitude,
                longitude: event.longitude,
            })}`,
        );
    }

    lines.push('END:VEVENT', 'END:VCALENDAR');

    return lines.map(foldIcsLine).join('\r\n');
}

/** Unduh konten ICS ke file .ics di browser. */
export function downloadIcs(filename: string, content: string): void {
    const blob = new Blob([content], { type: 'text/calendar;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');

    link.href = url;
    link.download = filename.endsWith('.ics') ? filename : `${filename}.ics`;
    document.body.appendChild(link);
    link.click();
    link.remove();

    URL.revokeObjectURL(url);
}
