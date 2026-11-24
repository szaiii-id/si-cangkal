import { render, screen, fireEvent } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import Contact from '../Contact.svelte';

// ==========================================
// MOCK INERTIA (useForm & page)
// ==========================================
vi.mock('@inertiajs/svelte', () => ({
    useForm: vi.fn(() => ({
        nama: '',
        email: '',
        pesan: '',
        errors: {},
        processing: false,
        post: vi.fn(),
        reset: vi.fn(),
    })),
    page: {
        props: {
            flash: { status: null, error: null },
        },
    },
}));

import { useForm } from '@inertiajs/svelte';

describe('Contact Component', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    // ==========================================
    // 1. RENDERING DASAR
    // ==========================================
    describe('Rendering', () => {
        it('harus merender judul dan deskripsi', () => {
            render(Contact);

            expect(screen.getByRole('heading', { level: 2 })).toHaveTextContent(
                /Punya Pertanyaan/i,
            );
            expect(
                screen.getByText(/Tim SI-CANGKAL siap membantu/i),
            ).toBeInTheDocument();
        });

        it('harus merender formulir dengan 3 input (nama, email, pesan)', () => {
            render(Contact);

            expect(screen.getByLabelText('Nama Lengkap')).toBeInTheDocument();
            expect(screen.getByLabelText('Alamat Email')).toBeInTheDocument();
            expect(
                screen.getByLabelText('Pesan atau Keterangan'),
            ).toBeInTheDocument();
        });

        it('harus merender informasi kontak di kolom kiri', () => {
            render(Contact);

            expect(
                screen.getByText(
                    'Kertak Hanyar, Kalimantan Selatan, Indonesia',
                ),
            ).toBeInTheDocument();
            expect(screen.getByText('halo@si-cangkal.id')).toBeInTheDocument();
            expect(screen.getByText('+62 811 2233 4455')).toBeInTheDocument();
        });
    });

    // ==========================================
    // 2. SUBMIT FORM
    // ==========================================
    describe('Form Submission', () => {
        it('harus memanggil form.post saat form disubmit', async () => {
            const postMock = vi.fn();
            (useForm as any).mockReturnValue({
                nama: 'Budi',
                email: 'budi@test.com',
                pesan: 'Halo, saya ingin bertanya.',
                errors: {},
                processing: false,
                post: postMock,
                reset: vi.fn(),
            });

            const { container } = render(Contact);

            // PERBAIKAN: Menggunakan querySelector karena <form> tidak memiliki role="form"
            // kecuali jika diberi aria-label.
            const form = container.querySelector('form');
            expect(form).toBeInTheDocument();

            await fireEvent.submit(form!);

            expect(postMock).toHaveBeenCalledWith(
                '/contact',
                expect.any(Object),
            );
        });
    });

    // ==========================================
    // 3. ERROR STATE
    // ==========================================
    describe('Error State', () => {
        it('harus menampilkan pesan error jika ada', () => {
            (useForm as any).mockReturnValue({
                nama: '',
                email: '',
                pesan: '',
                errors: { nama: 'Nama wajib diisi' },
                processing: false,
                post: vi.fn(),
                reset: vi.fn(),
            });

            render(Contact);

            expect(screen.getByText('Nama wajib diisi')).toBeInTheDocument();
        });

        it('input harus memiliki aria-invalid=true jika error', () => {
            (useForm as any).mockReturnValue({
                nama: '',
                email: '',
                pesan: '',
                errors: { email: 'Email tidak valid' },
                processing: false,
                post: vi.fn(),
                reset: vi.fn(),
            });

            render(Contact);

            const emailInput = screen.getByLabelText('Alamat Email');
            expect(emailInput).toHaveAttribute('aria-invalid', 'true');
        });
    });

    // ==========================================
    // 4. LOADING STATE
    // ==========================================
    describe('Loading State', () => {
        it('harus menonaktifkan tombol dan menampilkan spinner saat processing', () => {
            (useForm as any).mockReturnValue({
                nama: '',
                email: '',
                pesan: '',
                errors: {},
                processing: true,
                post: vi.fn(),
                reset: vi.fn(),
            });

            render(Contact);

            const btn = screen.getByRole('button', { name: /Mengirim/i });
            expect(btn).toBeDisabled();
        });
    });

    // ==========================================
    // 5. AKSESIBILITAS (A11Y)
    // ==========================================
    describe('Accessibility', () => {
        it('label harus terhubung dengan input (for & id)', () => {
            render(Contact);

            const namaInput = screen.getByLabelText('Nama Lengkap');
            expect(namaInput).toHaveAttribute('id', 'nama');

            const emailInput = screen.getByLabelText('Alamat Email');
            expect(emailInput).toHaveAttribute('id', 'email');

            const pesanInput = screen.getByLabelText('Pesan atau Keterangan');
            expect(pesanInput).toHaveAttribute('id', 'pesan');
        });
    });
});
