/**
 * ============================================================
 * Client Profile — Component Test
 * ============================================================
 *
 * Fokus pengujian:
 *   1. Isolation & Mocking  → Inertia (router.put), toast,
 *                             hasFormChanges, ClientLayout.
 *   2. Props & Rendering    → header profil, avatar initial,
 *                             email read-only, form values.
 *   3. Interaction          → input change, reset, submit.
 *   4. Reactivity           → hasChanges derived, error state,
 *                             processing disabled buttons.
 * ============================================================
 */

import { render, screen, fireEvent } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import Profile from '../Profile.svelte';

// ============================================================
// 1. HOISTED MOCK REGISTRY
// ============================================================
const mockRouterPut = vi.hoisted(() => vi.fn());

// ============================================================
// 2. MOCK — INERTIA
// ============================================================
vi.mock('@inertiajs/svelte', () => ({
    router: { put: mockRouterPut },
}));

// ============================================================
// 3. MOCK — UTILS
// ============================================================
vi.mock('@/utils/toast.svelte', () => ({
    showToast: vi.fn(),
}));

vi.mock('@/utils/form', () => ({
    hasFormChanges: vi.fn((current: any, initial: any) => {
        return (
            current.name !== initial.name ||
            current.username !== initial.username
        );
    }),
}));

// ============================================================
// 4. MOCK — CLIENT LAYOUT
// ============================================================
vi.mock('@/components/layout/ClientLayout.svelte', () => ({
    default: (anchor: Node, props: { children?: (anchor: Node) => void }) => {
        props.children?.(anchor);
        return {};
    },
}));

// ============================================================
// 5. STATIC IMPORT
// ============================================================
import { showToast } from '@/utils/toast.svelte';

// ============================================================
// 6. FIXTURES
// ============================================================
const makeUser = (overrides = {}) => ({
    id: 1,
    name: 'Budi Santoso',
    username: 'budisan',
    email: 'budi@test.com',
    role: 'client',
    ...overrides,
});

const renderPage = (user = makeUser()) =>
    render(Profile, { props: { user } });

// ============================================================
// 7. TEST SUITE
// ============================================================
describe('Client Profile', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    // ----------------------------------------------------------
    // 7.1 RENDERING — Header
    // ----------------------------------------------------------
    describe('Rendering — Header', () => {
        it('menampilkan nama & username di header', () => {
            renderPage();
            expect(screen.getByRole('heading', { level: 2, name: 'Budi Santoso' })).toBeInTheDocument();
            expect(screen.getByText('@budisan')).toBeInTheDocument();
        });

        it('menampilkan email', () => {
            renderPage();
            expect(screen.getByText('budi@test.com')).toBeInTheDocument();
        });

        it('menampilkan badge "Client"', () => {
            renderPage();
            expect(screen.getByText('Client')).toBeInTheDocument();
        });

        it('menampilkan inisial nama di avatar', () => {
            renderPage();
            expect(screen.getByText('B')).toBeInTheDocument();
        });

        it('menampilkan fallback "?" saat nama kosong', () => {
            renderPage(makeUser({ name: '' }));
            expect(screen.getByText('?')).toBeInTheDocument();
        });

        it('menampilkan fallback "Client" saat nama kosong', () => {
            renderPage(makeUser({ name: '' }));
            expect(screen.getByRole('heading', { level: 2 })).toHaveTextContent('Client');
        });

        it('menampilkan fallback "@username" saat username kosong', () => {
            renderPage(makeUser({ username: null }));
            expect(screen.getByText('@username')).toBeInTheDocument();
        });
    });

    // ----------------------------------------------------------
    // 7.2 RENDERING — Form
    // ----------------------------------------------------------
    describe('Rendering — Form', () => {
        it('menampilkan input nama dengan value awal', () => {
            renderPage();
            const nameInput = screen.getByLabelText(/Nama Lengkap/i) as HTMLInputElement;
            expect(nameInput.value).toBe('Budi Santoso');
        });

        it('menampilkan input username dengan value awal', () => {
            renderPage();
            const usernameInput = screen.getByLabelText(/Username/i) as HTMLInputElement;
            expect(usernameInput.value).toBe('budisan');
        });

        it('menampilkan email sebagai read-only', () => {
            renderPage();
            const emailInput = screen.getByLabelText(/Email/i) as HTMLInputElement;
            expect(emailInput.readOnly).toBe(true);
            expect(emailInput.disabled).toBe(true);
            expect(emailInput.value).toBe('budi@test.com');
        });

        it('menampilkan badge "Read-only" di label email', () => {
            renderPage();
            expect(screen.getByText('Read-only')).toBeInTheDocument();
        });

        it('tombol Reset & Simpan disabled saat tidak ada perubahan', () => {
            renderPage();
            const resetBtn = screen.getByRole('button', { name: 'Reset' });
            const saveBtn = screen.getByRole('button', { name: /Simpan Perubahan/i });
            
            expect(resetBtn).toBeDisabled();
            expect(saveBtn).toBeDisabled();
        });
    });

    // ----------------------------------------------------------
    // 7.3 INTERACTION — Input Changes
    // ----------------------------------------------------------
    describe('Interaction — Input Changes', () => {
        it('mengetik nama membuat tombol Save & Reset aktif', async () => {
            renderPage();
            
            const nameInput = screen.getByLabelText(/Nama Lengkap/i) as HTMLInputElement;
            await fireEvent.input(nameInput, { target: { value: 'Budi Baru' } });

            const saveBtn = screen.getByRole('button', { name: /Simpan Perubahan/i });
            expect(saveBtn).not.toBeDisabled();

            const resetBtn = screen.getByRole('button', { name: 'Reset' });
            expect(resetBtn).not.toBeDisabled();
        });

        it('mengetik username membuat tombol Save aktif', async () => {
            renderPage();
            
            const usernameInput = screen.getByLabelText(/Username/i) as HTMLInputElement;
            await fireEvent.input(usernameInput, { target: { value: 'budi-baru' } });

            const saveBtn = screen.getByRole('button', { name: /Simpan Perubahan/i });
            expect(saveBtn).not.toBeDisabled();
        });

        it('tidak menandai perubahan jika hanya mengetik lalu hapus', async () => {
            renderPage();
            
            const nameInput = screen.getByLabelText(/Nama Lengkap/i) as HTMLInputElement;
            await fireEvent.input(nameInput, { target: { value: 'X' } });
            await fireEvent.input(nameInput, { target: { value: 'Budi Santoso' } });

            const saveBtn = screen.getByRole('button', { name: /Simpan Perubahan/i });
            expect(saveBtn).toBeDisabled();
        });
    });

    // ----------------------------------------------------------
    // 7.4 INTERACTION — Reset Form
    // ----------------------------------------------------------
    describe('Interaction — Reset Form', () => {
        it('klik Reset mengembalikan form ke nilai awal', async () => {
            renderPage();
            
            const nameInput = screen.getByLabelText(/Nama Lengkap/i) as HTMLInputElement;
            await fireEvent.input(nameInput, { target: { value: 'Budi Baru' } });

            const resetBtn = screen.getByRole('button', { name: 'Reset' });
            await fireEvent.click(resetBtn);

            expect(nameInput.value).toBe('Budi Santoso');
        });

        it('klik Reset mengosongkan error', async () => {
            renderPage();
            
            // Simulasi error muncul (dengan submit + onError)
            mockRouterPut.mockImplementationOnce((_url, _data, opts) => {
                opts.onError({ name: 'Error test' });
                opts.onFinish?.();
            });

            // First change + submit
            const nameInput = screen.getByLabelText(/Nama Lengkap/i) as HTMLInputElement;
            await fireEvent.input(nameInput, { target: { value: 'X' } });

            const saveBtn = screen.getByRole('button', { name: /Simpan Perubahan/i });
            await fireEvent.click(saveBtn);

            expect(screen.getByText('Error test')).toBeInTheDocument();

            // Reset
            const resetBtn = screen.getByRole('button', { name: 'Reset' });
            await fireEvent.click(resetBtn);

            expect(screen.queryByText('Error test')).not.toBeInTheDocument();
        });
    });

    // ----------------------------------------------------------
    // 7.5 INTERACTION — Submit
    // ----------------------------------------------------------
    describe('Interaction — Submit', () => {
        it('TIDAK submit jika tidak ada perubahan', async () => {
            renderPage();
            
            const saveBtn = screen.getByRole('button', { name: /Simpan Perubahan/i });
            await fireEvent.click(saveBtn);

            expect(mockRouterPut).not.toHaveBeenCalled();
        });

        it('memanggil router.put dengan payload & endpoint benar', async () => {
            renderPage();
            
            const nameInput = screen.getByLabelText(/Nama Lengkap/i) as HTMLInputElement;
            await fireEvent.input(nameInput, { target: { value: 'Budi Baru' } });

            mockRouterPut.mockImplementation(() => {}); // no-op

            const saveBtn = screen.getByRole('button', { name: /Simpan Perubahan/i });
            await fireEvent.click(saveBtn);

            expect(mockRouterPut).toHaveBeenCalledOnce();
            expect(mockRouterPut).toHaveBeenCalledWith(
                '/client/profile',
                { name: 'Budi Baru', username: 'budisan' },
                expect.objectContaining({ preserveScroll: true }),
            );
        });

        it('menampilkan "Menyimpan..." selama processing', async () => {
            renderPage();
            
            const nameInput = screen.getByLabelText(/Nama Lengkap/i) as HTMLInputElement;
            await fireEvent.input(nameInput, { target: { value: 'Budi Baru' } });

            // Mock tidak panggil callback → processing tetap true
            mockRouterPut.mockImplementation(() => {});

            const saveBtn = screen.getByRole('button', { name: /Simpan Perubahan/i });
            await fireEvent.click(saveBtn);

            await Promise.resolve();
            expect(screen.getByRole('button', { name: /Menyimpan/i })).toBeInTheDocument();
        });

        it('menampilkan toast success saat onSuccess', async () => {
            renderPage();
            
            const nameInput = screen.getByLabelText(/Nama Lengkap/i) as HTMLInputElement;
            await fireEvent.input(nameInput, { target: { value: 'Budi Baru' } });

            mockRouterPut.mockImplementation((_url, _data, opts) => {
                opts.onSuccess();
                opts.onFinish();
            });

            const saveBtn = screen.getByRole('button', { name: /Simpan Perubahan/i });
            await fireEvent.click(saveBtn);

            expect(showToast).toHaveBeenCalledWith(
                'Profil berhasil diperbarui.',
                'success',
            );
        });

        it('menampilkan error jika onError', async () => {
            renderPage();
            
            const nameInput = screen.getByLabelText(/Nama Lengkap/i) as HTMLInputElement;
            await fireEvent.input(nameInput, { target: { value: 'Budi Baru' } });

            mockRouterPut.mockImplementation((_url, _data, opts) => {
                opts.onError({ name: 'Nama sudah dipakai' });
                opts.onFinish();
            });

            const saveBtn = screen.getByRole('button', { name: /Simpan Perubahan/i });
            await fireEvent.click(saveBtn);

            expect(screen.getByText('Nama sudah dipakai')).toBeInTheDocument();
            expect(showToast).toHaveBeenCalledWith(
                'Gagal menyimpan profil. Periksa kembali.',
                'error',
            );
        });

        it('menampilkan error alert dengan count', async () => {
            renderPage();
            
            const nameInput = screen.getByLabelText(/Nama Lengkap/i) as HTMLInputElement;
            await fireEvent.input(nameInput, { target: { value: 'Budi Baru' } });

            mockRouterPut.mockImplementation((_url, _data, opts) => {
                opts.onError({ name: 'Error 1', username: 'Error 2' });
                opts.onFinish();
            });

            const saveBtn = screen.getByRole('button', { name: /Simpan Perubahan/i });
            await fireEvent.click(saveBtn);

            expect(screen.getByText(/Terdapat 2 kesalahan pada form/i)).toBeInTheDocument();
        });
    });
});