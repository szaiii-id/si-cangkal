import { render, screen, fireEvent } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import UserTable from '../UserTable.svelte';

// ==========================================
// MOCK INERTIA
// ==========================================
vi.mock('@inertiajs/svelte', () => ({
    inertia: vi.fn(),
}));

// ==========================================
// MOCK DATA
// ==========================================
const createMockUser = (overrides = {}) => ({
    id: '1',
    name: 'Budi Santoso',
    username: 'budi',
    email: 'budi@test.com',
    role: 'admin' as const,
    created_at: '2026-10-16T10:00:00Z',
    profile: null,
    ...overrides,
});

describe('UserTable Component', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    // ==========================================
    // 1. RENDERING DASAR
    // ==========================================
    describe('Rendering', () => {
        it('harus merender nama dan email user', () => {
            render(UserTable, {
                props: {
                    users: [createMockUser()],
                    authUserId: '99',
                    onDelete: vi.fn(),
                    onReset: vi.fn(),
                },
            });

            // Nama muncul di mobile & desktop (getAllByText)
            expect(screen.getAllByText('Budi Santoso').length).toBeGreaterThan(
                0,
            );
            expect(screen.getAllByText('budi@test.com').length).toBeGreaterThan(
                0,
            );
        });

        it('harus menampilkan role badge yang sesuai', () => {
            render(UserTable, {
                props: {
                    users: [createMockUser({ role: 'mitra' })],
                    authUserId: '99',
                    onDelete: vi.fn(),
                    onReset: vi.fn(),
                },
            });

            expect(screen.getAllByText('Mitra').length).toBeGreaterThan(0);
        });
    });

    // ==========================================
    // 2. ACTION BUTTONS (ROLE-BASED)
    // ==========================================
    describe('Action Buttons', () => {
        it('harus menampilkan tombol Edit & Reset untuk role admin', () => {
            render(UserTable, {
                props: {
                    users: [createMockUser({ id: '1', role: 'admin' })],
                    authUserId: '99',
                    onDelete: vi.fn(),
                    onReset: vi.fn(),
                },
            });

            expect(screen.getAllByText('Edit').length).toBeGreaterThan(0);
            expect(screen.getAllByText('Reset').length).toBeGreaterThan(0);
        });

        it('harus menampilkan tombol Detail untuk role mitra', () => {
            render(UserTable, {
                props: {
                    users: [createMockUser({ id: '2', role: 'mitra' })],
                    authUserId: '99',
                    onDelete: vi.fn(),
                    onReset: vi.fn(),
                },
            });

            expect(screen.getAllByText('Detail').length).toBeGreaterThan(0);
        });

        it('TIDAK menampilkan tombol Edit/Reset untuk role client', () => {
            render(UserTable, {
                props: {
                    users: [createMockUser({ id: '3', role: 'client' })],
                    authUserId: '99',
                    onDelete: vi.fn(),
                    onReset: vi.fn(),
                },
            });

            expect(screen.queryByText('Edit')).not.toBeInTheDocument();
            expect(screen.queryByText('Reset')).not.toBeInTheDocument();
        });

        it('TIDAK menampilkan tombol Hapus jika user adalah diri sendiri (authUserId)', () => {
            render(UserTable, {
                props: {
                    users: [createMockUser({ id: '99' })],
                    authUserId: '99',
                    onDelete: vi.fn(),
                    onReset: vi.fn(),
                },
            });

            expect(screen.queryByText('Hapus')).not.toBeInTheDocument();
        });

        it('menampilkan tombol Hapus jika user bukan diri sendiri', () => {
            render(UserTable, {
                props: {
                    users: [createMockUser({ id: '1' })],
                    authUserId: '99',
                    onDelete: vi.fn(),
                    onReset: vi.fn(),
                },
            });

            expect(screen.getAllByText('Hapus').length).toBeGreaterThan(0);
        });
    });

    // ==========================================
    // 3. INTERAKSI
    // ==========================================
    describe('User Interaction', () => {
        it('harus memanggil onDelete saat tombol Hapus diklik', async () => {
            const onDelete = vi.fn();
            render(UserTable, {
                props: {
                    users: [createMockUser({ id: '1', name: 'Budi' })],
                    authUserId: '99',
                    onDelete,
                    onReset: vi.fn(),
                },
            });

            // Ambil tombol Hapus pertama (versi mobile)
            const deleteBtns = screen.getAllByText('Hapus');
            await fireEvent.click(deleteBtns[0]);

            expect(onDelete).toHaveBeenCalledWith({ id: '1', name: 'Budi' });
        });

        it('harus memanggil onReset saat tombol Reset diklik', async () => {
            const onReset = vi.fn();
            render(UserTable, {
                props: {
                    users: [
                        createMockUser({
                            id: '1',
                            name: 'Budi',
                            email: 'budi@test.com',
                        }),
                    ],
                    authUserId: '99',
                    onDelete: vi.fn(),
                    onReset,
                },
            });

            const resetBtns = screen.getAllByText('Reset');
            await fireEvent.click(resetBtns[0]);

            expect(onReset).toHaveBeenCalledWith({
                id: '1',
                name: 'Budi',
                email: 'budi@test.com',
            });
        });
    });

    // ==========================================
    // 4. AKSESIBILITAS (A11Y)
    // ==========================================
    describe('Accessibility', () => {
        it('tabel harus memiliki aria-label', () => {
            render(UserTable, {
                props: {
                    users: [createMockUser()],
                    authUserId: '99',
                    onDelete: vi.fn(),
                    onReset: vi.fn(),
                },
            });

            const table = screen.getByRole('table', {
                name: /Daftar Pengguna/i,
            });
            expect(table).toBeInTheDocument();
        });
    });
});
