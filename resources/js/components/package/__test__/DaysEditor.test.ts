import { render, screen, fireEvent } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import DaysEditor from '../DaysEditor.svelte';
import type { PackageDay } from '@/types/models/package';

// ==========================================
// TIPE DATA (Gunakan Omit untuk menghilangkan package_id)
// ==========================================
type EditableDay = Omit<PackageDay, 'package_id'>;

// ==========================================
// MOCK DATA
// ==========================================
const createMockDay = (overrides: Partial<EditableDay> = {}): EditableDay => ({
    id: 1,
    hari_ke: 1,
    judul: 'Penjemputan',
    deskripsi: 'Jemput di bandara',
    jam_mulai: '08:00',
    jam_selesai: '09:00',
    lokasi: 'Bandara',
    ...overrides,
});

describe('DaysEditor Component', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    // ==========================================
    // 1. RENDERING AWAL (KOSONG)
    // ==========================================
    describe('Empty State', () => {
        it('harus menampilkan pesan "Belum ada rencana perjalanan" jika days kosong', () => {
            render(DaysEditor, { props: { days: [] } });
            expect(
                screen.getByText(/Belum ada rencana perjalanan/i),
            ).toBeInTheDocument();
        });

        it('harus menampilkan form tambah hari jika tidak disabled', () => {
            render(DaysEditor, { props: { days: [] } });
            expect(screen.getByText('Tambah Hari Baru')).toBeInTheDocument();
        });
    });

    // ==========================================
    // 2. MENAMPILKAN DAFTAR HARI
    // ==========================================
    describe('Displaying Days', () => {
        it('harus menampilkan daftar hari dengan judul, jam, dan lokasi', () => {
            const days = [createMockDay()];
            render(DaysEditor, { props: { days } });

            expect(screen.getByText('Hari ke-1')).toBeInTheDocument();
            expect(screen.getByText('Penjemputan')).toBeInTheDocument();

            const timeSpan = screen.getByText((_content, element) => {
                return Boolean(
                    element?.tagName === 'SPAN' &&
                    element.textContent?.includes('08:00') &&
                    element.textContent?.includes('09:00'),
                );
            });
            expect(timeSpan).toBeInTheDocument();

            expect(screen.getByText('Bandara')).toBeInTheDocument();
        });

        it('harus menampilkan deskripsi jika ada', () => {
            const days = [createMockDay()];
            render(DaysEditor, { props: { days } });
            expect(screen.getByText('Jemput di bandara')).toBeInTheDocument();
        });
    });

    // ==========================================
    // 3. MENAMBAH HARI BARU
    // ==========================================
    describe('Adding New Day', () => {
        it('harus menambahkan hari baru ke daftar saat form disubmit', async () => {
            render(DaysEditor, { props: { days: [] } });

            const judulInput = screen.getByLabelText(/Judul Kegiatan/i);
            await fireEvent.input(judulInput, {
                target: { value: 'Makan Siang' },
            });

            const form = document.querySelector('form');
            if (form) form.setAttribute('novalidate', '');

            const submitBtn = screen.getByRole('button', {
                name: /Tambahkan ke Daftar/i,
            });
            await fireEvent.click(submitBtn);

            expect(screen.getByText('Makan Siang')).toBeInTheDocument();
        });

        it('harus menampilkan error jika judul kosong', async () => {
            render(DaysEditor, { props: { days: [] } });

            const judulInput = screen.getByLabelText(/Judul Kegiatan/i);
            judulInput.removeAttribute('required');

            const form = document.querySelector('form');
            if (form) form.setAttribute('novalidate', '');

            const submitBtn = screen.getByRole('button', {
                name: /Tambahkan ke Daftar/i,
            });
            await fireEvent.click(submitBtn);

            expect(
                screen.getByText(/Judul kegiatan wajib diisi/i),
            ).toBeInTheDocument();
        });
    });

    // ==========================================
    // 4. MENGHAPUS HARI
    // ==========================================
    describe('Removing Day', () => {
        it('harus menghapus hari dari daftar dan memperbarui nomor urut', async () => {
            const days = [
                createMockDay({ id: 1, hari_ke: 1, judul: 'Hari Pertama' }),
                createMockDay({ id: 2, hari_ke: 2, judul: 'Hari Kedua' }),
            ];
            render(DaysEditor, { props: { days } });

            const deleteBtns = screen.getAllByRole('button', {
                name: /Hapus hari ke-/i,
            });
            await fireEvent.click(deleteBtns[0]);

            expect(screen.queryByText('Hari Pertama')).not.toBeInTheDocument();
            expect(screen.getByText('Hari Kedua')).toBeInTheDocument();
            expect(screen.getByText('Hari ke-1')).toBeInTheDocument();
        });
    });

    // ==========================================
    // 5. MODE DISABLED
    // ==========================================
    describe('Disabled Mode', () => {
        it('TIDAK boleh menampilkan form tambah hari jika disabled=true', () => {
            render(DaysEditor, { props: { days: [], disabled: true } });
            expect(
                screen.queryByText('Tambah Hari Baru'),
            ).not.toBeInTheDocument();
        });

        it('TIDAK boleh menampilkan tombol hapus jika disabled=true', () => {
            const days = [createMockDay()];
            render(DaysEditor, { props: { days, disabled: true } });
            expect(
                screen.queryByRole('button', { name: /Hapus hari ke-/i }),
            ).not.toBeInTheDocument();
        });
    });
});
