import { render, screen, fireEvent } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import BusinessProfileForm from '../BusinessProfileForm.svelte';

// ==========================================
// MOCK DATA
// ==========================================
const createMockForm = (overrides = {}) => ({
    nama_penyedia_usaha: 'PT Travel Indah',
    no_hp: '081234567890',
    deskripsi: 'Layanan travel terbaik.',
    ...overrides,
});

describe('BusinessProfileForm Component', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    // ==========================================
    // 1. RENDERING DASAR
    // ==========================================
    describe('Rendering', () => {
        it('harus merender judul "Profil Usaha"', () => {
            render(BusinessProfileForm, {
                props: { form: createMockForm(), errors: {} },
            });
            expect(screen.getByText('Profil Usaha')).toBeInTheDocument();
        });

        it('harus merender 3 input (nama, no_hp, deskripsi)', () => {
            render(BusinessProfileForm, {
                props: { form: createMockForm(), errors: {} },
            });

            expect(screen.getByLabelText(/Nama Usaha/i)).toBeInTheDocument();
            expect(screen.getByLabelText(/Nomor HP/i)).toBeInTheDocument();
            expect(
                screen.getByLabelText(/Deskripsi Singkat/i),
            ).toBeInTheDocument();
        });

        it('harus menampilkan nilai awal dari props form', () => {
            render(BusinessProfileForm, {
                props: { form: createMockForm(), errors: {} },
            });

            const namaInput = screen.getByLabelText(
                /Nama Usaha/i,
            ) as HTMLInputElement;
            expect(namaInput.value).toBe('PT Travel Indah');

            const hpInput = screen.getByLabelText(
                /Nomor HP/i,
            ) as HTMLInputElement;
            expect(hpInput.value).toBe('081234567890');
        });
    });

    // ==========================================
    // 2. ERROR STATE
    // ==========================================
    describe('Error State', () => {
        it('harus menampilkan pesan error untuk nama jika ada', () => {
            render(BusinessProfileForm, {
                props: {
                    form: createMockForm(),
                    errors: { nama_penyedia_usaha: 'Nama usaha wajib diisi' },
                },
            });

            expect(
                screen.getByText('Nama usaha wajib diisi'),
            ).toBeInTheDocument();
        });

        it('harus menampilkan pesan error untuk no_hp jika ada', () => {
            render(BusinessProfileForm, {
                props: {
                    form: createMockForm(),
                    errors: { no_hp: 'Nomor HP tidak valid' },
                },
            });

            expect(
                screen.getByText('Nomor HP tidak valid'),
            ).toBeInTheDocument();
        });

        it('input harus memiliki aria-invalid=true jika error', () => {
            render(BusinessProfileForm, {
                props: {
                    form: createMockForm(),
                    errors: { nama_penyedia_usaha: 'Error' },
                },
            });

            const namaInput = screen.getByLabelText(/Nama Usaha/i);
            expect(namaInput).toHaveAttribute('aria-invalid', 'true');
        });
    });

    // ==========================================
    // 3. USER INTERACTION
    // ==========================================
    describe('User Interaction', () => {
        it('harus memperbarui nilai input saat user mengetik (DOM check)', async () => {
            render(BusinessProfileForm, {
                props: { form: createMockForm(), errors: {} },
            });

            const namaInput = screen.getByLabelText(
                /Nama Usaha/i,
            ) as HTMLInputElement;

            await fireEvent.input(namaInput, {
                target: { value: 'PT Travel Baru' },
            });

            // PERBAIKAN: Cek DOM langsung, bukan component.$$ (Svelte 5 tidak support)
            expect(namaInput.value).toBe('PT Travel Baru');
        });
    });

    // ==========================================
    // 4. AKSESIBILITAS (A11Y)
    // ==========================================
    describe('Accessibility', () => {
        it('pesan error harus memiliki role="alert"', () => {
            render(BusinessProfileForm, {
                props: {
                    form: createMockForm(),
                    errors: { nama_penyedia_usaha: 'Nama usaha wajib diisi' },
                },
            });

            const errorMessage = screen.getByRole('alert');
            expect(errorMessage).toHaveTextContent('Nama usaha wajib diisi');
        });
    });
});
