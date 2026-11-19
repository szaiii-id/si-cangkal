import { render, screen, fireEvent } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import FacilitiesEditor from '../FacilitiesEditor.svelte';
import type { PackageFacilityTipe } from '@/types/enum';

// ==========================================
// TIPE DATA (Didefinisikan ulang di test)
// ==========================================
type EditableFacility = {
    id: number;
    tipe: PackageFacilityTipe;
    nama: string;
};

// ==========================================
// MOCK DATA
// ==========================================
const createMockFacility = (overrides: Partial<EditableFacility> = {}): EditableFacility => ({
    id: 1,
    tipe: 'include',
    nama: 'Transportasi',
    ...overrides,
});

describe('FacilitiesEditor Component', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    // ==========================================
    // 1. RENDERING AWAL (KOSONG)
    // ==========================================
    describe('Empty State', () => {
        it('harus menampilkan pesan "Belum ada" untuk kedua tipe', () => {
            render(FacilitiesEditor, { props: { facilities: [] } });
            
            const emptyMessages = screen.getAllByText('Belum ada.');
            expect(emptyMessages.length).toBe(2);
        });
    });

    // ==========================================
    // 2. MENAMPILKAN DAFTAR FASILITAS
    // ==========================================
    describe('Displaying Facilities', () => {
        it('harus menampilkan fasilitas "include" di kolom Termasuk', () => {
            const facilities = [createMockFacility({ id: 1, tipe: 'include', nama: 'Makan' })];
            render(FacilitiesEditor, { props: { facilities } });

            expect(screen.getByText('Makan')).toBeInTheDocument();
            expect(screen.getByText(/Termasuk \(1\)/)).toBeInTheDocument();
        });

        it('harus menampilkan fasilitas "exclude" di kolom Tidak Termasuk', () => {
            const facilities = [createMockFacility({ id: 2, tipe: 'exclude', nama: 'Tiket Pesawat' })];
            render(FacilitiesEditor, { props: { facilities } });

            expect(screen.getByText('Tiket Pesawat')).toBeInTheDocument();
            expect(screen.getByText(/Tidak Termasuk \(1\)/)).toBeInTheDocument();
        });
    });

    // ==========================================
    // 3. MENAMBAH FASILITAS
    // ==========================================
    describe('Adding Facility', () => {
        it('harus menambahkan fasilitas baru ke daftar saat form disubmit', async () => {
            render(FacilitiesEditor, { props: { facilities: [] } });

            const namaInput = screen.getByLabelText('Nama Fasilitas');
            await fireEvent.input(namaInput, { target: { value: 'Guide' } });

            const submitBtn = screen.getByRole('button', { name: /\+ Tambah/i });
            await fireEvent.click(submitBtn);

            expect(screen.getByText('Guide')).toBeInTheDocument();
        });

        it('harus menampilkan error jika nama fasilitas kosong', async () => {
            render(FacilitiesEditor, { props: { facilities: [] } });

            const submitBtn = screen.getByRole('button', { name: /\+ Tambah/i });
            await fireEvent.click(submitBtn);

            expect(screen.getByText(/Nama fasilitas wajib diisi/i)).toBeInTheDocument();
        });
    });

    // ==========================================
    // 4. MENGHAPUS FASILITAS
    // ==========================================
    describe('Removing Facility', () => {
        it('harus menghapus fasilitas dari daftar', async () => {
            const facilities = [createMockFacility({ id: 1, nama: 'Makan' })];
            render(FacilitiesEditor, { props: { facilities } });

            expect(screen.getByText('Makan')).toBeInTheDocument();

            const deleteBtn = screen.getByRole('button', { name: /Hapus fasilitas Makan/i });
            await fireEvent.click(deleteBtn);

            expect(screen.queryByText('Makan')).not.toBeInTheDocument();
        });
    });

    // ==========================================
    // 5. MODE DISABLED
    // ==========================================
    describe('Disabled Mode', () => {
        it('TIDAK boleh menampilkan form tambah jika disabled=true', () => {
            render(FacilitiesEditor, { props: { facilities: [], disabled: true } });
            expect(screen.queryByText('Tambah Fasilitas Baru')).not.toBeInTheDocument();
        });

        it('TIDAK boleh menampilkan tombol hapus jika disabled=true', () => {
            const facilities = [createMockFacility()];
            render(FacilitiesEditor, { props: { facilities, disabled: true } });
            expect(screen.queryByRole('button', { name: /Hapus fasilitas/i })).not.toBeInTheDocument();
        });
    });
});