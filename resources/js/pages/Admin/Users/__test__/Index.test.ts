/**
 * ============================================================
 * Admin Users Index Page — Component Test
 * ============================================================
 *
 * Fokus pengujian (sesuai ketentuan):
 *   1. Isolation & Mocking     → Inertia, API, & child components di-mock
 *   2. Props & Rendering       → DOM dirender sesuai props (default & edge case)
 *   3. User Interaction        → click/input → callback dengan payload benar
 *   4. Reactivity              → state lokal berubah → DOM re-render
 *
 * Strategi mock:
 *   - Child components di-mock dengan pendekatan **anchor-aware**
 *     (insertBefore ke `anchor.parentNode`) supaya kompatibel dengan
 *     Svelte 5 runtime — bukan `createRawSnippet` yang error untuk
 *     komponen penerima `Snippet`.
 *   - `mockUpdaters` Set menyimpan fungsi update per komponen;
 *     `syncUI()` memanggil `tick()` + semua updater agar DOM
 *     mencerminkan state Svelte terbaru secara manual.
 *
 * ⚠️ Catatan penting:
 *   Mock melakukan `el.innerHTML = ''` di setiap `update()` → reference
 *   DOM lama menjadi stale. SELALU query ulang setelah `syncUI()`,
 *   jangan simpan reference tombol antar siklus render.
 * ============================================================
 */

import { render, screen, fireEvent } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { tick } from 'svelte';
import Index from '../Index.svelte';
import type { Credentials, PaginationMeta, StatusTab } from '@/types';

// ============================================================
// 1. HOISTED MOCK REGISTRY
// ============================================================
const mockUpdaters = vi.hoisted(() => new Set<() => void>());

const mockPage = vi.hoisted(() => ({
    props: {
        auth: {
            user: { id: 1, name: 'Super Admin', role: 'admin' },
        },
        flash: {
            credentials: null as Credentials | null,
        },
    },
}));

// ============================================================
// 2. MOCK — INERTIA
// ============================================================
vi.mock('@inertiajs/svelte', () => ({
    page: mockPage,
    inertia: vi.fn(),
}));

// ============================================================
// 3. MOCK — API ADMIN
// ============================================================
vi.mock('@/api/admin', () => ({
    getUsers: vi.fn(),
    deleteUser: vi.fn(),
    resetUserPassword: vi.fn(),
}));

// ============================================================
// 4. MOCK — MENUS
// ============================================================
vi.mock('@/utils/menus', () => ({
    adminMenu: [],
}));

// ============================================================
// 5. MOCK — LAYOUT (anchor-aware, pass-through children)
// ============================================================
vi.mock('@/components/layout/DashboardLayout.svelte', () => ({
    default: (anchor: Node, props: { children?: (anchor: Node) => void }) => {
        props.children?.(anchor);
        return {};
    },
}));

// ============================================================
// 6. MOCK — PAGE HEADER (anchor-aware, render actions snippet)
// ============================================================
vi.mock('@/global/PageHeader.svelte', () => ({
    default: (
        anchor: Node,
        props: { title: string; description?: string; actions?: (anchor: Node) => void },
    ) => {
        const el = document.createElement('div');
        el.setAttribute('data-testid', 'page-header');
        el.innerHTML = `<h1>${props.title}</h1><p>${props.description ?? ''}</p>`;
        anchor.parentNode?.insertBefore(el, anchor);
        props.actions?.(anchor);
        return {};
    },
}));

// ============================================================
// 7. MOCK — STATUS TABS (reactive via mockUpdaters)
// ============================================================
vi.mock('@/global/StatusTabs.svelte', () => ({
    default: (
        anchor: Node,
        props: {
            tabs: StatusTab[];
            active: string;
            onchange?: (val: string) => void;
        },
    ) => {
        const el = document.createElement('div');
        el.setAttribute('role', 'tablist');
        anchor.parentNode?.insertBefore(el, anchor);

        const update = () => {
            el.innerHTML = '';
            props.tabs.forEach((tab) => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.setAttribute('role', 'tab');
                btn.setAttribute('aria-selected', String(props.active === tab.value));
                btn.textContent = `${tab.label} (${tab.count})`;
                btn.onclick = () => {
                    props.active = tab.value;
                    props.onchange?.(tab.value);
                };
                el.appendChild(btn);
            });
        };

        update();
        mockUpdaters.add(update);
        return {};
    },
}));

// ============================================================
// 8. MOCK — SEARCH INPUT (reactive via mockUpdaters)
// ============================================================
vi.mock('@/global/SearchInput.svelte', () => ({
    default: (
        anchor: Node,
        props: {
            value: string;
            placeholder?: string;
            oninput?: (val: string) => void;
            onclear?: () => void;
        },
    ) => {
        const wrapper = document.createElement('div');
        const input = document.createElement('input');
        input.type = 'search';
        input.placeholder = props.placeholder ?? '';
        input.value = props.value ?? '';
        input.oninput = (e) => {
            const val = (e.target as HTMLInputElement).value;
            props.value = val;
            props.oninput?.(val);
        };

        const clearBtn = document.createElement('button');
        clearBtn.type = 'button';
        clearBtn.textContent = 'Hapus Pencarian';
        clearBtn.onclick = () => {
            props.value = '';
            props.onclear?.();
        };

        wrapper.append(input, clearBtn);
        anchor.parentNode?.insertBefore(wrapper, anchor);

        const update = () => {
            input.value = props.value ?? '';
        };
        mockUpdaters.add(update);
        return {};
    },
}));

// ============================================================
// 9. MOCK — USER TABLE (reactive via mockUpdaters)
// ============================================================
vi.mock('@/components/admin/users/UserTable.svelte', () => ({
    default: (
        anchor: Node,
        props: {
            users: Array<{ id: number; name: string; email: string; role: string }>;
            authUserId?: number;
            onDelete?: (u: { id: number; name: string }) => void;
            onReset?: (u: { id: number; name: string; email: string }) => void;
        },
    ) => {
        const el = document.createElement('div');
        el.setAttribute('data-testid', 'user-table');
        anchor.parentNode?.insertBefore(el, anchor);

        const update = () => {
            el.innerHTML = '';
            el.setAttribute('data-auth-user-id', String(props.authUserId ?? ''));
            props.users.forEach((user) => {
                const row = document.createElement('div');
                row.setAttribute('data-testid', `user-row-${user.id}`);
                row.textContent = `${user.name} (${user.role})`;

                const delBtn = document.createElement('button');
                delBtn.type = 'button';
                delBtn.textContent = `Hapus ${user.name}`;
                delBtn.onclick = () => props.onDelete?.(user);

                const resetBtn = document.createElement('button');
                resetBtn.type = 'button';
                resetBtn.textContent = `Reset ${user.name}`;
                resetBtn.onclick = () => props.onReset?.(user);

                row.append(delBtn, resetBtn);
                el.appendChild(row);
            });
        };

        update();
        mockUpdaters.add(update);
        return {};
    },
}));

// ============================================================
// 10. MOCK — EMPTY STATE (reactive via mockUpdaters)
// ============================================================
vi.mock('@/global/EmptyState.svelte', () => ({
    default: (
        anchor: Node,
        props: {
            title: string;
            description: string;
            hasFilters?: boolean;
            onClear?: () => void;
        },
    ) => {
        const el = document.createElement('div');
        el.setAttribute('data-testid', 'empty-state');
        anchor.parentNode?.insertBefore(el, anchor);

        const update = () => {
            el.innerHTML = `<h3>${props.title}</h3><p>${props.description}</p>`;
            if (props.hasFilters) {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.textContent = 'Reset Filter';
                btn.onclick = () => props.onClear?.();
                el.appendChild(btn);
            }
        };

        update();
        mockUpdaters.add(update);
        return {};
    },
}));

// ============================================================
// 11. MOCK — PAGINATION (statis, cukup render sekali)
// ============================================================
vi.mock('@/global/Pagination.svelte', () => ({
    default: (anchor: Node, props: { meta: PaginationMeta; label: string }) => {
        const el = document.createElement('div');
        el.setAttribute('data-testid', 'pagination');
        el.textContent = `Total ${props.meta.total} ${props.label}`;
        anchor.parentNode?.insertBefore(el, anchor);
        return {};
    },
}));

// ============================================================
// 12. MOCK — CREDENTIALS MODAL (reactive via mockUpdaters)
// ============================================================
vi.mock('../CredentialsModal.svelte', () => ({
    default: (anchor: Node, props: { open: boolean; credentials: Credentials | null }) => {
        const el = document.createElement('div');
        el.setAttribute('data-testid', 'credentials-modal');
        anchor.parentNode?.insertBefore(el, anchor);

        const update = () => {
            el.innerHTML = '';
            if (!props.open || !props.credentials) return;
            el.textContent = `Kredensial: ${props.credentials.username} / ${props.credentials.password}`;
        };

        update();
        mockUpdaters.add(update);
        return {};
    },
}));

// ============================================================
// 13. MOCK — CONFIRM MODAL (reactive, aware of `processing`)
// ============================================================
vi.mock('@/global/ConfirmModal.svelte', () => ({
    default: (
        anchor: Node,
        props: {
            open: boolean;
            variant?: string;
            title: string;
            description: string;
            confirmLabel: string;
            processing?: boolean;
            onConfirm?: () => void;
        },
    ) => {
        const el = document.createElement('div');
        el.setAttribute('data-testid', 'confirm-modal');
        anchor.parentNode?.insertBefore(el, anchor);

        const update = () => {
            el.innerHTML = '';
            if (!props.open) return;
            el.setAttribute('data-variant', props.variant ?? '');

            const heading = document.createElement('h3');
            heading.textContent = props.title;

            const desc = document.createElement('p');
            desc.textContent = props.description;

            const btn = document.createElement('button');
            btn.type = 'button';
            btn.textContent = props.confirmLabel;
            btn.disabled = Boolean(props.processing);
            btn.onclick = () => props.onConfirm?.();

            el.append(heading, desc, btn);
        };

        update();
        mockUpdaters.add(update);
        return {};
    },
}));

// ============================================================
// 14. MOCK — RESET PASSWORD MODAL (reactive via mockUpdaters)
// ============================================================
vi.mock('../ResetPasswordConfirmModal.svelte', () => ({
    default: (
        anchor: Node,
        props: {
            open: boolean;
            userName: string;
            userEmail: string;
            onConfirm?: () => void;
        },
    ) => {
        const el = document.createElement('div');
        el.setAttribute('data-testid', 'reset-modal');
        anchor.parentNode?.insertBefore(el, anchor);

        const update = () => {
            el.innerHTML = '';
            if (!props.open) return;

            const text = document.createElement('p');
            text.textContent = `Reset password untuk ${props.userName} (${props.userEmail})`;

            const btn = document.createElement('button');
            btn.type = 'button';
            btn.textContent = 'Konfirmasi Reset';
            btn.onclick = () => props.onConfirm?.();

            el.append(text, btn);
        };

        update();
        mockUpdaters.add(update);
        return {};
    },
}));

// ============================================================
// 15. STATIC IMPORT (setelah semua vi.mock agar mock aktif)
// ============================================================
import { getUsers, deleteUser, resetUserPassword } from '@/api/admin';

// ============================================================
// 16. HELPERS
// ============================================================

/**
 * Sinkronisasi DOM manual: jalankan `tick()` lalu panggil semua
 * `update` dari komponen yang di-mock agar re-render state Svelte
 * tercermin di DOM.
 *
 * Catatan: mock menggunakan `innerHTML = ''`, jadi SEMUA element
 * DOM lama menjadi stale — wajib query ulang setelah `syncUI()`.
 */
async function syncUI(): Promise<void> {
    await tick();
    mockUpdaters.forEach((fn) => fn());
}

// ============================================================
// 17. FIXTURES
// ============================================================
const sampleUsers = {
    current_page: 1,
    last_page: 1,
    per_page: 15,
    total: 3,
    from: 1,
    to: 3,
    links: [],
    data: [
        {
            id: 1,
            name: 'Super Admin',
            username: 'superadmin',
            email: 'admin@test.com',
            role: 'admin' as const,
            created_at: '2026-01-01T00:00:00Z',
        },
        {
            id: 2,
            name: 'Mitra Satu',
            username: 'mitrasatu',
            email: 'mitra1@test.com',
            role: 'mitra' as const,
            created_at: '2026-01-02T00:00:00Z',
        },
        {
            id: 3,
            name: 'Mitra Dua',
            username: 'mitradua',
            email: 'mitra2@test.com',
            role: 'mitra' as const,
            created_at: '2026-01-03T00:00:00Z',
        },
    ],
};

// ============================================================
// 18. TEST SUITE
// ============================================================
describe('Admin Users Index Page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        mockUpdaters.clear();
        mockPage.props.auth = { user: { id: 1, name: 'Super Admin', role: 'admin' } };
        mockPage.props.flash = { credentials: null };
        vi.useFakeTimers();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    // ----------------------------------------------------------
    // 18.1 RENDERING — Props & struktur DOM
    // ----------------------------------------------------------
    describe('Rendering', () => {
        it('merender header, tombol Tambah User, tab status, tabel, dan pagination dengan benar', () => {
            render(Index, {
                users: sampleUsers,
                filters: { search: '', per_page: 15 },
            });

            // PageHeader
            expect(screen.getByRole('heading', { name: 'Manajemen User' })).toBeInTheDocument();
            expect(screen.getByText('Kelola akun admin dan mitra')).toBeInTheDocument();

            // Actions snippet
            expect(screen.getByRole('link', { name: 'Tambah User' })).toHaveAttribute(
                'href',
                '/admin/users/create',
            );

            // StatusTabs — count derived akurat
            expect(screen.getByRole('tab', { name: 'Semua (3)' })).toHaveAttribute(
                'aria-selected',
                'true',
            );
            expect(screen.getByRole('tab', { name: 'Admin (1)' })).toBeInTheDocument();
            expect(screen.getByRole('tab', { name: 'Mitra (2)' })).toBeInTheDocument();

            // UserTable
            const table = screen.getByTestId('user-table');
            expect(table).toHaveAttribute('data-auth-user-id', '1');
            expect(screen.getByTestId('user-row-1')).toHaveTextContent('Super Admin (admin)');
            expect(screen.getByTestId('user-row-2')).toHaveTextContent('Mitra Satu (mitra)');
            expect(screen.getByTestId('user-row-3')).toHaveTextContent('Mitra Dua (mitra)');

            // Pagination
            expect(screen.getByTestId('pagination')).toHaveTextContent('Total 3 user');
        });
    });

    // ----------------------------------------------------------
    // 18.2 REACTIVITY — StatusTabs filter
    // ----------------------------------------------------------
    describe('Reactivity — StatusTabs', () => {
        it('memfilter daftar user saat tab Admin/Mitra diklik', async () => {
            render(Index, {
                users: sampleUsers,
                filters: { search: '', per_page: 15 },
            });

            // → Tab Admin
            await fireEvent.click(screen.getByRole('tab', { name: 'Admin (1)' }));
            await syncUI();

            expect(screen.getByTestId('user-row-1')).toBeInTheDocument();
            expect(screen.queryByTestId('user-row-2')).not.toBeInTheDocument();
            expect(screen.queryByTestId('user-row-3')).not.toBeInTheDocument();

            // → Tab Mitra
            await fireEvent.click(screen.getByRole('tab', { name: 'Mitra (2)' }));
            await syncUI();

            expect(screen.queryByTestId('user-row-1')).not.toBeInTheDocument();
            expect(screen.getByTestId('user-row-2')).toBeInTheDocument();
            expect(screen.getByTestId('user-row-3')).toBeInTheDocument();
        });
    });

    // ----------------------------------------------------------
    // 18.3 INTERACTION — Search & Debounce
    // ----------------------------------------------------------
    describe('Interaction — Search & Debounce', () => {
        it('menunda getUsers 300ms setelah input pencarian berubah', async () => {
            render(Index, {
                users: sampleUsers,
                filters: { search: '', per_page: 15 },
            });

            const searchInput = screen.getByPlaceholderText('Cari nama atau email...');
            await fireEvent.input(searchInput, { target: { value: 'mitra' } });

            expect(getUsers).not.toHaveBeenCalled();

            await vi.advanceTimersByTimeAsync(299);
            expect(getUsers).not.toHaveBeenCalled();

            await vi.advanceTimersByTimeAsync(1);

            expect(getUsers).toHaveBeenCalledTimes(1);
            expect(getUsers).toHaveBeenCalledWith(
                { search: 'mitra', per_page: 15 },
                { preserveState: true, preserveScroll: true, replace: true },
            );
        });

        it('langsung memanggil getUsers saat pencarian di-clear tanpa menunggu debounce', async () => {
            render(Index, {
                users: sampleUsers,
                filters: { search: 'admin', per_page: 15 },
            });

            await fireEvent.click(screen.getByRole('button', { name: 'Hapus Pencarian' }));
            await syncUI();

            expect(getUsers).toHaveBeenCalledTimes(1);
            expect(getUsers).toHaveBeenCalledWith(
                { search: '', per_page: 15 },
                { preserveState: true, preserveScroll: true, replace: true },
            );
        });
    });

    // ----------------------------------------------------------
    // 18.4 RENDERING — EmptyState edge case
    // ----------------------------------------------------------
    describe('Rendering — EmptyState', () => {
        it('menampilkan EmptyState default saat data kosong tanpa filter', () => {
            render(Index, {
                users: { ...sampleUsers, total: 0, data: [] },
                filters: { search: '', per_page: 15 },
            });

            const emptyState = screen.getByTestId('empty-state');
            expect(emptyState).toHaveTextContent('Belum ada user');
            expect(emptyState).toHaveTextContent(
                'Mulai dengan menambahkan user admin atau mitra pertama Anda.',
            );
            expect(screen.queryByRole('button', { name: 'Reset Filter' })).not.toBeInTheDocument();
        });

        it('menampilkan EmptyState hasil pencarian & reset filter saat diklik', async () => {
            render(Index, {
                users: { ...sampleUsers, total: 0, data: [] },
                filters: { search: 'tidakada', per_page: 15 },
            });

            const emptyState = screen.getByTestId('empty-state');
            expect(emptyState).toHaveTextContent('Tidak ada hasil ditemukan');
            expect(emptyState).toHaveTextContent(
                'Tidak ditemukan user dengan kata kunci "tidakada".',
            );

            await fireEvent.click(screen.getByRole('button', { name: 'Reset Filter' }));

            expect(getUsers).toHaveBeenCalledWith(
                { search: '', per_page: 15 },
                { preserveState: true, preserveScroll: true, replace: true },
            );
        });
    });

    // ----------------------------------------------------------
    // 18.5 EFFECT — Flash credentials
    // ----------------------------------------------------------
    describe('Effect — Flash Credentials', () => {
        it('membuka CredentialsModal otomatis saat flash.credentials tersedia', async () => {
            mockPage.props.flash = {
                credentials: {
                    name: 'Mitra Baru',
                    username: 'mitrabaru',
                    email: 'baru@test.com',
                    password: 'TempPassword123',
                } as Credentials,
            };

            render(Index, {
                users: sampleUsers,
                filters: { search: '', per_page: 15 },
            });
            await syncUI();

            expect(screen.getByTestId('credentials-modal')).toHaveTextContent(
                'Kredensial: mitrabaru / TempPassword123',
            );
        });
    });

    // ----------------------------------------------------------
    // 18.6 INTERACTION — Delete & Reset Password flows
    // ----------------------------------------------------------
    describe('Interaction — Delete & Reset Password', () => {
        it('membuka ConfirmModal, memanggil deleteUser, dan menonaktifkan tombol selama processing', async () => {
            let finishCallback: (() => void) | undefined;
            vi.mocked(deleteUser).mockImplementation((_id, options) => {
                finishCallback = options?.onFinish;
            });

            render(Index, {
                users: sampleUsers,
                filters: { search: '', per_page: 15 },
            });

            // 1. Klik "Hapus Mitra Satu" → modal terbuka
            await fireEvent.click(screen.getByRole('button', { name: 'Hapus Mitra Satu' }));
            await syncUI();

            const confirmModal = screen.getByTestId('confirm-modal');
            expect(confirmModal).toHaveAttribute('data-variant', 'danger');
            expect(confirmModal).toHaveTextContent('Hapus User?');
            expect(confirmModal).toHaveTextContent('Yakin ingin menghapus "Mitra Satu"?');

            // 2. Klik "Ya, Hapus" → deleteUser terpanggil
            await fireEvent.click(screen.getByRole('button', { name: 'Ya, Hapus' }));
            await syncUI();

            expect(deleteUser).toHaveBeenCalledWith(2, expect.any(Object));

            // 3. ✅ Query ULANG tombol setelah re-render → assert disabled
            expect(screen.getByRole('button', { name: 'Ya, Hapus' })).toBeDisabled();

            // 4. Panggil onFinish → processing = false & modal tertutup
            finishCallback?.();
            await syncUI();

            expect(screen.getByTestId('confirm-modal')).toBeEmptyDOMElement();
        });

        it('membuka ResetPasswordConfirmModal, memanggil resetUserPassword, lalu menutup modal', async () => {
            let finishCallback: (() => void) | undefined;
            vi.mocked(resetUserPassword).mockImplementation((_id, options) => {
                finishCallback = options?.onFinish;
            });

            render(Index, {
                users: sampleUsers,
                filters: { search: '', per_page: 15 },
            });

            // 1. Klik "Reset Mitra Satu" → modal terbuka
            await fireEvent.click(screen.getByRole('button', { name: 'Reset Mitra Satu' }));
            await syncUI();

            const resetModal = screen.getByTestId('reset-modal');
            expect(resetModal).toHaveTextContent(
                'Reset password untuk Mitra Satu (mitra1@test.com)',
            );

            // 2. Klik "Konfirmasi Reset" → API terpanggil
            await fireEvent.click(screen.getByRole('button', { name: 'Konfirmasi Reset' }));

            expect(resetUserPassword).toHaveBeenCalledWith(2, {
                preserveScroll: true,
                onFinish: expect.any(Function),
            });

            // 3. Panggil onFinish → modal tertutup
            finishCallback?.();
            await syncUI();

            expect(screen.getByTestId('reset-modal')).toBeEmptyDOMElement();
        });
    });
});