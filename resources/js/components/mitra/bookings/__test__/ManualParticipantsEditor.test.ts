import { render, screen, fireEvent } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import ManualParticipantsEditor from '../ManualParticipantsEditor.svelte';

// ==========================================
// MOCK DATA
// ==========================================
const createMockParticipant = (overrides = {}) => ({
    nama: 'Budi',
    umur: 25,
    no_hp: '081234567890',
    catatan: 'Alergi seafood',
    ...overrides,
});

describe('ManualParticipantsEditor Component', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    // ==========================================
    // 1. RENDERING AWAL (KOSONG)
    // ==========================================
    describe('Empty State', () => {
        it('harus menampilkan pesan kosong jika participants kosong', () => {
            render(ManualParticipantsEditor, {
                props: { participants: [], errors: {} },
            });
            expect(
                screen.getByText('Tentukan jumlah peserta terlebih dahulu.'),
            ).toBeInTheDocument();
        });
    });

    // ==========================================
    // 2. MENAMPILKAN PESERTA
    // ==========================================
    describe('Displaying Participants', () => {
        it('harus merender form peserta', () => {
            render(ManualParticipantsEditor, {
                props: { participants: [createMockParticipant()], errors: {} },
            });

            expect(screen.getByText('Data Peserta')).toBeInTheDocument();
            expect(screen.getByLabelText(/Nama Lengkap/i)).toBeInTheDocument();
            expect(screen.getByLabelText(/Umur/i)).toBeInTheDocument();
            expect(screen.getByLabelText(/No. HP/i)).toBeInTheDocument();
            expect(
                screen.getByLabelText(/Catatan Peserta/i),
            ).toBeInTheDocument();
        });

        it('harus menampilkan nilai awal dari props', () => {
            render(ManualParticipantsEditor, {
                props: { participants: [createMockParticipant()], errors: {} },
            });

            const namaInput = screen.getByLabelText(
                /Nama Lengkap/i,
            ) as HTMLInputElement;
            expect(namaInput.value).toBe('Budi');
        });
    });

    // ==========================================
    // 3. ERROR STATE
    // ==========================================
    describe('Error State', () => {
        it('harus menampilkan pesan error untuk nama jika ada', () => {
            render(ManualParticipantsEditor, {
                props: {
                    participants: [createMockParticipant()],
                    errors: { 'participants.0.nama': 'Nama wajib diisi' },
                },
            });

            expect(screen.getByText('Nama wajib diisi')).toBeInTheDocument();
        });

        it('input harus memiliki aria-invalid=true jika error', () => {
            render(ManualParticipantsEditor, {
                props: {
                    participants: [createMockParticipant()],
                    errors: { 'participants.0.nama': 'Error' },
                },
            });

            const namaInput = screen.getByLabelText(/Nama Lengkap/i);
            expect(namaInput).toHaveAttribute('aria-invalid', 'true');
        });
    });

    // ==========================================
    // 4. USER INTERACTION
    // ==========================================
    describe('User Interaction', () => {
        it('harus memperbarui nilai input saat user mengetik', async () => {
            render(ManualParticipantsEditor, {
                props: { participants: [createMockParticipant()], errors: {} },
            });

            const namaInput = screen.getByLabelText(
                /Nama Lengkap/i,
            ) as HTMLInputElement;
            await fireEvent.input(namaInput, {
                target: { value: 'Budi Baru' },
            });

            expect(namaInput.value).toBe('Budi Baru');
        });
    });

    // ==========================================
    // 5. AKSESIBILITAS (A11Y)
    // ==========================================
    describe('Accessibility', () => {
        it('pesan error harus memiliki role="alert"', () => {
            render(ManualParticipantsEditor, {
                props: {
                    participants: [createMockParticipant()],
                    errors: { 'participants.0.nama': 'Nama wajib diisi' },
                },
            });

            const errorMessage = screen.getByRole('alert');
            expect(errorMessage).toHaveTextContent('Nama wajib diisi');
        });
    });
});
