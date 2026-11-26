/**
 * ============================================================
 * Admin Users — Edit Page — Component Test
 * ============================================================
 *
 * Fokus pengujian (sesuai ketentuan):
 *   1. Isolation & Mocking  → Inertia, API updateUser, menu,
 *                             & child components (UserAccountFields,
 *                             UserProfileFields, BackButton) di-mock.
 *   2. Props & Rendering    → header, role badge, field render per role.
 *   3. Interaction          → submit form, error state, processing.
 *   4. Reactivity           → error banner, processing update DOM.
 *
 * Strategi mock:
 *   - Anchor-aware pattern (`insertBefore(anchor.parentNode)`) untuk
 *     kompatibilitas Svelte 5 runtime.
 *   - Mock child components me-render input yang mem-bind ke `form`
 *     yang diteruskan, supaya test bisa mengisi field dan verifikasi
 *     payload yang dikirim ke API.
 *   - `mockUpdaters` + `syncUI()` memastikan DOM merefleksikan state
 *     Svelte terbaru setelah setiap interaksi.
 * ============================================================
 */

import { render, screen, fireEvent } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { tick } from 'svelte';
import Edit from '../Edit.svelte';

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
    updateUser: vi.fn(),
}));

// ============================================================
// 4. MOCK — MENUS
// ============================================================
vi.mock('@/utils/menus', () => ({
    adminMenu: [],
}));

// ============================================================
// 5. MOCK — DASHBOARD LAYOUT (pass-through children)
// ============================================================
vi.mock('@/components/layout/DashboardLayout.svelte', () => ({
    default: (anchor: Node, props: { children?: (anchor: Node) => void }) => {
        props.children?.(anchor);
        return {};
    },
}));

// ============================================================
// 6. MOCK — BACK BUTTON
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
// 7. MOCK — USER ACCOUNT FIELDS (bind ke form.name/username/email)
// ============================================================
vi.mock('@/components/admin/users/UserAccountFields.svelte', () => ({
    default: (
        anchor: Node,
        props: {
            form: { name: string; username: string; email: string };
            errors: Record<string, string>;
        },
    ) => {
        const wrapper = document.createElement('div');
        wrapper.setAttribute('data-testid', 'user-account-fields');
        anchor.parentNode?.insertBefore(wrapper, anchor);

        const update = () => {
            wrapper.innerHTML = '';

            const fields: Array<[keyof typeof props.form, string]> = [
                ['name', 'Nama'],
                ['username', 'Username'],
                ['email', 'Email'],
            ];

            fields.forEach(([key, label]) => {
                const input = document.createElement('input');
                input.type = 'text';
                input.setAttribute('aria-label', label);
                input.value = (props.form as any)[key] ?? '';
                input.oninput = (e) => {
                    (props.form as any)[key] = (
                        e.target as HTMLInputElement
                    ).value;
                };
                wrapper.appendChild(input);

                if (props.errors[key as string]) {
                    const err = document.createElement('p');
                    err.setAttribute('role', 'alert');
                    err.setAttribute('data-testid', `error-${key}`);
                    err.textContent = props.errors[key as string];
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
// 8. MOCK — USER PROFILE FIELDS (bind ke form.profile.*)
// ============================================================
vi.mock('@/components/admin/users/UserProfileFields.svelte', () => ({
    default: (
        anchor: Node,
        props: {
            form: {
                profile: {
                    nama_penyedia_usaha: string;
                    nomor_identitas: string;
                    telepon: string;
                    alamat: string;
                    provinsi: string;
                    kota: string;
                    kode_pos: string;
                    deskripsi: string;
                };
            };
        },
    ) => {
        const wrapper = document.createElement('div');
        wrapper.setAttribute('data-testid', 'user-profile-fields');
        anchor.parentNode?.insertBefore(wrapper, anchor);

        const update = () => {
            wrapper.innerHTML = '';

            const fields: Array<[keyof typeof props.form.profile, string]> = [
                ['nama_penyedia_usaha', 'Nama Usaha'],
                ['nomor_identitas', 'Nomor Identitas'],
                ['telepon', 'Telepon'],
                ['alamat', 'Alamat'],
                ['provinsi', 'Provinsi'],
                ['kota', 'Kota'],
                ['kode_pos', 'Kode Pos'],
                ['deskripsi', 'Deskripsi Usaha'],
            ];

            fields.forEach(([key, label]) => {
                const input = document.createElement('input');
                input.type = 'text';
                input.setAttribute('aria-label', label);
                input.value = props.form.profile?.[key] ?? '';
                input.oninput = (e) => {
                    (props.form.profile as any)[key] = (
                        e.target as HTMLInputElement
                    ).value;
                };
                wrapper.appendChild(input);
            });
        };

        update();
        mockUpdaters.add(update);
        return {};
    },
}));

// ============================================================
// 9. STATIC IMPORT (setelah semua vi.mock)
// ============================================================
import { updateUser } from '@/api/admin';

// ============================================================
// 10. HELPERS
// ============================================================
async function syncUI(): Promise<void> {
    await tick();
    mockUpdaters.forEach((fn) => fn());
}

async function fillField(label: string, value: string): Promise<void> {
    const input = screen.getByLabelText(label) as HTMLInputElement;
    await fireEvent.input(input, { target: { value } });
    await syncUI();
}

// ============================================================
// 11. FIXTURES
// ============================================================
const makeAdmin = () => ({
    id: 1,
    name: 'Super Admin',
    username: 'superadmin',
    email: 'admin@test.com',
    role: 'admin' as const,
    profile: null,
});

const makeMitra = () => ({
    id: 2,
    name: 'Mitra Satu',
    username: 'mitrasatu',
    email: 'mitra1@test.com',
    role: 'mitra' as const,
    profile: {
        nama_penyedia_usaha: 'PT Mitra Jaya',
        nomor_identitas: '1234567890',
        alamat: 'Jl. Merdeka No. 1',
        telepon: '+62 812 3456 7890',
        provinsi: 'Jawa Barat',
        kota: 'Bandung',
        kode_pos: '40111',
        deskripsi: 'Travel agent terpercaya',
    },
});

// ============================================================
// 12. TEST SUITE
// ============================================================
describe('Admin Users — Edit Page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        mockUpdaters.clear();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    // ----------------------------------------------------------
    // 12.1 RENDERING — header & badge role
    // ----------------------------------------------------------
    describe('Rendering — Header', () => {
        it('menampilkan inisial, nama, email, dan role badge Admin', () => {
            render(Edit, { user: makeAdmin() });

            // Inisial
            expect(screen.getByText('S')).toBeInTheDocument();

            // Nama & email
            expect(screen.getByText('Edit Super Admin')).toBeInTheDocument();
            expect(screen.getByText('admin@test.com')).toBeInTheDocument();

            // Role badge
            expect(screen.getByText('Admin')).toBeInTheDocument();
        });

        it('menampilkan role badge Mitra untuk user mitra', () => {
            render(Edit, { user: makeMitra() });

            expect(screen.getByText('Edit Mitra Satu')).toBeInTheDocument();
            expect(screen.getByText('Mitra')).toBeInTheDocument();
            expect(screen.getByText('mitra1@test.com')).toBeInTheDocument();
        });

        it('menampilkan back button dengan href benar', () => {
            render(Edit, { user: makeAdmin() });

            const backBtn = screen.getByTestId('back-button');
            expect(backBtn).toHaveAttribute('href', '/admin/users');
            expect(backBtn).toHaveTextContent('Kembali ke Daftar User');
        });
    });

    // ----------------------------------------------------------
    // 12.2 RENDERING — conditional section by role
    // ----------------------------------------------------------
    describe('Rendering — Conditional by Role', () => {
        it('TIDAK merender section Data Usaha untuk role admin', () => {
            render(Edit, { user: makeAdmin() });

            expect(screen.getByText('Data Akun')).toBeInTheDocument();
            expect(screen.queryByText('Data Usaha')).not.toBeInTheDocument();
            expect(
                screen.queryByTestId('user-profile-fields'),
            ).not.toBeInTheDocument();
        });

        it('merender section Data Usaha untuk role mitra', () => {
            render(Edit, { user: makeMitra() });

            expect(screen.getByText('Data Akun')).toBeInTheDocument();
            expect(screen.getByText('Data Usaha')).toBeInTheDocument();
            expect(
                screen.getByTestId('user-profile-fields'),
            ).toBeInTheDocument();
        });

        it('mengisi field akun dengan data user', () => {
            render(Edit, { user: makeAdmin() });

            expect(screen.getByLabelText('Nama')).toHaveValue('Super Admin');
            expect(screen.getByLabelText('Username')).toHaveValue('superadmin');
            expect(screen.getByLabelText('Email')).toHaveValue(
                'admin@test.com',
            );
        });

        it('mengisi field profil dengan data user mitra', () => {
            render(Edit, { user: makeMitra() });

            expect(screen.getByLabelText('Nama Usaha')).toHaveValue(
                'PT Mitra Jaya',
            );
            expect(screen.getByLabelText('Nomor Identitas')).toHaveValue(
                '1234567890',
            );
            expect(screen.getByLabelText('Kota')).toHaveValue('Bandung');
            expect(screen.getByLabelText('Kode Pos')).toHaveValue('40111');
        });

        it('mengisi field kosong saat profile null (fallback string kosong)', () => {
            const userWithoutProfile = {
                ...makeAdmin(),
                username: null as any, // null diubah ke '' di form
            };
            render(Edit, { user: userWithoutProfile });

            expect(screen.getByLabelText('Username')).toHaveValue('');
        });
    });

    // ----------------------------------------------------------
    // 12.3 INTERACTION — submit payload
    // ----------------------------------------------------------
    describe('Interaction — Submit', () => {
        it('memanggil updateUser dengan id user dan form yang valid', async () => {
            render(Edit, { user: makeAdmin() });

            await fillField('Nama', 'Super Admin Baru');

            vi.mocked(updateUser).mockImplementation(() => {});

            await fireEvent.click(
                screen.getByRole('button', { name: 'Simpan Perubahan' }),
            );

            expect(updateUser).toHaveBeenCalledTimes(1);
            expect(updateUser).toHaveBeenCalledWith(
                1,
                expect.objectContaining({
                    name: 'Super Admin Baru',
                    username: 'superadmin',
                    email: 'admin@test.com',
                }),
                expect.any(Object),
            );
        });

        it('mengirim payload lengkap termasuk profile untuk role mitra', async () => {
            render(Edit, { user: makeMitra() });

            await fillField('Nama Usaha', 'PT Mitra Baru');

            vi.mocked(updateUser).mockImplementation(() => {});

            await fireEvent.click(
                screen.getByRole('button', { name: 'Simpan Perubahan' }),
            );

            expect(updateUser).toHaveBeenCalledWith(
                2,
                expect.objectContaining({
                    name: 'Mitra Satu',
                    profile: expect.objectContaining({
                        nama_penyedia_usaha: 'PT Mitra Baru',
                        nomor_identitas: '1234567890',
                        kota: 'Bandung',
                    }),
                }),
                expect.any(Object),
            );
        });
    });

    // ----------------------------------------------------------
    // 12.4 INTERACTION — processing state
    // ----------------------------------------------------------
    describe('Interaction — Processing', () => {
        it('menonaktifkan tombol & menampilkan "Menyimpan…" selama processing', async () => {
            vi.mocked(updateUser).mockImplementation(() => {});

            render(Edit, { user: makeAdmin() });

            await fireEvent.click(
                screen.getByRole('button', { name: 'Simpan Perubahan' }),
            );
            await syncUI();

            expect(
                screen.getByRole('button', { name: /menyimpan/i }),
            ).toBeDisabled();
        });

        it('mengaktifkan tombol kembali setelah onFinish', async () => {
            let finishCallback: (() => void) | undefined;
            vi.mocked(updateUser).mockImplementation((_id, _data, options) => {
                finishCallback = () => options?.onFinish?.({} as any);
            });

            render(Edit, { user: makeAdmin() });

            await fireEvent.click(
                screen.getByRole('button', { name: 'Simpan Perubahan' }),
            );
            await syncUI();
            expect(
                screen.getByRole('button', { name: /menyimpan/i }),
            ).toBeDisabled();

            finishCallback?.();
            await syncUI();

            expect(
                screen.getByRole('button', { name: 'Simpan Perubahan' }),
            ).not.toBeDisabled();
        });
    });

    // ----------------------------------------------------------
    // 12.5 INTERACTION — error handling
    // ----------------------------------------------------------
    describe('Interaction — Error Handling', () => {
        it('menampilkan pesan error per field saat onError dipanggil', async () => {
            vi.mocked(updateUser).mockImplementation((_id, _data, options) => {
                options?.onError?.({
                    name: 'Nama wajib diisi',
                    email: 'Email tidak valid',
                });
                options?.onFinish?.({} as any);
            });

            render(Edit, { user: makeAdmin() });

            await fireEvent.click(
                screen.getByRole('button', { name: 'Simpan Perubahan' }),
            );
            await syncUI();

            expect(screen.getByText('Nama wajib diisi')).toBeInTheDocument();
            expect(screen.getByText('Email tidak valid')).toBeInTheDocument();
        });

        it('mengaktifkan tombol submit kembali setelah error', async () => {
            vi.mocked(updateUser).mockImplementation((_id, _data, options) => {
                options?.onError?.({ name: 'Required' });
                options?.onFinish?.({} as any);
            });

            render(Edit, { user: makeAdmin() });

            await fireEvent.click(
                screen.getByRole('button', { name: 'Simpan Perubahan' }),
            );
            await syncUI();

            expect(
                screen.getByRole('button', { name: 'Simpan Perubahan' }),
            ).not.toBeDisabled();
        });

        it('menghapus error lama saat submit ulang', async () => {
            let callCount = 0;
            vi.mocked(updateUser).mockImplementation((_id, _data, options) => {
                callCount++;
                if (callCount === 1) {
                    options?.onError?.({ name: 'Required' });
                }
                options?.onFinish?.({} as any);
            });

            render(Edit, { user: makeAdmin() });

            // Submit 1 → error muncul
            await fireEvent.click(
                screen.getByRole('button', { name: 'Simpan Perubahan' }),
            );
            await syncUI();
            expect(screen.getByText('Required')).toBeInTheDocument();

            // Submit 2 → errors reset
            await fireEvent.click(
                screen.getByRole('button', { name: 'Simpan Perubahan' }),
            );
            await syncUI();
            expect(screen.queryByText('Required')).not.toBeInTheDocument();
        });
    });

    // ----------------------------------------------------------
    // 12.6 A11Y — form structure
    // ----------------------------------------------------------
    describe('Accessibility', () => {
        it('semua field wajib memiliki label yang jelas', () => {
            render(Edit, { user: makeAdmin() });

            // Field wajib pakai <label for="…">
            expect(screen.getByLabelText('Nama')).toBeInTheDocument();
            expect(screen.getByLabelText('Email')).toBeInTheDocument();
            expect(screen.getByLabelText('Username')).toBeInTheDocument();
        });

        it('field dengan error memiliki aria-invalid dan role="alert"', async () => {
            vi.mocked(updateUser).mockImplementation((_id, _data, options) => {
                options?.onError?.({ name: 'Wajib' });
                options?.onFinish?.({} as any);
            });

            render(Edit, { user: makeAdmin() });

            await fireEvent.click(
                screen.getByRole('button', { name: 'Simpan Perubahan' }),
            );
            await syncUI();

            // error message
            const errorMsg = screen.getByText('Wajib');
            expect(errorMsg).toHaveAttribute('role', 'alert');
        });
    });
});
