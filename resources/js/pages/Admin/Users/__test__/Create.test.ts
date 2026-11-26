/**
 * ============================================================
 * Admin Users — Create Page (Tambah Admin) — Component Test
 * ============================================================
 *
 * Fokus pengujian (sesuai ketentuan):
 *   1. Isolation & Mocking  → Inertia, API createAdmin, toast, menu,
 *                             & child components (UserAccountForm,
 *                             UserPasswordForm, BackButton) di-mock.
 *   2. Props & Rendering    → header, error banner, tombol submit.
 *   3. Interaction          → submit form, error state, processing.
 *   4. Reactivity           → errorCount, processing update DOM.
 *
 * Strategi mock:
 *   - Anchor-aware pattern (insertBefore ke anchor.parentNode) untuk
 *     kompatibilitas Svelte 5 runtime.
 *   - UserAccountForm & UserPasswordForm di-mock dengan input yang
 *     mem-bind `form` / `password` via custom event agar test bisa
 *     mengisi field dari luar.
 *   - mockUpdaters Set + syncUI() untuk memastikan DOM merefleksikan
 *     state Svelte terbaru setelah setiap interaksi.
 * ============================================================
 */

import { render, screen, fireEvent } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { tick } from 'svelte';
import Create from '../Create.svelte';

// ============================================================
// 1. HOISTED MOCK REGISTRY
// ============================================================
const mockUpdaters = vi.hoisted(() => new Set<() => void>());

// ============================================================
// 2. MOCK — INERTIA
// ============================================================
vi.mock('@inertiajs/svelte', () => ({
    inertia: vi.fn(),
}));

// ============================================================
// 3. MOCK — API ADMIN
// ============================================================
vi.mock('@/api/admin', () => ({
    createAdmin: vi.fn(),
}));

// ============================================================
// 4. MOCK — TOAST
// ============================================================
vi.mock('@/utils/toast.svelte', () => ({
    showToast: vi.fn(),
}));

// ============================================================
// 5. MOCK — MENUS
// ============================================================
vi.mock('@/utils/menus', () => ({
    adminMenu: [],
}));

// ============================================================
// 6. MOCK — DASHBOARD LAYOUT (anchor-aware, pass-through children)
// ============================================================
vi.mock('@/components/layout/DashboardLayout.svelte', () => ({
    default: (anchor: Node, props: { children?: (anchor: Node) => void }) => {
        props.children?.(anchor);
        return {};
    },
}));

// ============================================================
// 7. MOCK — BACK BUTTON (render link agar assertion href mudah)
// ============================================================
vi.mock('@/global/BackButton.svelte', () => ({
    default: (anchor: Node, props: { href: string; label?: string }) => {
        const link = document.createElement('a');
        link.href = props.href;
        link.textContent = props.label ?? 'Kembali';
        link.setAttribute('data-testid', 'back-button');
        anchor.parentNode?.insertBefore(link, anchor);
        return {};
    },
}));

// ============================================================
// 8. MOCK — USER ACCOUNT FORM (render input minimal + trigger oninput)
// ============================================================
vi.mock('@/components/admin/users/UserAccountForm.svelte', () => ({
    default: (
        anchor: Node,
        props: {
            form: { name: string; username: string; email: string };
            errors: Record<string, string>;
        },
    ) => {
        const wrapper = document.createElement('div');
        wrapper.setAttribute('data-testid', 'user-account-form');
        anchor.parentNode?.insertBefore(wrapper, anchor);

        const update = () => {
            wrapper.innerHTML = '';

            const fields: Array<[string, string, string]> = [
                ['name', 'Nama', props.form.name],
                ['username', 'Username', props.form.username],
                ['email', 'Email', props.form.email],
            ];

            fields.forEach(([key, label, value]) => {
                const input = document.createElement('input');
                input.type = 'text';
                input.setAttribute('aria-label', label);
                input.value = value ?? '';
                input.oninput = (e) => {
                    (props.form as any)[key] = (
                        e.target as HTMLInputElement
                    ).value;
                };
                wrapper.appendChild(input);

                if (props.errors[key]) {
                    const err = document.createElement('p');
                    err.setAttribute('role', 'alert');
                    err.textContent = props.errors[key];
                    wrapper.appendChild(err);
                }
            });
        };

        update();
        mockUpdaters.add(update);
        return {};
    },
}));

// ============================================================
// 9. MOCK — USER PASSWORD FORM (input password + trigger oninput)
// ============================================================
vi.mock('@/components/admin/users/UserPasswordForm.svelte', () => ({
    default: (
        anchor: Node,
        props: {
            password: string;
            error?: string;
        },
    ) => {
        const wrapper = document.createElement('div');
        wrapper.setAttribute('data-testid', 'user-password-form');
        anchor.parentNode?.insertBefore(wrapper, anchor);

        const update = () => {
            wrapper.innerHTML = '';

            const input = document.createElement('input');
            input.type = 'password';
            input.setAttribute('aria-label', 'Password');
            input.value = props.password ?? '';
            input.oninput = (e) => {
                props.password = (e.target as HTMLInputElement).value;
            };
            wrapper.appendChild(input);

            if (props.error) {
                const err = document.createElement('p');
                err.setAttribute('role', 'alert');
                err.textContent = props.error;
                wrapper.appendChild(err);
            }
        };

        update();
        mockUpdaters.add(update);
        return {};
    },
}));

// ============================================================
// 10. STATIC IMPORT (setelah semua vi.mock)
// ============================================================
import { createAdmin } from '@/api/admin';
import { showToast } from '@/utils/toast.svelte';

// ============================================================
// 11. HELPERS
// ============================================================
async function syncUI(): Promise<void> {
    await tick();
    mockUpdaters.forEach((fn) => fn());
}

/** Isi field berdasarkan aria-label lalu trigger re-render mock. */
async function fillField(label: string, value: string): Promise<void> {
    const input = screen.getByLabelText(label) as HTMLInputElement;
    await fireEvent.input(input, { target: { value } });
    await syncUI();
}

// ============================================================
// 12. TEST SUITE
// ============================================================
describe('Admin Users — Create Page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        mockUpdaters.clear();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    // ----------------------------------------------------------
    // 12.1 RENDERING — struktur dasar
    // ----------------------------------------------------------
    describe('Rendering', () => {
        it('menampilkan judul halaman, back button, header form, dan tombol submit', () => {
            render(Create);

            // Judul via svelte:head tidak terlihat di DOM body, tapi tombol/header ya
            expect(screen.getByText('Buat Akun Admin')).toBeInTheDocument();
            expect(
                screen.getByText('Isi data dasar untuk akun admin baru.'),
            ).toBeInTheDocument();

            // Back button
            const backBtn = screen.getByTestId('back-button');
            expect(backBtn).toHaveTextContent('Kembali ke Daftar User');
            expect(backBtn).toHaveAttribute('href', '/admin/users');

            // Form fields (via mock UserAccountForm)
            expect(screen.getByLabelText('Nama')).toBeInTheDocument();
            expect(screen.getByLabelText('Username')).toBeInTheDocument();
            expect(screen.getByLabelText('Email')).toBeInTheDocument();

            // Password tidak lagi diinput admin; diatur lewat email.
            expect(screen.queryByLabelText('Password')).not.toBeInTheDocument();

            // Submit button
            expect(
                screen.getByRole('button', { name: 'Buat Admin' }),
            ).toBeInTheDocument();
        });

        it('tidak menampilkan error banner saat belum ada error', () => {
            render(Create);
            expect(
                screen.queryByText(/kesalahan pada form/i),
            ).not.toBeInTheDocument();
        });
    });

    // ----------------------------------------------------------
    // 12.2 INTERACTION — submit form dengan payload benar
    // ----------------------------------------------------------
    describe('Interaction — Submit', () => {
        it('mengirim payload createAdmin dengan role=admin dan data form', async () => {
            render(Create);

            await fillField('Nama', 'Budi Santoso');
            await fillField('Username', 'budisan');
            await fillField('Email', 'budi@test.com');

            // Mock createAdmin — tidak panggil callback agar processing tetap true sementara
            vi.mocked(createAdmin).mockImplementation(() => {});

            await fireEvent.click(
                screen.getByRole('button', { name: 'Buat Admin' }),
            );

            expect(createAdmin).toHaveBeenCalledTimes(1);
            expect(createAdmin).toHaveBeenCalledWith(
                {
                    role: 'admin',
                    name: 'Budi Santoso',
                    username: 'budisan',
                    email: 'budi@test.com',
                },
                expect.any(Object),
            );
        });

        it('mengirim username=null saat username kosong', async () => {
            render(Create);

            await fillField('Nama', 'Budi');
            await fillField('Email', 'budi@test.com');

            vi.mocked(createAdmin).mockImplementation(() => {});

            await fireEvent.click(
                screen.getByRole('button', { name: 'Buat Admin' }),
            );

            expect(createAdmin).toHaveBeenCalledWith(
                expect.objectContaining({ username: null }),
                expect.any(Object),
            );
        });

        it('tidak mengirim field password sama sekali', async () => {
            render(Create);

            await fillField('Nama', 'Budi');
            await fillField('Email', 'budi@test.com');

            vi.mocked(createAdmin).mockImplementation(() => {});

            await fireEvent.click(
                screen.getByRole('button', { name: 'Buat Admin' }),
            );

            expect(createAdmin).toHaveBeenCalledWith(
                {
                    role: 'admin',
                    name: 'Budi',
                    username: null,
                    email: 'budi@test.com',
                },
                expect.any(Object),
            );
        });
    });

    // ----------------------------------------------------------
    // 12.3 INTERACTION — processing state
    // ----------------------------------------------------------
    describe('Interaction — Processing', () => {
        it('menonaktifkan tombol & menampilkan "Menyimpan…" selama processing', async () => {
            render(Create);

            // createAdmin tidak memanggil callback → processing tetap true
            vi.mocked(createAdmin).mockImplementation(() => {});

            await fireEvent.click(
                screen.getByRole('button', { name: 'Buat Admin' }),
            );
            await syncUI();

            const btn = screen.getByRole('button', { name: /menyimpan/i });
            expect(btn).toBeDisabled();
        });

        it('mengaktifkan tombol kembali setelah onFinish dipanggil', async () => {
            let finishCallback: (() => void) | undefined;
            vi.mocked(createAdmin).mockImplementation((_payload, options) => {
                finishCallback = () => options?.onFinish?.({} as any);
            });

            render(Create);

            await fireEvent.click(
                screen.getByRole('button', { name: 'Buat Admin' }),
            );
            await syncUI();

            expect(
                screen.getByRole('button', { name: /menyimpan/i }),
            ).toBeDisabled();

            finishCallback?.();
            await syncUI();

            expect(
                screen.getByRole('button', { name: 'Buat Admin' }),
            ).not.toBeDisabled();
        });
    });

    // ----------------------------------------------------------
    // 12.4 INTERACTION — error handling
    // ----------------------------------------------------------
    describe('Interaction — Error Handling', () => {
        it('menampilkan error banner & error field saat onError dipanggil', async () => {
            vi.mocked(createAdmin).mockImplementation((_payload, options) => {
                options?.onError?.({
                    name: 'Nama wajib diisi',
                    email: 'Email tidak valid',
                });
                options?.onFinish?.({} as any);
            });

            render(Create);

            await fireEvent.click(
                screen.getByRole('button', { name: 'Buat Admin' }),
            );
            await syncUI();

            // Error banner
            expect(
                screen.getByText(/Terdapat 2 kesalahan pada form/i),
            ).toBeInTheDocument();

            // Error field
            expect(screen.getByText('Nama wajib diisi')).toBeInTheDocument();
            expect(screen.getByText('Email tidak valid')).toBeInTheDocument();
        });

        it('menampilkan toast error saat onError dipanggil', async () => {
            vi.mocked(createAdmin).mockImplementation((_payload, options) => {
                options?.onError?.({ name: 'Required' });
                options?.onFinish?.({} as any);
            });

            render(Create);

            await fireEvent.click(
                screen.getByRole('button', { name: 'Buat Admin' }),
            );
            await syncUI();

            expect(showToast).toHaveBeenCalledWith(
                'Terdapat kesalahan pada form.',
                'error',
            );
        });

        it('menampilkan toast success saat onSuccess dipanggil', async () => {
            vi.mocked(createAdmin).mockImplementation((_payload, options) => {
                options?.onSuccess?.({} as any);
                options?.onFinish?.({} as any);
            });

            render(Create);

            await fireEvent.click(
                screen.getByRole('button', { name: 'Buat Admin' }),
            );
            await syncUI();

            expect(showToast).toHaveBeenCalledWith(
                'Akun dibuat. Tautan set password dikirim ke email.',
                'success',
            );
        });

        it('menghapus error banner saat submit ulang', async () => {
            let callCount = 0;
            vi.mocked(createAdmin).mockImplementation((_payload, options) => {
                callCount++;
                if (callCount === 1) {
                    options?.onError?.({ name: 'Required' });
                }
                options?.onFinish?.({} as any);
            });

            render(Create);

            // Submit pertama → error
            await fireEvent.click(
                screen.getByRole('button', { name: 'Buat Admin' }),
            );
            await syncUI();
            expect(
                screen.getByText(/Terdapat 1 kesalahan/i),
            ).toBeInTheDocument();

            // Submit kedua → errors reset
            await fireEvent.click(
                screen.getByRole('button', { name: 'Buat Admin' }),
            );
            await syncUI();
            expect(
                screen.queryByText(/kesalahan pada form/i),
            ).not.toBeInTheDocument();
        });
    });

    // ----------------------------------------------------------
    // 12.5 REACTIVITY — errorCount derived
    // ----------------------------------------------------------
    describe('Reactivity — Error Count', () => {
        it('menampilkan jumlah error yang akurat di banner', async () => {
            vi.mocked(createAdmin).mockImplementation((_payload, options) => {
                options?.onError?.({
                    name: 'Required',
                    email: 'Invalid',
                    password: 'Too short',
                });
                options?.onFinish?.({} as any);
            });

            render(Create);

            await fireEvent.click(
                screen.getByRole('button', { name: 'Buat Admin' }),
            );
            await syncUI();

            expect(
                screen.getByText(/Terdapat 3 kesalahan/i),
            ).toBeInTheDocument();
        });
    });
});
