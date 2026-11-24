import { describe, it, expect, vi } from 'vitest';
import {
    validateImageFile,
    validateMultipleImageFiles,
    createImagePreview,
    formatFileSize,
} from '../fileUpload';

/**
 * Helper untuk membuat File palsu di lingkungan test.
 */
function createMockFile(name: string, sizeInBytes: number, type: string): File {
    const file = new File([''], name, { type });
    Object.defineProperty(file, 'size', { value: sizeInBytes });
    return file;
}

describe('File Upload Utilities', () => {
    // ==========================================
    // 1. formatFileSize()
    // ==========================================
    describe('formatFileSize()', () => {
        it('harus memformat bytes ke B, KB, MB, GB', () => {
            expect(formatFileSize(0)).toBe('0 B');
            expect(formatFileSize(500)).toBe('500 B');
            expect(formatFileSize(1024)).toBe('1.0 KB');
            expect(formatFileSize(1024 * 1024)).toBe('1.0 MB');
        });

        it('harus menangani input invalid', () => {
            expect(formatFileSize(-100)).toBe('-');
            expect(formatFileSize(NaN)).toBe('-');
        });
    });

    // ==========================================
    // 2. validateImageFile()
    // ==========================================
    describe('validateImageFile()', () => {
        it('harus gagal jika file null', () => {
            expect(validateImageFile(null)).toEqual({
                valid: false,
                error: 'File tidak dipilih.',
            });
        });

        it('harus lolos untuk JPG < 2MB', () => {
            const file = createMockFile(
                'photo.jpg',
                1 * 1024 * 1024,
                'image/jpeg',
            );
            expect(validateImageFile(file)).toEqual({
                valid: true,
                error: null,
            });
        });

        it('harus gagal jika ukuran melebihi batas', () => {
            const file = createMockFile(
                'photo.jpg',
                3 * 1024 * 1024,
                'image/jpeg',
            );
            expect(validateImageFile(file).error).toBe(
                'Ukuran file maksimal 2MB.',
            );
        });

        it('harus gagal jika tipe MIME tidak diizinkan', () => {
            const file = createMockFile(
                'doc.pdf',
                500 * 1024,
                'application/pdf',
            );
            expect(validateImageFile(file).error).toContain(
                'Format file tidak didukung',
            );
        });

        it('harus mendahulukan pesan ukuran jika file kebesaran DAN tipe salah', () => {
            const file = createMockFile(
                'doc.pdf',
                3 * 1024 * 1024,
                'application/pdf',
            );
            expect(validateImageFile(file).error).toBe(
                'Ukuran file maksimal 2MB.',
            );
        });

        it('harus mendukung custom options (maxSizeMB dan label)', () => {
            const file = createMockFile('logo.png', 600 * 1024, 'image/png');
            const result = validateImageFile(file, {
                maxSizeMB: 0.5,
                label: 'logo',
            });
            expect(result.error).toBe('Ukuran logo maksimal 0.5MB.');
        });
    });

    // ==========================================
    // 3. validateMultipleImageFiles()
    // ==========================================
    describe('validateMultipleImageFiles()', () => {
        it('harus gagal jika array kosong', () => {
            const result = validateMultipleImageFiles([]);
            expect(result.errors).toContain('Minimal 1 file wajib dipilih.');
        });

        it('harus lolos jika semua file valid', () => {
            const files = [
                createMockFile('1.jpg', 500 * 1024, 'image/jpeg'),
                createMockFile('2.png', 500 * 1024, 'image/png'),
            ];
            const result = validateMultipleImageFiles(files);
            expect(result.valid).toBe(true);
        });

        it('harus gagal jika melebihi maxCount', () => {
            const files = [
                createMockFile('1.jpg', 100, 'image/jpeg'),
                createMockFile('2.jpg', 100, 'image/jpeg'),
                createMockFile('3.jpg', 100, 'image/jpeg'),
            ];
            const result = validateMultipleImageFiles(files, { maxCount: 2 });
            expect(result.errors).toContain('Maksimal 2 file.');
        });

        // --- PERBAIKAN TOTAL: TANPA DataTransfer ---
        it('harus bisa menerima FileList (seperti input HTML)', () => {
            const file = createMockFile('1.jpg', 100, 'image/jpeg');

            // Membuat objek tiruan yang menyerupai FileList HTML
            const fakeFileList: FileList = {
                0: file,
                length: 1,
                item: (index: number) => (index === 0 ? file : null),
                [Symbol.iterator]: function* () {
                    yield file;
                },
            } as unknown as FileList;

            const result = validateMultipleImageFiles(fakeFileList);
            expect(result.valid).toBe(true);
        });
    });

    // ==========================================
    // 4. createImagePreview()
    // ==========================================
    describe('createImagePreview()', () => {
        it('harus menghasilkan data URL (base64) dari file', async () => {
            const mockDataUrl = 'data:image/png;base64,mockbase64string';

            // Mock FileReader dengan Class (bukan object literal)
            // agar 'this' di dalam method mengacu pada instance.
            class MockFileReader {
                result: string | null = mockDataUrl;
                onload: (() => void) | null = null;
                onerror: (() => void) | null = null;

                readAsDataURL() {
                    if (this.onload) this.onload();
                }
            }

            vi.stubGlobal('FileReader', MockFileReader);

            const file = createMockFile('test.png', 100, 'image/png');
            const preview = await createImagePreview(file);

            expect(preview).toBe(mockDataUrl);
        });
    });
});
