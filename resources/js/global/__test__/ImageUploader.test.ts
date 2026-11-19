import { render, screen, fireEvent, waitFor } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import ImageUploader from '../ImageUploader.svelte';

// ==========================================
// MOCK UTILS (Agar kita hanya menguji UI komponen)
// ==========================================
vi.mock('@/utils/fileUpload', () => ({
    validateImageFile: vi.fn((file) => {
        if (file.size > 2 * 1024 * 1024) return { valid: false, error: 'Ukuran file maksimal 2MB.' };
        return { valid: true, error: null };
    }),
    createImagePreview: vi.fn(async (file) => `data:image/png;base64,MOCK_PREVIEW_${file.name}`),
    formatFileSize: vi.fn((bytes) => `${(bytes / 1024).toFixed(1)} KB`),
}));

// Helper untuk membuat File palsu
function createMockFile(name: string, sizeInBytes: number, type: string): File {
    const file = new File([''], name, { type });
    Object.defineProperty(file, 'size', { value: sizeInBytes });
    return file;
}

describe('ImageUploader Component', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    // ==========================================
    // 1. RENDERING DASAR
    // ==========================================
    describe('Rendering', () => {
        it('harus menampilkan tombol upload jika belum ada gambar', () => {
            render(ImageUploader);
            expect(screen.getByTestId('logo-upload-button')).toBeInTheDocument();
        });

        it('harus menampilkan gambar existing jika existingUrls diberikan', () => {
            render(ImageUploader, {
                props: { existingUrls: ['https://example.com/image1.jpg'] },
            });
            expect(screen.getByTestId('logo-preview')).toHaveAttribute('src', 'https://example.com/image1.jpg');
        });

        it('harus menyembunyikan tombol upload jika sudah mencapai maxFiles', () => {
            render(ImageUploader, {
                props: { existingUrls: ['url1', 'url2'], maxFiles: 2 },
            });
            expect(screen.queryByTestId('logo-upload-button')).not.toBeInTheDocument();
        });
    });

    // ==========================================
    // 2. UPLOAD & PREVIEW
    // ==========================================
    describe('Upload & Preview', () => {
        it('harus memproses file yang diunggah dan menampilkan preview', async () => {
            const input = screen.getByTestId('logo-input');
            const file = createMockFile('test.png', 500 * 1024, 'image/png');

            await fireEvent.change(input, { target: { files: [file] } });

            // Tunggu efek async (createImagePreview) selesai
            await waitFor(() => {
                expect(screen.getByTestId('logo-preview')).toBeInTheDocument();
            });
        });

        it('harus menolak file yang melebihi batas ukuran', async () => {
            const input = screen.getByTestId('logo-input');
            const file = createMockFile('large.jpg', 3 * 1024 * 1024, 'image/jpeg'); // 3MB

            await fireEvent.change(input, { target: { files: [file] } });

            expect(screen.getByTestId('logo-error')).toHaveTextContent('Ukuran file maksimal 2MB.');
        });

        it('harus menolak jika jumlah file melebihi maxFiles', async () => {
            render(ImageUploader, {
                props: { existingUrls: ['url1'], maxFiles: 2 },
            });
            
            const input = screen.getByTestId('logo-input');
            const file1 = createMockFile('test1.png', 500 * 1024, 'image/png');
            const file2 = createMockFile('test2.png', 500 * 1024, 'image/png');

            await fireEvent.change(input, { target: { files: [file1, file2] } });

            expect(screen.getByTestId('logo-error')).toHaveTextContent(/Maksimal 2 file/);
        });
    });

    // ==========================================
    // 3. REMOVE FILE
    // ==========================================
    describe('Remove Actions', () => {
        it('harus memanggil onRemoveExisting saat tombol hapus (existing) diklik', async () => {
            const onRemoveExisting = vi.fn();
            render(ImageUploader, {
                props: { existingUrls: ['url1'], onRemoveExisting },
            });

            const removeBtn = screen.getByTestId('logo-remove-button');
            await fireEvent.click(removeBtn);

            expect(onRemoveExisting).toHaveBeenCalledWith('url1', 0);
        });

        it('harus menghapus file baru dari daftar saat tombol hapus diklik', async () => {
            render(ImageUploader, { props: { maxFiles: 2 } });
            const input = screen.getByTestId('logo-input');
            const file = createMockFile('test.png', 500 * 1024, 'image/png');

            await fireEvent.change(input, { target: { files: [file] } });
            await waitFor(() => expect(screen.getByTestId('logo-preview')).toBeInTheDocument());

            const removeBtn = screen.getByTestId('logo-remove-button');
            await fireEvent.click(removeBtn);

            await waitFor(() => {
                expect(screen.queryByTestId('logo-preview')).not.toBeInTheDocument();
            });
        });
    });

    // ==========================================
    // 4. STATE DISABLED
    // ==========================================
    describe('Disabled State', () => {
        it('harus menyembunyikan tombol hapus jika disabled=true', () => {
            render(ImageUploader, {
                props: { existingUrls: ['url1'], disabled: true },
            });

            expect(screen.queryByTestId('logo-remove-button')).not.toBeInTheDocument();
        });
    });
});