import { render, screen, fireEvent, waitFor } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import ProfileInfoForm from '../ProfileInfoForm.svelte';

// ==========================================
// MOCK CHILD COMPONENT (ImageUploader)
// ==========================================
vi.mock('@/global/ImageUploader.svelte', () => import('@/global/__mocks__/ImageUploader.svelte'));

// ==========================================
// MOCK API & TOAST
// ==========================================
vi.mock('@/api/mitra', () => ({
    updateProfile: vi.fn(),
}));

vi.mock('@/utils/toast.svelte', () => ({
    showToast: vi.fn(),
}));

// ==========================================
// MOCK INERTIA
// ==========================================
const mockPage = vi.hoisted(() => ({
    props: {
        auth: { user: { id: 1, name: 'Budi', email: 'budi@test.com' } },
    },
}));

vi.mock('@inertiajs/svelte', () => ({
    page: mockPage,
    inertia: vi.fn(),
}));

import { updateProfile } from '@/api/mitra';

describe('ProfileInfoForm Component', () => {
    const mockProfile = {
        id: 1,
        nama_penyedia_usaha: 'PT Travel Indah',
        nomor_identitas: '1234567890',
        alamat: 'Jl. Contoh No. 1',
        telepon: '081234567890',
        provinsi: 'Bali',
        kota: 'Denpasar',
        kode_pos: '80119',
        logo: 'logo.jpg',
        deskripsi: 'Layanan travel terbaik.',
    };

    beforeEach(() => {
        vi.clearAllMocks();
    });

    // ==========================================
    // 1. RENDERING DASAR
    // ==========================================
    describe('Rendering', () => {
        it('harus merender judul dan ImageUploader', () => {
            render(ProfileInfoForm, { props: { profile: mockProfile } });

            expect(screen.getByText('Informasi Usaha')).toBeInTheDocument();
            expect(screen.getByTestId('image-uploader')).toBeInTheDocument();
        });

        it('harus merender semua field form', () => {
            render(ProfileInfoForm, { props: { profile: mockProfile } });

            expect(screen.getByLabelText('Nama Usaha')).toBeInTheDocument();
            expect(screen.getByLabelText('Nomor Identitas')).toBeInTheDocument();
            expect(screen.getByLabelText('Telepon')).toBeInTheDocument();
            expect(screen.getByLabelText('Kode Pos')).toBeInTheDocument();
            expect(screen.getByLabelText('Alamat')).toBeInTheDocument();
            expect(screen.getByLabelText('Provinsi')).toBeInTheDocument();
            expect(screen.getByLabelText('Kota')).toBeInTheDocument();
            expect(screen.getByLabelText('Deskripsi Usaha')).toBeInTheDocument();
        });

        it('harus menampilkan nilai awal dari props profile', () => {
            render(ProfileInfoForm, { props: { profile: mockProfile } });

            const namaInput = screen.getByLabelText('Nama Usaha') as HTMLInputElement;
            expect(namaInput.value).toBe('PT Travel Indah');
        });
    });

    // ==========================================
    // 2. ERROR STATE (via mock API)
    // ==========================================
    describe('Error State', () => {
        it('harus menampilkan pesan error jika API mengembalikan error', async () => {
            // Mock updateProfile untuk memicu onError
            (updateProfile as any).mockImplementation((_data: any, options: any) => {
                options.onError?.({ nama_penyedia_usaha: 'Nama usaha wajib diisi' });
                options.onFinish?.();
            });

            render(ProfileInfoForm, { props: { profile: mockProfile } });

            const form = document.querySelector('form');
            await fireEvent.submit(form!);

            await waitFor(() => {
                expect(screen.getByText('Nama usaha wajib diisi')).toBeInTheDocument();
            });
        });
    });

    // ==========================================
    // 3. SUBMIT FORM
    // ==========================================
    describe('Form Submission', () => {
        it('harus memanggil updateProfile saat form disubmit', async () => {
            render(ProfileInfoForm, { props: { profile: mockProfile } });

            const form = document.querySelector('form');
            await fireEvent.submit(form!);

            expect(updateProfile).toHaveBeenCalledWith(
                expect.any(FormData),
                expect.any(Object)
            );
        });
    });

    // ==========================================
    // 4. AKSESIBILITAS (A11Y)
    // ==========================================
    describe('Accessibility', () => {
        it('ikon SVG dekoratif harus disembunyikan dari screen reader', () => {
            const { container } = render(ProfileInfoForm, { props: { profile: mockProfile } });
            
            const svgs = container.querySelectorAll('svg');
            svgs.forEach((svg) => {
                expect(svg).toHaveAttribute('aria-hidden', 'true');
            });
        });
    });
});