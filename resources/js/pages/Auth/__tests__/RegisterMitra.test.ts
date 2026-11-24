import { render, screen, fireEvent } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { tick } from 'svelte';
import RegisterMitra from '../RegisterMitra.svelte';

// ==========================================
// MOCK INERTIA
// ==========================================
const mockPage = vi.hoisted(() => ({
    props: {
        flash: {
            error: null as string | null,
            retry_after: null as number | null,
        },
    },
}));

vi.mock('@inertiajs/svelte', () => ({
    page: mockPage,
    inertia: vi.fn(),
}));

// ==========================================
// MOCK API AUTH
// ==========================================
vi.mock('@/api/auth', () => ({
    registerMitra: vi.fn(),
}));

// ==========================================
// MOCK CHILD COMPONENT (Mengarah ke file fisik)
// ==========================================
vi.mock(
    '@/components/ui/PasswordChecklist.svelte',
    () => import('@/components/ui/__mocks__/PasswordChecklist.svelte'),
);

import { registerMitra } from '@/api/auth';

async function fillValidForm() {
    await fireEvent.input(screen.getByLabelText('Nama Lengkap'), {
        target: { value: 'Budi Santoso' },
    });
    await fireEvent.input(screen.getByLabelText('Username'), {
        target: { value: 'budisantoso' },
    });
    await fireEvent.input(screen.getByLabelText('Email'), {
        target: { value: 'budi@test.com' },
    });
    await fireEvent.input(screen.getByLabelText('Password'), {
        target: { value: 'Password123!' },
    });
    await fireEvent.input(screen.getByLabelText('Konfirmasi Password'), {
        target: { value: 'Password123!' },
    });
}

describe('RegisterMitra Page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        mockPage.props.flash = { error: null, retry_after: null };
        vi.useFakeTimers();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    // ==========================================
    // 1. RENDERING DASAR
    // ==========================================
    describe('Rendering', () => {
        it('harus merender judul "Daftar Mitra" dan seluruh field form', () => {
            render(RegisterMitra);

            expect(
                screen.getByRole('heading', { name: /Daftar Mitra/i }),
            ).toBeInTheDocument();
            expect(screen.getByLabelText('Nama Lengkap')).toBeInTheDocument();
            expect(screen.getByLabelText('Username')).toBeInTheDocument();
            expect(screen.getByLabelText('Email')).toBeInTheDocument();
            expect(screen.getByLabelText('Password')).toBeInTheDocument();
            expect(
                screen.getByLabelText('Konfirmasi Password'),
            ).toBeInTheDocument();
        });

        it('harus menampilkan 3 tautan consent: Syarat, Privasi, Aturan Mitra', () => {
            render(RegisterMitra);

            expect(
                screen.getByRole('link', { name: /Syarat & Ketentuan/i }),
            ).toHaveAttribute('href', '/legal/terms');
            expect(
                screen.getByRole('link', { name: /Kebijakan Privasi/i }),
            ).toHaveAttribute('href', '/legal/privacy');
            expect(
                screen.getByRole('link', { name: /Aturan Mitra/i }),
            ).toHaveAttribute('href', '/legal/mitra-rules');
        });

        it('harus menampilkan tautan "Daftar sebagai Client"', () => {
            render(RegisterMitra);

            expect(
                screen.getByRole('link', { name: /Daftar sebagai Client/i }),
            ).toHaveAttribute('href', '/register');
        });

        it('tombol submit harus disabled di awal dan berlabel "Buat Akun Mitra"', () => {
            render(RegisterMitra);

            const submitBtn = screen.getByRole('button', {
                name: /Buat Akun Mitra/i,
            });
            expect(submitBtn).toBeDisabled();
        });
    });

    // ==========================================
    // 2. VALIDASI CONSENT
    // ==========================================
    describe('Consent Validation', () => {
        it('TIDAK boleh memanggil registerMitra jika checkbox consent tidak dicentang', async () => {
            render(RegisterMitra);
            await fillValidForm();

            await fireEvent.submit(document.querySelector('form')!);

            expect(registerMitra).not.toHaveBeenCalled();
            expect(screen.getByRole('alert')).toHaveTextContent(
                'Anda harus menyetujui Syarat & Ketentuan.',
            );
        });

        it('harus memanggil registerMitra jika consent dicentang dan form valid', async () => {
            render(RegisterMitra);
            await fillValidForm();
            await fireEvent.click(
                screen.getByLabelText(/Saya telah membaca dan menyetujui/i),
            );

            await fireEvent.submit(document.querySelector('form')!);

            expect(registerMitra).toHaveBeenCalledWith(
                {
                    name: 'Budi Santoso',
                    username: 'budisantoso',
                    email: 'budi@test.com',
                    password: 'Password123!',
                    password_confirmation: 'Password123!',
                },
                expect.any(Object),
            );
        });
    });

    // ==========================================
    // 3. PASSWORD MISMATCH
    // ==========================================
    describe('Password Mismatch', () => {
        it('harus menampilkan error dan mengunci tombol jika password tidak cocok', async () => {
            render(RegisterMitra);

            await fireEvent.input(screen.getByLabelText('Password'), {
                target: { value: 'Password123!' },
            });
            await fireEvent.input(
                screen.getByLabelText('Konfirmasi Password'),
                {
                    target: { value: 'Berbeda123!' },
                },
            );
            await fireEvent.click(
                screen.getByLabelText(/Saya telah membaca dan menyetujui/i),
            );

            expect(screen.getByRole('alert')).toHaveTextContent(
                'Konfirmasi password tidak cocok.',
            );
            expect(
                screen.getByRole('button', { name: /Buat Akun Mitra/i }),
            ).toBeDisabled();
        });
    });

    // ==========================================
    // 4. LOADING STATE
    // ==========================================
    describe('Loading State', () => {
        it('harus menampilkan "Memproses..." dan menonaktifkan tombol saat processing', async () => {
            vi.mocked(registerMitra).mockImplementation(() => {});

            render(RegisterMitra);
            await fillValidForm();
            await fireEvent.click(
                screen.getByLabelText(/Saya telah membaca dan menyetujui/i),
            );
            await fireEvent.submit(document.querySelector('form')!);

            expect(
                screen.getByRole('button', { name: /Memproses/i }),
            ).toBeDisabled();
        });
    });

    // ==========================================
    // 5. ERROR DARI BACKEND
    // ==========================================
    describe('Backend Error', () => {
        it('harus menampilkan error dari backend per field', async () => {
            vi.mocked(registerMitra).mockImplementation((_data, options) => {
                options?.onError?.({
                    name: 'Nama wajib diisi',
                    username: 'Username sudah digunakan',
                    email: 'Email tidak valid',
                });
                options?.onFinish?.({} as any);
            });

            render(RegisterMitra);
            await fillValidForm();
            await fireEvent.click(
                screen.getByLabelText(/Saya telah membaca dan menyetujui/i),
            );
            await fireEvent.submit(document.querySelector('form')!);

            const alerts = screen.getAllByRole('alert');
            expect(alerts).toHaveLength(3);
            expect(alerts[0]).toHaveTextContent('Nama wajib diisi');
            expect(alerts[1]).toHaveTextContent('Username sudah digunakan');
            expect(alerts[2]).toHaveTextContent('Email tidak valid');
        });

        it('harus menampilkan flash error global', () => {
            mockPage.props.flash = {
                error: 'Registrasi mitra sedang ditutup',
                retry_after: null,
            };
            render(RegisterMitra);

            expect(screen.getByRole('alert')).toHaveTextContent(
                'Registrasi mitra sedang ditutup',
            );
        });
    });

    // ==========================================
    // 6. COUNTDOWN (RATE LIMIT)
    // ==========================================
    describe('Countdown (Rate Limit)', () => {
        it('harus mengunci tombol selama hitung mundur dan mengaktifkannya kembali setelah selesai', async () => {
            mockPage.props.flash = { error: null, retry_after: 3 };
            render(RegisterMitra);
            await fillValidForm();
            await fireEvent.click(
                screen.getByLabelText(/Saya telah membaca dan menyetujui/i),
            );
            await tick();

            const submitBtn = screen.getByRole('button', {
                name: /Tunggu 3 detik/i,
            });
            expect(submitBtn).toBeDisabled();

            await vi.advanceTimersByTimeAsync(1000);
            expect(submitBtn).toHaveTextContent('Tunggu 2 detik...');

            await vi.advanceTimersByTimeAsync(2000);
            expect(submitBtn).toHaveTextContent('Buat Akun Mitra');
            expect(submitBtn).toBeEnabled();
        });
    });

    // ==========================================
    // 7. AKSESIBILITAS
    // ==========================================
    describe('Accessibility', () => {
        it('semua ikon SVG dekoratif harus memiliki aria-hidden="true"', () => {
            const { container } = render(RegisterMitra);
            const svgs = container.querySelectorAll('svg');

            expect(svgs.length).toBeGreaterThan(0);
            svgs.forEach((svg) => {
                expect(svg).toHaveAttribute('aria-hidden', 'true');
            });
        });
    });
});
