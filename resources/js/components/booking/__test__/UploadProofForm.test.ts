import { render, screen, fireEvent, waitFor } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import UploadProofForm from '../UploadProofForm.svelte';

// ==========================================
// MOCK INERTIA (useForm)
// ==========================================
vi.mock('@inertiajs/svelte', () => ({
    useForm: vi.fn(),
}));

import { useForm } from '@inertiajs/svelte';

describe('UploadProofForm Component', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    // ==========================================
    // 1. RENDERING (HAS PROOF)
    // ==========================================
    describe('When Proof Already Exists', () => {
        it('harus menampilkan pesan sukses jika hasProof=true', () => {
            (useForm as any).mockReturnValue({ payment_proof: null, processing: false, errors: {}, post: vi.fn(), reset: vi.fn() });
            
            render(UploadProofForm, { 
                props: { hasProof: true, uploadUrl: '/upload' } 
            });

            expect(screen.getByText(/Bukti transfer sudah diunggah/i)).toBeInTheDocument();
            expect(screen.queryByRole('button', { name: /Kirim Bukti Transfer/i })).not.toBeInTheDocument();
        });
    });

    // ==========================================
    // 2. RENDERING (BELUM ADA PROOF)
    // ==========================================
    describe('When Proof Does Not Exist', () => {
        it('harus menampilkan input file dan tombol submit', () => {
            (useForm as any).mockReturnValue({ payment_proof: null, processing: false, errors: {}, post: vi.fn(), reset: vi.fn() });
            
            render(UploadProofForm, { 
                props: { hasProof: false, uploadUrl: '/upload' } 
            });

            expect(screen.getByLabelText(/Pilih file bukti transfer/i)).toBeInTheDocument();
            expect(screen.getByRole('button', { name: /Kirim Bukti Transfer/i })).toBeInTheDocument();
        });
    });

    // ==========================================
    // 3. VALIDASI FILE
    // ==========================================
    describe('File Validation', () => {
        it('harus menampilkan error jika file terlalu besar (>5MB)', async () => {
            (useForm as any).mockReturnValue({ payment_proof: null, processing: false, errors: {}, post: vi.fn(), reset: vi.fn() });
            
            render(UploadProofForm, { 
                props: { hasProof: false, uploadUrl: '/upload' } 
            });

            const fileInput = screen.getByLabelText(/Pilih file bukti transfer/i);
            
            // Buat file palsu 6MB
            const file = new File([''], 'large.jpg', { type: 'image/jpeg' });
            Object.defineProperty(file, 'size', { value: 6 * 1024 * 1024 });

            await fireEvent.change(fileInput, { target: { files: [file] } });

            // PERBAIKAN: Sesuaikan regex dengan pesan dari validateImageFile
            await waitFor(() => {
                expect(screen.getByText(/Ukuran bukti transfer maksimal 5MB/i)).toBeInTheDocument();
            });
        });
    });

    // ==========================================
    // 4. LOADING STATE
    // ==========================================
    describe('Loading State', () => {
        it('harus menonaktifkan tombol dan menampilkan spinner saat processing', () => {
            (useForm as any).mockReturnValue({ 
                payment_proof: new File([''], 'test.jpg'), 
                processing: true, 
                errors: {}, 
                post: vi.fn(), 
                reset: vi.fn() 
            });
            
            render(UploadProofForm, { 
                props: { hasProof: false, uploadUrl: '/upload' } 
            });

            const btn = screen.getByRole('button');
            expect(btn).toBeDisabled();
            expect(screen.getByText(/Mengunggah/i)).toBeInTheDocument();
        });
    });

    // ==========================================
    // 5. AKSESIBILITAS (A11Y)
    // ==========================================
    describe('Accessibility', () => {
        it('pesan error harus memiliki role="alert"', async () => {
            (useForm as any).mockReturnValue({ 
                payment_proof: null, 
                processing: false, 
                errors: { payment_proof: 'File tidak valid' }, 
                post: vi.fn(), 
                reset: vi.fn() 
            });
            
            render(UploadProofForm, { 
                props: { hasProof: false, uploadUrl: '/upload' } 
            });

            const errorMessage = screen.getByRole('alert');
            expect(errorMessage).toHaveTextContent('File tidak valid');
        });
    });
});