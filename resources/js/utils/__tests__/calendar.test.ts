import { describe, expect, it, vi } from 'vitest';
import { buildIcs, downloadIcs } from '../calendar';

describe('buildIcs', () => {
    const event = {
        title: 'Trip Bali, 3 Hari',
        start: '2026-11-26T01:00:00.000Z',
        end: '2026-11-28T10:00:00.000Z',
        location: 'Denpasar; Bali',
        description: 'Titik kumpul\nBandara',
    };

    it('menghasilkan file ICS dengan header dan footer yang benar', () => {
        const ics = buildIcs(event);

        expect(ics).toContain('BEGIN:VCALENDAR');
        expect(ics).toContain('VERSION:2.0');
        expect(ics).toContain('BEGIN:VEVENT');
        expect(ics).toContain('END:VEVENT');
        expect(ics).toContain('END:VCALENDAR');
    });

    it('memakai waktu UTC dalam format ICS', () => {
        const ics = buildIcs(event);

        expect(ics).toContain('DTSTART;VALUE=DATE:20261126');
        expect(ics).toContain('DTEND;VALUE=DATE:20261128');
    });

    it('UID stabil dari UUID booking dan menyertakan koordinat', () => {
        const ics = buildIcs({
            ...event,
            uuid: '00000000-0000-4000-8000-000000000001',
            latitude: '-8.6500000',
            longitude: '115.2200000',
        });

        expect(ics).toContain(
            'UID:booking-00000000-0000-4000-8000-000000000001@si-cangkal',
        );
        expect(ics).toContain('GEO:-8.6500000;115.2200000');
        expect(ics.replace(/\r\n /g, '')).toContain(
            'URL:https://www.google.com/maps/search/?api=1&query=-8.6500000%2C115.2200000',
        );
    });

    it('tidak menyertakan GEO bila koordinat tidak ada', () => {
        expect(buildIcs(event)).not.toContain('GEO:');
    });

    it('memakai CRLF dan melipat baris panjang', () => {
        const ics = buildIcs({ ...event, description: 'x'.repeat(200) });

        expect(ics).toContain('\r\n');
        expect(ics.split('\r\n').every((line) => line.length <= 75)).toBe(true);
    });

    it('meng-escape koma, titik koma, dan newline', () => {
        const ics = buildIcs(event);

        expect(ics).toContain('SUMMARY:Trip Bali\\, 3 Hari');
        expect(ics).toContain('LOCATION:Denpasar\\; Bali');
        expect(ics).toContain('DESCRIPTION:Titik kumpul\\nBandara');
    });
});

describe('downloadIcs', () => {
    it('memicu unduhan file .ics', () => {
        const createObjectURL = vi.fn(() => 'blob:trip');
        const revokeObjectURL = vi.fn();
        const click = vi
            .spyOn(HTMLAnchorElement.prototype, 'click')
            .mockImplementation(() => {});

        vi.stubGlobal('URL', {
            ...URL,
            createObjectURL,
            revokeObjectURL,
        });

        downloadIcs('trip.ics', 'BEGIN:VCALENDAR');

        expect(createObjectURL).toHaveBeenCalledOnce();
        expect(click).toHaveBeenCalledOnce();
        expect(revokeObjectURL).toHaveBeenCalledWith('blob:trip');

        click.mockRestore();
        vi.unstubAllGlobals();
    });
});
