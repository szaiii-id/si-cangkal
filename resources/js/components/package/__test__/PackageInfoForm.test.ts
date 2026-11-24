import { render, screen } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import PackageInfoForm from '../PackageInfoForm.svelte';
import type { PackageInfoFormData } from '@/types/models/package';

// ==========================================
// MOCK CHILD COMPONENTS (Mengarah ke file fisik)
// ==========================================
vi.mock(
    '@/global/InputText.svelte',
    () => import('@/global/__mocks__/InputText.svelte'),
);
vi.mock(
    '@/global/RupiahInput.svelte',
    () => import('@/global/__mocks__/RupiahInput.svelte'),
);
vi.mock(
    '@/global/ImageUploader.svelte',
    () => import('@/global/__mocks__/ImageUploader.svelte'),
);

// ==========================================
// MOCK DATA
// ==========================================
const createMockForm = (
    overrides: Partial<PackageInfoFormData> = {},
): PackageInfoFormData => ({
    nama: 'Paket Test',
    deskripsi: 'Deskripsi Test',
    kategori: 'wisata_alam',
    durasi_hari: 3,
    durasi_malam: 2,
    harga_per_orang: 500000,
    harga_per_rombongan: 0,
    max_peserta: 10,
    timezone: 'Asia/Makassar',
    ...overrides,
});

describe('PackageInfoForm Component', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    // ==========================================
    // 1. RENDERING DASAR
    // ==========================================
    describe('Rendering', () => {
        it('harus merender semua section form', () => {
            render(PackageInfoForm, { props: { form: createMockForm() } });

            expect(screen.getByText('Informasi Paket')).toBeInTheDocument();
            expect(screen.getByText('Durasi & Harga')).toBeInTheDocument();
            expect(screen.getByText('Foto Sampul')).toBeInTheDocument();
        });

        it('harus merender field nama, deskripsi, kategori, timezone', () => {
            render(PackageInfoForm, { props: { form: createMockForm() } });

            // Cek label dari komponen asli (deskripsi, kategori, timezone)
            expect(screen.getByLabelText('Deskripsi')).toBeInTheDocument();
            expect(screen.getByLabelText('Kategori')).toBeInTheDocument();
            expect(screen.getByLabelText('Zona Waktu')).toBeInTheDocument();

            // Cek label dari mock InputText (Nama Paket)
            expect(screen.getByLabelText('Nama Paket')).toBeInTheDocument();
        });
    });

    // ==========================================
    // 2. CONDITIONAL RENDERING (Harga)
    // ==========================================
    describe('Conditional Rendering', () => {
        it('harus menampilkan "Harga per Orang" jika useRombongan=false', () => {
            render(PackageInfoForm, {
                props: { form: createMockForm(), useRombongan: false },
            });

            // Label dari mock RupiahInput
            expect(screen.getByText('Harga per Orang')).toBeInTheDocument();
            expect(
                screen.queryByText('Harga Rombongan'),
            ).not.toBeInTheDocument();
        });

        it('harus menampilkan "Harga Rombongan" dan "Maksimal Peserta" jika useRombongan=true', () => {
            render(PackageInfoForm, {
                props: { form: createMockForm(), useRombongan: true },
            });

            // Label dari mock RupiahInput
            expect(screen.getByText('Harga Rombongan')).toBeInTheDocument();

            // Label asli
            expect(
                screen.getByLabelText('Maksimal Peserta'),
            ).toBeInTheDocument();
            expect(
                screen.queryByText('Harga per Orang'),
            ).not.toBeInTheDocument();
        });
    });

    // ==========================================
    // 3. ERROR STATE
    // ==========================================
    describe('Error State', () => {
        it('harus menampilkan pesan error jika ada', () => {
            render(PackageInfoForm, {
                props: {
                    form: createMockForm(),
                    errors: {
                        nama: 'Nama wajib diisi',
                        deskripsi: 'Deskripsi wajib diisi',
                    },
                },
            });

            // "Nama wajib diisi" dari mock InputText (data-testid="error-nama")
            expect(screen.getByTestId('error-nama')).toHaveTextContent(
                'Nama wajib diisi',
            );

            // "Deskripsi wajib diisi" dari komponen asli (role="alert")
            expect(
                screen.getByText('Deskripsi wajib diisi'),
            ).toBeInTheDocument();
        });
    });

    // ==========================================
    // 4. DISABLED MODE
    // ==========================================
    describe('Disabled Mode', () => {
        it('harus menonaktifkan input jika disabled=true', () => {
            render(PackageInfoForm, {
                props: { form: createMockForm(), disabled: true },
            });

            const deskripsiInput = screen.getByLabelText('Deskripsi');
            expect(deskripsiInput).toBeDisabled();

            const kategoriSelect = screen.getByLabelText('Kategori');
            expect(kategoriSelect).toBeDisabled();
        });
    });

    // ==========================================
    // 5. USER INTERACTION
    // ==========================================
    describe('User Interaction', () => {
        it('harus merender ImageUploader (meskipun di-mock)', () => {
            render(PackageInfoForm, {
                props: {
                    form: createMockForm(),
                    existingCover: ['url1'],
                },
            });

            expect(screen.getByTestId('image-uploader')).toBeInTheDocument();
        });
    });
});
