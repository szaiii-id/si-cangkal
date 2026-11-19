import { render, screen, fireEvent } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import SearchInput from '../SearchInput.svelte';

describe('SearchInput Component', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    // ==========================================
    // 1. RENDERING DASAR
    // ==========================================
    describe('Rendering', () => {
        it('harus merender input dengan placeholder', () => {
            render(SearchInput, { props: { placeholder: 'Cari paket wisata...' } });
            expect(screen.getByPlaceholderText('Cari paket wisata...')).toBeInTheDocument();
        });

        it('harus merender nilai awal dari props value', () => {
            render(SearchInput, { props: { value: 'Bali' } });
            expect(screen.getByRole('textbox')).toHaveValue('Bali');
        });

        it('harus menampilkan tombol hapus jika value tidak kosong', () => {
            render(SearchInput, { props: { value: 'Test' } });
            expect(screen.getByRole('button', { name: /hapus pencarian/i })).toBeInTheDocument();
        });

        it('TIDAK boleh menampilkan tombol hapus jika value kosong', () => {
            render(SearchInput, { props: { value: '' } });
            expect(screen.queryByRole('button', { name: /hapus pencarian/i })).not.toBeInTheDocument();
        });
    });

    // ==========================================
    // 2. USER INTERACTION (Binding & Callbacks)
    // ==========================================
    describe('User Interaction', () => {
        it('harus memperbarui nilai saat pengguna mengetik', async () => {
            render(SearchInput, { props: { value: '' } });
            const input = screen.getByRole('textbox');

            await fireEvent.input(input, { target: { value: 'Bali' } });
            expect(input).toHaveValue('Bali');
        });

        it('harus memanggil callback oninput saat pengguna mengetik', async () => {
            const oninput = vi.fn();
            render(SearchInput, { props: { value: '', oninput } });
            const input = screen.getByRole('textbox');

            await fireEvent.input(input, { target: { value: 'Bali' } });
            
            // PERBAIKAN: Karena kita mengambil nilai dari event target
            expect(oninput).toHaveBeenCalledWith('Bali');
        });

        it('harus memanggil onclear dan mengosongkan input saat tombol hapus diklik', async () => {
            const onclear = vi.fn();
            const oninput = vi.fn();
            
            render(SearchInput, { props: { value: 'Test', onclear, oninput } });
            
            const clearBtn = screen.getByRole('button', { name: /hapus pencarian/i });
            await fireEvent.click(clearBtn);

            // Callback harus dipanggil dengan string kosong
            expect(oninput).toHaveBeenCalledWith('');
            expect(onclear).toHaveBeenCalledTimes(1);
            
            // Input harus kosong
            expect(screen.getByRole('textbox')).toHaveValue('');
        });
    });

    // ==========================================
    // 3. FOCUS STATE (Reactivity)
    // ==========================================
    describe('Focus State', () => {
        it('harus mengubah warna border saat input difokuskan', async () => {
            render(SearchInput, { props: { value: '' } });
            const input = screen.getByRole('textbox');

            // Awal: border abu-abu
            expect(input).toHaveClass('border-gray-200');

            // Fokus
            await fireEvent.focus(input);
            expect(input).toHaveClass('border-pink');
        });

        it('harus mengembalikan warna saat blur', async () => {
            render(SearchInput, { props: { value: '' } });
            const input = screen.getByRole('textbox');

            await fireEvent.focus(input);
            await fireEvent.blur(input);

            expect(input).toHaveClass('border-gray-200');
        });
    });
});