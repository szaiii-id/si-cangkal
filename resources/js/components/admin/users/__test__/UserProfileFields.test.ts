import { render, screen, fireEvent } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import UserProfileFields from '../UserProfileFields.svelte';
import type { UserProfileFormData } from '@/types/models/user';

// ==========================================
// MOCK DATA
// ==========================================
const createMockForm = (
    overrides: Partial<UserProfileFormData> = {},
): UserProfileFormData => ({
    profile: {
        nama_penyedia_usaha: 'PT Travel Indah',
        nomor_identitas: '1234567890',
        telepon: '081234567890',
        alamat: 'Jl. Contoh No. 1',
        provinsi: 'Kalimantan Selatan',
        kota: 'Banjarmasin',
        kode_pos: '70123',
        deskripsi: 'Layanan travel terbaik.',
        ...overrides.profile,
    },
});

describe('UserProfileFields Component', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    // ==========================================
    // 1. RENDERING DASAR
    // ==========================================
    describe('Rendering', () => {
        it('harus merender input nama usaha dan nomor identitas', () => {
            render(UserProfileFields, { props: { form: createMockForm() } });

            expect(screen.getByLabelText('Nama Usaha')).toBeInTheDocument();
            expect(
                screen.getByLabelText('Nomor Identitas'),
            ).toBeInTheDocument();
        });

        it('harus merender input telepon, alamat, provinsi, kota, kode pos, deskripsi', () => {
            render(UserProfileFields, { props: { form: createMockForm() } });

            expect(screen.getByLabelText('Telepon')).toBeInTheDocument();
            expect(screen.getByLabelText('Alamat')).toBeInTheDocument();
            expect(screen.getByLabelText('Provinsi')).toBeInTheDocument();
            expect(screen.getByLabelText('Kota')).toBeInTheDocument();
            expect(screen.getByLabelText('Kode Pos')).toBeInTheDocument();
            expect(
                screen.getByLabelText('Deskripsi Usaha'),
            ).toBeInTheDocument();
        });

        it('harus menampilkan nilai awal dari props form', () => {
            render(UserProfileFields, { props: { form: createMockForm() } });

            const namaInput = screen.getByLabelText(
                'Nama Usaha',
            ) as HTMLInputElement;
            expect(namaInput.value).toBe('PT Travel Indah');

            const teleponInput = screen.getByLabelText(
                'Telepon',
            ) as HTMLInputElement;
            expect(teleponInput.value).toBe('081234567890');
        });
    });

    // ==========================================
    // 2. ERROR STATE
    // ==========================================
    describe('Error State', () => {
        it('harus menampilkan pesan error untuk nama usaha jika ada', () => {
            render(UserProfileFields, {
                props: {
                    form: createMockForm(),
                    errors: {
                        'profile.nama_penyedia_usaha': 'Nama usaha wajib diisi',
                    },
                },
            });

            expect(
                screen.getByText('Nama usaha wajib diisi'),
            ).toBeInTheDocument();
        });

        it('input harus memiliki aria-invalid=true jika error', () => {
            render(UserProfileFields, {
                props: {
                    form: createMockForm(),
                    errors: { 'profile.nama_penyedia_usaha': 'Error' },
                },
            });

            const namaInput = screen.getByLabelText('Nama Usaha');
            expect(namaInput).toHaveAttribute('aria-invalid', 'true');
        });
    });

    // ==========================================
    // 3. USER INTERACTION
    // ==========================================
    describe('User Interaction', () => {
        it('harus memperbarui nilai input saat user mengetik', async () => {
            render(UserProfileFields, { props: { form: createMockForm() } });

            const namaInput = screen.getByLabelText(
                'Nama Usaha',
            ) as HTMLInputElement;
            await fireEvent.input(namaInput, {
                target: { value: 'PT Travel Baru' },
            });

            expect(namaInput.value).toBe('PT Travel Baru');
        });
    });

    // ==========================================
    // 4. AKSESIBILITAS (A11Y)
    // ==========================================
    describe('Accessibility', () => {
        it('pesan error harus memiliki role="alert"', () => {
            render(UserProfileFields, {
                props: {
                    form: createMockForm(),
                    errors: {
                        'profile.nama_penyedia_usaha': 'Nama usaha wajib diisi',
                    },
                },
            });

            const errorMessage = screen.getByRole('alert');
            expect(errorMessage).toHaveTextContent('Nama usaha wajib diisi');
        });
    });
});
