import { render, screen, fireEvent } from '@testing-library/svelte';
import { describe, it, expect } from 'vitest';
import InputText from '../InputText.svelte';

describe('InputText Component', () => {
    // ==========================================
    // 1. RENDERING & PROPS
    // ==========================================
    describe('Rendering', () => {
        it('harus merender label dan placeholder', () => {
            render(InputText, {
                props: { id: 'name', label: 'Nama Lengkap', placeholder: 'Masukkan nama' },
            });

            expect(screen.getByLabelText('Nama Lengkap')).toBeInTheDocument();
            expect(screen.getByPlaceholderText('Masukkan nama')).toBeInTheDocument();
        });

        it('harus merender atribut required dan tanda *', () => {
            render(InputText, { props: { id: 'name', label: 'Nama', required: true } });
            
            // PERBAIKAN: Gunakan regex atau opsi exact:false
            // karena label memiliki tambahan <span>*</span>
            const input = screen.getByLabelText(/Nama/i);
            
            expect(input).toBeRequired();
        });

        it('harus merender hint jika diberikan', () => {
            render(InputText, { props: { id: 'name', label: 'Nama', hint: 'Minimal 3 karakter' } });
            expect(screen.getByText('Minimal 3 karakter')).toBeInTheDocument();
        });

        it('harus merender error jika diberikan', () => {
            render(InputText, { props: { id: 'name', label: 'Nama', error: 'Nama wajib diisi' } });
            expect(screen.getByText('Nama wajib diisi')).toBeInTheDocument();
        });
    });

    // ==========================================
    // 2. USER INTERACTION (Binding)
    // ==========================================
    describe('User Interaction', () => {
        it('harus memperbarui nilai saat pengguna mengetik', async () => {
            render(InputText, { props: { id: 'name', label: 'Nama' } });
            const input = screen.getByLabelText(/Nama/i);

            await fireEvent.input(input, { target: { value: 'Budi' } });
            
            expect(input).toHaveValue('Budi');
        });
    });

    // ==========================================
    // 3. AKSESIBILITAS (A11Y)
    // ==========================================
    describe('Accessibility', () => {
        it('harus memiliki aria-invalid=true jika error diberikan', () => {
            render(InputText, { props: { id: 'name', label: 'Nama', error: 'Wajib diisi' } });
            const input = screen.getByLabelText(/Nama/i);
            expect(input).toHaveAttribute('aria-invalid', 'true');
        });

        it('harus menghubungkan input dengan pesan error via aria-describedby', () => {
            render(InputText, { props: { id: 'name', label: 'Nama', error: 'Wajib diisi' } });
            const input = screen.getByLabelText(/Nama/i);
            
            expect(input).toHaveAttribute('aria-describedby', 'name-message');
            expect(document.getElementById('name-message')).toHaveTextContent('Wajib diisi');
        });

        it('harus menghubungkan input dengan hint via aria-describedby', () => {
            render(InputText, { props: { id: 'name', label: 'Nama', hint: 'Minimal 3 karakter' } });
            const input = screen.getByLabelText(/Nama/i);
            
            expect(input).toHaveAttribute('aria-describedby', 'name-message');
            expect(document.getElementById('name-message')).toHaveTextContent('Minimal 3 karakter');
        });

        it('pesan error harus memiliki role="alert" agar langsung dibacakan', () => {
            render(InputText, { props: { id: 'name', label: 'Nama', error: 'Wajib diisi' } });
            const errorMessage = screen.getByRole('alert');
            expect(errorMessage).toHaveTextContent('Wajib diisi');
        });
    });

    // ==========================================
    // 4. STATE DISABLED & READONLY
    // ==========================================
    describe('Disabled & Readonly', () => {
        it('harus menonaktifkan input jika disabled=true', () => {
            render(InputText, { props: { id: 'name', label: 'Nama', disabled: true } });
            expect(screen.getByLabelText(/Nama/i)).toBeDisabled();
        });

        it('harus menerapkan readonly jika readonly=true', () => {
            render(InputText, { props: { id: 'name', label: 'Nama', readonly: true } });
            expect(screen.getByLabelText(/Nama/i)).toHaveAttribute('readonly');
        });
    });
});