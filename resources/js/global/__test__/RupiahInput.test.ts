import { render, screen, fireEvent } from '@testing-library/svelte';
import { describe, it, expect } from 'vitest';
import RupiahInput from '../RupiahInput.svelte';

describe('RupiahInput Component', () => {
    // ==========================================
    // 1. RENDERING DASAR
    // ==========================================
    describe('Rendering', () => {
        it('harus merender label dan prefix "Rp"', () => {
            render(RupiahInput, { props: { id: 'harga', label: 'Harga Paket' } });
            expect(screen.getByLabelText(/Harga Paket/i)).toBeInTheDocument();
            expect(screen.getByText('Rp')).toBeInTheDocument();
        });

        it('harus menampilkan nilai awal yang terformat (1500000 -> "1.500.000")', () => {
            render(RupiahInput, { props: { id: 'harga', value: 1500000 } });
            expect(screen.getByRole('textbox')).toHaveValue('1.500.000');
        });
    });

    // ==========================================
    // 2. FORMATTING ON-TYPE (User Interaction)
    // ==========================================
    describe('Formatting on Input', () => {
        it('harus memformat angka saat user mengetik', async () => {
            render(RupiahInput, { props: { id: 'harga', value: 0 } });
            const input = screen.getByRole('textbox');

            await fireEvent.input(input, { target: { value: '50000' } });
            
            // Harusnya otomatis jadi "50.000"
            expect(input).toHaveValue('50.000');
        });

        it('harus mengabaikan karakter non-digit (huruf, simbol)', async () => {
            render(RupiahInput, { props: { id: 'harga', value: 0 } });
            const input = screen.getByRole('textbox');

            await fireEvent.input(input, { target: { value: '12abc34' } });
            
            // Harus mengambil digit saja -> 1234 -> "1.234"
            expect(input).toHaveValue('1.234');
        });
    });

    // ==========================================
    // 3. AKSESIBILITAS (A11Y)
    // ==========================================
    describe('Accessibility', () => {
        it('harus memiliki aria-invalid=true jika error diberikan', () => {
            render(RupiahInput, {
                props: { id: 'harga', error: 'Harga wajib diisi' },
            });
            const input = screen.getByRole('textbox');
            expect(input).toHaveAttribute('aria-invalid', 'true');
        });

        it('harus menghubungkan input dengan pesan error via aria-describedby', () => {
            render(RupiahInput, {
                props: { id: 'harga', error: 'Harga wajib diisi' },
            });
            const input = screen.getByRole('textbox');
            expect(input).toHaveAttribute('aria-describedby', 'harga-message');
            expect(document.getElementById('harga-message')).toHaveTextContent('Harga wajib diisi');
        });

        it('pesan error harus memiliki role="alert"', () => {
            render(RupiahInput, {
                props: { id: 'harga', error: 'Harga wajib diisi' },
            });
            expect(screen.getByRole('alert')).toHaveTextContent('Harga wajib diisi');
        });

        it('harus menghubungkan input dengan hint via aria-describedby', () => {
            render(RupiahInput, {
                props: { id: 'harga', hint: 'Minimal Rp 100.000' },
            });
            const input = screen.getByRole('textbox');
            expect(input).toHaveAttribute('aria-describedby', 'harga-message');
            expect(document.getElementById('harga-message')).toHaveTextContent('Minimal Rp 100.000');
        });
    });

    // ==========================================
    // 4. STATE DISABLED & REQUIRED
    // ==========================================
    describe('Disabled & Required', () => {
        it('harus menonaktifkan input jika disabled=true', () => {
            render(RupiahInput, { props: { id: 'harga', disabled: true } });
            expect(screen.getByRole('textbox')).toBeDisabled();
        });

        it('harus menerapkan atribut required jika required=true', () => {
            render(RupiahInput, { props: { id: 'harga', required: true } });
            expect(screen.getByRole('textbox')).toBeRequired();
        });
    });

    // ==========================================
    // 5. EDGE CASES (onBlur)
    // ==========================================
    describe('Edge Cases', () => {
        it('harus memformat ulang nilai saat blur (jika user memanipulasi DOM)', async () => {
            render(RupiahInput, { props: { id: 'harga', value: 1000 } });
            const input = screen.getByRole('textbox');

            // Simulasi user memasukkan "abc" (tidak valid)
            await fireEvent.input(input, { target: { value: 'abc' } });
            
            // Saat blur, harus dikembalikan ke "0"
            await fireEvent.blur(input);
            expect(input).toHaveValue('0');
        });
    });
});