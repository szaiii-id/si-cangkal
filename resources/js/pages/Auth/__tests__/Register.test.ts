import { render, screen, fireEvent } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { tick } from 'svelte';
import Register from '../Register.svelte';

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
    register: vi.fn(),
}));

// ==========================================
// MOCK CHILD COMPONENT (Mengarah ke file fisik)
// ==========================================
vi.mock(
    '@/components/ui/PasswordChecklist.svelte',
    () => import('@/components/ui/__mocks__/PasswordChecklist.svelte'),
);

import { register } from '@/api/auth';

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

describe('Register Page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        mockPage.props.flash = { error: null, retry_after: null };
        vi.useFakeTimers();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    describe('Rendering', () => {
        it('harus merender seluruh field form, tombol disabled di awal, dan tautan navigasi', () => {
            render(Register);

            expect(
                screen.getByRole('heading', { name: /Daftar Akun/i }),
            ).toBeInTheDocument();
            expect(screen.getByLabelText('Nama Lengkap')).toBeInTheDocument();
            expect(screen.getByLabelText('Username')).toBeInTheDocument();
            expect(screen.getByLabelText('Email')).toBeInTheDocument();
            expect(screen.getByLabelText('Password')).toBeInTheDocument();
            expect(
                screen.getByLabelText('Konfirmasi Password'),
            ).toBeInTheDocument();
            expect(
                screen.getByLabelText(/Saya telah membaca dan menyetujui/i),
            ).not.toBeChecked();

            expect(
                screen.getByRole('button', { name: /Buat Akun/i }),
            ).toBeDisabled();

            expect(
                screen.getByRole('link', { name: /Syarat & Ketentuan/i }),
            ).toHaveAttribute('href', '/legal/terms');
            expect(
                screen.getByRole('link', { name: /Kebijakan Privasi/i }),
            ).toHaveAttribute('href', '/legal/privacy');
            expect(
                screen.getByRole('link', { name: /Masuk sekarang/i }),
            ).toHaveAttribute('href', '/login');
            expect(
                screen.getByRole('link', { name: /Google/i }),
            ).toHaveAttribute('href', '/auth/google');
        });
    });

    describe('Validation & Reactivity', () => {
        it('harus menampilkan PasswordChecklist saat password mulai diisi', async () => {
            render(Register);

            expect(
                screen.queryByTestId('password-checklist'),
            ).not.toBeInTheDocument();

            await fireEvent.input(screen.getByLabelText('Password'), {
                target: { value: 'Pass' },
            });

            expect(
                screen.getByTestId('password-checklist'),
            ).toBeInTheDocument();
        });

        it('harus menampilkan error dan mengunci tombol jika konfirmasi password tidak cocok', async () => {
            render(Register);

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
                screen.getByRole('button', { name: /Buat Akun/i }),
            ).toBeDisabled();
        });

        it('harus menampilkan error dan tidak memanggil register jika form disubmit tanpa menyetujui syarat', async () => {
            render(Register);
            await fillValidForm();

            await fireEvent.submit(document.querySelector('form')!);

            expect(screen.getByRole('alert')).toHaveTextContent(
                'Anda harus menyetujui Syarat & Ketentuan.',
            );
            expect(register).not.toHaveBeenCalled();
        });
    });

    describe('User Interaction', () => {
        it('harus mengaktifkan tombol setelah syarat disetujui dan mengirim payload lengkap saat submit', async () => {
            render(Register);
            await fillValidForm();

            const consentCheckbox = screen.getByLabelText(
                /Saya telah membaca dan menyetujui/i,
            );
            await fireEvent.click(consentCheckbox);

            const submitBtn = screen.getByRole('button', {
                name: /Buat Akun/i,
            });
            expect(submitBtn).toBeEnabled();

            await fireEvent.submit(document.querySelector('form')!);

            expect(register).toHaveBeenCalledTimes(1);
            expect(register).toHaveBeenCalledWith(
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

        it('harus toggle visibilitas password dan konfirmasi password secara independen', async () => {
            render(Register);

            const passwordInput = screen.getByLabelText(
                'Password',
            ) as HTMLInputElement;
            const confirmInput = screen.getByLabelText(
                'Konfirmasi Password',
            ) as HTMLInputElement;

            expect(passwordInput.type).toBe('password');
            expect(confirmInput.type).toBe('password');

            await fireEvent.click(
                screen.getByRole('button', { name: 'Tampilkan password' }),
            );
            expect(passwordInput.type).toBe('text');
            expect(confirmInput.type).toBe('password');

            await fireEvent.click(
                screen.getByRole('button', {
                    name: 'Tampilkan konfirmasi password',
                }),
            );
            expect(confirmInput.type).toBe('text');

            await fireEvent.click(
                screen.getByRole('button', { name: 'Sembunyikan password' }),
            );
            expect(passwordInput.type).toBe('password');
        });
    });

    describe('Loading, Error & Flash States', () => {
        it('harus menonaktifkan tombol dan menampilkan indikator saat proses registrasi berjalan', async () => {
            vi.mocked(register).mockImplementation(() => {});

            render(Register);
            await fillValidForm();
            await fireEvent.click(
                screen.getByLabelText(/Saya telah membaca dan menyetujui/i),
            );
            await fireEvent.submit(document.querySelector('form')!);

            expect(
                screen.getByRole('button', { name: /Memproses/i }),
            ).toBeDisabled();
        });

        it('harus menampilkan error validasi dari backend untuk setiap field', async () => {
            vi.mocked(register).mockImplementation((_data, options) => {
                options?.onError?.({
                    name: 'Nama wajib diisi',
                    username: 'Username sudah digunakan',
                    email: 'Email tidak valid',
                    password: 'Password terlalu lemah',
                    password_confirmation: 'Konfirmasi gagal',
                });
                options?.onFinish?.({} as any);
            });

            render(Register);
            await fillValidForm();
            await fireEvent.click(
                screen.getByLabelText(/Saya telah membaca dan menyetujui/i),
            );
            await fireEvent.submit(document.querySelector('form')!);

            const alerts = screen.getAllByRole('alert');
            expect(alerts).toHaveLength(5);
            expect(alerts[0]).toHaveTextContent('Nama wajib diisi');
            expect(alerts[1]).toHaveTextContent('Username sudah digunakan');
            expect(alerts[2]).toHaveTextContent('Email tidak valid');
            expect(alerts[3]).toHaveTextContent('Password terlalu lemah');
            expect(alerts[4]).toHaveTextContent('Konfirmasi gagal');
        });

        it('harus menampilkan flash error global', () => {
            mockPage.props.flash = {
                error: 'Registrasi sedang ditutup',
                retry_after: null,
            };
            render(Register);

            expect(screen.getByRole('alert')).toHaveTextContent(
                'Registrasi sedang ditutup',
            );
        });
    });

    describe('Countdown (Rate Limit)', () => {
        it('harus mengunci tombol selama hitung mundur dan mengaktifkannya kembali setelah selesai', async () => {
            mockPage.props.flash = { error: null, retry_after: 3 };
            render(Register);
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
            expect(submitBtn).toHaveTextContent('Tunggu 2 detik…');

            await vi.advanceTimersByTimeAsync(2000);
            expect(submitBtn).toHaveTextContent('Buat Akun');
            expect(submitBtn).toBeEnabled();
        });
    });

    describe('Accessibility', () => {
        it('semua ikon SVG dekoratif harus memiliki aria-hidden="true"', () => {
            const { container } = render(Register);
            const svgs = container.querySelectorAll('svg');

            expect(svgs.length).toBeGreaterThan(0);
            svgs.forEach((svg) => {
                expect(svg).toHaveAttribute('aria-hidden', 'true');
            });
        });
    });
});
