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

        expect(ics).toContain('DTSTART:20261126T010000Z');
        expect(ics).toContain('DTEND:20261128T100000Z');
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
