import { render, screen, fireEvent, waitFor } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import UploadPaymentProofCard from '../UploadPaymentProofCard.svelte';

// ==========================================
// MOCK API & TOAST
// ==========================================
vi.mock('@/api/mitra', () => ({
    uploadPaymentProof: vi.fn(),
}));

vi.mock('@/utils/toast.svelte', () => ({
    showToast: vi.fn(),
}));

import { uploadPaymentProof } from '@/api/mitra';

describe('UploadPaymentProofCard Component', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    // ==========================================
    // 1. RENDERING DASAR
    // ==========================================
    describe('Rendering', () => {
        it('harus merender judul dan area upload', () => {
            render(UploadPaymentProofCard, { props: { bookingId: 1 } });

            expect(
                screen.getByText('Kirim Bukti Transfer'),
            ).toBeInTheDocument();
            expect(
                screen.getByText('Klik untuk memilih file'),
            ).toBeInTheDocument();
        });
    });

    // ==========================================
    // 2. VALIDASI FILE
    // ==========================================
    describe('File Validation', () => {
        it('harus menampilkan error jika file terlalu besar (>5MB)', async () => {
            render(UploadPaymentProofCard, { props: { bookingId: 1 } });

            const label = screen
                .getByLabelText(/Klik untuk memilih file/i)
                .closest('label');
            const input = label?.querySelector('input');

            if (!input) {
                throw new Error('Input file tidak ditemukan');
            }

            const file = new File([''], 'large.jpg', { type: 'image/jpeg' });
            Object.defineProperty(file, 'size', { value: 6 * 1024 * 1024 });

            await fireEvent.change(input, { target: { files: [file] } });

            await waitFor(() => {
                expect(screen.getByRole('alert')).toHaveTextContent(
                    /Ukuran bukti transfer maksimal 5MB/i,
                );
            });
        });

        it('harus menampilkan error jika format file tidak didukung', async () => {
            render(UploadPaymentProofCard, { props: { bookingId: 1 } });

            const label = screen
                .getByLabelText(/Klik untuk memilih file/i)
                .closest('label');
            const input = label?.querySelector('input');

            if (!input) {
                throw new Error('Input file tidak ditemukan');
            }

            const file = new File([''], 'document.pdf', {
                type: 'application/pdf',
            });

            await fireEvent.change(input, { target: { files: [file] } });

            await waitFor(() => {
                expect(screen.getByRole('alert')).toHaveTextContent(
                    /Format bukti transfer tidak didukung/i,
                );
            });
        });
    });

    // ==========================================
    // 3. UPLOAD
    // ==========================================
    describe('Upload', () => {
        it('harus memanggil uploadPaymentProof saat tombol "Kirim Bukti" diklik', async () => {
            render(UploadPaymentProofCard, { props: { bookingId: 1 } });

            const label = screen
                .getByLabelText(/Klik untuk memilih file/i)
                .closest('label');
            const input = label?.querySelector('input');

            if (!input) {
                throw new Error('Input file tidak ditemukan');
            }

            const file = new File([''], 'test.jpg', { type: 'image/jpeg' });
            Object.defineProperty(file, 'size', { value: 500 * 1024 });

            await fireEvent.change(input, { target: { files: [file] } });

            // Setelah file dipilih, tombol "Kirim Bukti" muncul
            const uploadBtn = screen.getByText('Kirim Bukti');
            await fireEvent.click(uploadBtn);

            expect(uploadPaymentProof).toHaveBeenCalledWith(
                1,
                expect.any(FormData),
                expect.any(Object),
            );
        });
    });

    // ==========================================
    // 4. AKSESIBILITAS (A11Y)
    // ==========================================
    describe('Accessibility', () => {
        it('pesan error harus memiliki role="alert"', async () => {
            render(UploadPaymentProofCard, { props: { bookingId: 1 } });

            const label = screen
                .getByLabelText(/Klik untuk memilih file/i)
                .closest('label');
            const input = label?.querySelector('input');

            if (!input) {
                throw new Error('Input file tidak ditemukan');
            }

            const file = new File([''], 'large.jpg', { type: 'image/jpeg' });
            Object.defineProperty(file, 'size', { value: 6 * 1024 * 1024 });

            await fireEvent.change(input, { target: { files: [file] } });

            await waitFor(() => {
                const errorMessage = screen.getByRole('alert');
                expect(errorMessage).toHaveTextContent(
                    /Ukuran bukti transfer maksimal 5MB/i,
                );
            });
        });
    });
});
