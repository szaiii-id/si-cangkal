import { render, screen, fireEvent } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import UserAccountFields from '../UserAccountFields.svelte';
import type { UserAccountFormData } from '@/types/models/user';
// ==========================================
// MOCK DATA
// ==========================================
const createMockForm = (
    overrides: Partial<UserAccountFormData> = {},
): UserAccountFormData => ({
    name: 'Budi Santoso',
    email: 'budi@test.com',
    username: 'budi',
    ...overrides,
});

describe('UserAccountFields Component', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    // ==========================================
    // 1. RENDERING DASAR
    // ==========================================
    describe('Rendering', () => {
        it('harus merender 3 input (nama, email, username)', () => {
            render(UserAccountFields, {
                props: { form: createMockForm(), errors: {} },
            });

            expect(screen.getByLabelText(/Nama/i)).toBeInTheDocument();
            expect(screen.getByLabelText(/Email/i)).toBeInTheDocument();
            expect(screen.getByLabelText(/Username/i)).toBeInTheDocument();
        });

        it('harus menampilkan nilai awal dari props form', () => {
            render(UserAccountFields, {
                props: { form: createMockForm(), errors: {} },
            });

            const nameInput = screen.getByLabelText(
                /Nama/i,
            ) as HTMLInputElement;
            expect(nameInput.value).toBe('Budi Santoso');

            const emailInput = screen.getByLabelText(
                /Email/i,
            ) as HTMLInputElement;
            expect(emailInput.value).toBe('budi@test.com');
        });
    });

    // ==========================================
    // 2. ERROR STATE
    // ==========================================
    describe('Error State', () => {
        it('harus menampilkan pesan error untuk nama jika ada', () => {
            render(UserAccountFields, {
                props: {
                    form: createMockForm(),
                    errors: { name: 'Nama wajib diisi' },
                },
            });

            expect(screen.getByText('Nama wajib diisi')).toBeInTheDocument();
        });

        it('harus menampilkan pesan error untuk email jika ada', () => {
            render(UserAccountFields, {
                props: {
                    form: createMockForm(),
                    errors: { email: 'Email tidak valid' },
                },
            });

            expect(screen.getByText('Email tidak valid')).toBeInTheDocument();
        });

        it('harus menampilkan pesan error untuk username jika ada', () => {
            render(UserAccountFields, {
                props: {
                    form: createMockForm(),
                    errors: { username: 'Username sudah digunakan' },
                },
            });

            expect(
                screen.getByText('Username sudah digunakan'),
            ).toBeInTheDocument();
        });

        it('input harus memiliki aria-invalid=true jika error', () => {
            render(UserAccountFields, {
                props: {
                    form: createMockForm(),
                    errors: { email: 'Error' },
                },
            });

            const emailInput = screen.getByLabelText(/Email/i);
            expect(emailInput).toHaveAttribute('aria-invalid', 'true');
        });
    });

    // ==========================================
    // 3. USER INTERACTION
    // ==========================================
    describe('User Interaction', () => {
        it('harus memperbarui nilai input saat user mengetik', async () => {
            render(UserAccountFields, {
                props: { form: createMockForm(), errors: {} },
            });

            const nameInput = screen.getByLabelText(
                /Nama/i,
            ) as HTMLInputElement;
            await fireEvent.input(nameInput, {
                target: { value: 'Budi Baru' },
            });

            expect(nameInput.value).toBe('Budi Baru');
        });
    });

    // ==========================================
    // 4. AKSESIBILITAS (A11Y)
    // ==========================================
    describe('Accessibility', () => {
        it('pesan error harus memiliki role="alert"', () => {
            render(UserAccountFields, {
                props: {
                    form: createMockForm(),
                    errors: { name: 'Nama wajib diisi' },
                },
            });

            const errorMessage = screen.getByRole('alert');
            expect(errorMessage).toHaveTextContent('Nama wajib diisi');
        });
    });
});
