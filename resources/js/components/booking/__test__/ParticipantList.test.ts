import { render, screen } from '@testing-library/svelte';
import { describe, it, expect } from 'vitest';
import ParticipantList from '../ParticipantList.svelte';
import type {
    BookingParticipant,
    BookingParticipantResource,
} from '@/types/models';

// ==========================================
// MOCK DATA
// ==========================================
const createMockParticipant = (
    overrides: Partial<BookingParticipant> = {},
): BookingParticipant => ({
    id: 1,
    booking_id: '1',
    nama: 'Budi',
    umur: 25,
    no_hp: '081234567890',
    catatan: null,
    ...overrides,
});

const createMockParticipantResource = (
    overrides: Partial<BookingParticipantResource> = {},
): BookingParticipantResource => ({
    id: 1,
    nama: 'Budi',
    umur: 25,
    umurLabel: '25 tahun',
    no_hp: '081234567890',
    catatan: null,
    has_catatan: false,
    ...overrides,
});

describe('ParticipantList Component', () => {
    // ==========================================
    // 1. RENDERING AWAL (KOSONG)
    // ==========================================
    describe('Empty State', () => {
        it('harus menampilkan pesan default jika participants kosong', () => {
            render(ParticipantList, { props: { participants: [] } });
            expect(
                screen.getByText('Belum ada data peserta.'),
            ).toBeInTheDocument();
        });

        it('harus menampilkan pesan kustom jika emptyMessage diberikan', () => {
            render(ParticipantList, {
                props: { participants: [], emptyMessage: 'Tidak ada peserta.' },
            });
            expect(screen.getByText('Tidak ada peserta.')).toBeInTheDocument();
        });
    });

    // ==========================================
    // 2. MENAMPILKAN PESERTA (BookingParticipant)
    // ==========================================
    describe('Displaying Participants (Eloquent)', () => {
        it('harus menampilkan nama, umur, dan no_hp', () => {
            const participants = [createMockParticipant()];
            render(ParticipantList, { props: { participants } });

            expect(screen.getByText('Budi')).toBeInTheDocument();
            expect(screen.getByText('25 tahun')).toBeInTheDocument();
            expect(screen.getByText('081234567890')).toBeInTheDocument();
        });

        it('harus menampilkan catatan jika ada', () => {
            const participants = [
                createMockParticipant({ catatan: 'Alergi seafood' }),
            ];
            render(ParticipantList, { props: { participants } });

            expect(screen.getByText('Alergi seafood')).toBeInTheDocument();
        });
    });

    // ==========================================
    // 3. MENAMPILKAN PESERTA (BookingParticipantResource)
    // ==========================================
    describe('Displaying Participants (Resource)', () => {
        it('harus menampilkan umurLabel dari resource jika ada', () => {
            const participants = [
                createMockParticipantResource({ umurLabel: 'Dewasa (25 th)' }),
            ];
            render(ParticipantList, { props: { participants } });

            expect(screen.getByText('Dewasa (25 th)')).toBeInTheDocument();
        });

        it('harus menampilkan catatan jika has_catatan=true', () => {
            const participants = [
                createMockParticipantResource({
                    catatan: 'Butuh kursi roda',
                    has_catatan: true,
                }),
            ];
            render(ParticipantList, { props: { participants } });

            expect(screen.getByText('Butuh kursi roda')).toBeInTheDocument();
        });
    });

    // ==========================================
    // 4. CONDITIONAL (showCatatan)
    // ==========================================
    describe('Conditional Catatan', () => {
        it('TIDAK boleh menampilkan catatan jika showCatatan=false', () => {
            const participants = [
                createMockParticipant({ catatan: 'Alergi seafood' }),
            ];
            render(ParticipantList, {
                props: { participants, showCatatan: false },
            });

            expect(
                screen.queryByText('Alergi seafood'),
            ).not.toBeInTheDocument();
        });
    });

    // ==========================================
    // 5. AKSESIBILITAS (A11Y)
    // ==========================================
    describe('Accessibility', () => {
        it('pesan kosong harus memiliki role="status"', () => {
            render(ParticipantList, { props: { participants: [] } });

            const emptyMessage = screen.getByRole('status');
            expect(emptyMessage).toHaveTextContent('Belum ada data peserta.');
        });

        it('nomor urut peserta harus disembunyikan dari screen reader', () => {
            const participants = [createMockParticipant()];
            render(ParticipantList, { props: { participants } });

            // Cari elemen dengan aria-hidden="true"
            const { container } = render(ParticipantList, {
                props: { participants },
            });
            const numberBadge = container.querySelector('[aria-hidden="true"]');
            expect(numberBadge).toBeInTheDocument();
            expect(numberBadge).toHaveTextContent('1');
        });
    });
});
