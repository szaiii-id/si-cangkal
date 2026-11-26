export type CalendarEvent = {
    title: string;
    start: string;
    end?: string | null;
    location?: string | null;
    description?: string | null;
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

/** Bangun file .ics (RFC 5545) untuk sebuah jadwal trip. */
export function buildIcs(event: CalendarEvent): string {
    const stamp = toUtcStamp(new Date().toISOString());
    const end = event.end
        ? toUtcStamp(event.end)
        : toUtcStamp(
              new Date(
                  new Date(event.start).getTime() + 60 * 60 * 1000,
              ).toISOString(),
          );

    const lines = [
        'BEGIN:VCALENDAR',
        'VERSION:2.0',
        'PRODID:-//SI-CANGKAL//ID',
        'CALSCALE:GREGORIAN',
        'METHOD:PUBLISH',
        'BEGIN:VEVENT',
        `UID:${stamp}-${Math.random().toString(36).slice(2)}@si-cangkal`,
        `DTSTAMP:${stamp}`,
        `DTSTART:${toUtcStamp(event.start)}`,
        `DTEND:${end}`,
        `SUMMARY:${escapeIcs(event.title)}`,
    ];

    if (event.location) {
        lines.push(`LOCATION:${escapeIcs(event.location)}`);
    }

    if (event.description) {
        lines.push(`DESCRIPTION:${escapeIcs(event.description)}`);
    }

    lines.push('END:VEVENT', 'END:VCALENDAR');

    return lines.join('\r\n');
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
