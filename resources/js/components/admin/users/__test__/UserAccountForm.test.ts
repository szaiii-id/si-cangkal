import { render, screen, fireEvent } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import UserAccountForm from '../UserAccountForm.svelte';

// ==========================================
// MOCK CHILD COMPONENT (Mengarah ke file fisik)
// ==========================================
vi.mock('../UserAccountFields.svelte', () => import('../__mocks__/UserAccountFields.svelte'));

// ==========================================
// MOCK DATA
// ==========================================
const createMockForm = (overrides = {}) => ({
    name: 'Budi Santoso',
    email: 'budi@test.com',
    username: 'budi',
    role: 'admin',
    password: '',
    ...overrides,
});

describe('UserAccountForm Component', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    // ==========================================
    // 1. RENDERING DASAR
    // ==========================================
    describe('Rendering', () => {
        it('harus merender judul "Tambah Pengguna Baru" jika isEdit=false', () => {
            render(UserAccountForm, { 
                props: { form: createMockForm(), errors: {}, isEdit: false, onSubmit: vi.fn() } 
            });

            expect(screen.getByText('Tambah Pengguna Baru')).toBeInTheDocument();
        });

        it('harus merender judul "Edit Pengguna" jika isEdit=true', () => {
            render(UserAccountForm, { 
                props: { form: createMockForm(), errors: {}, isEdit: true, onSubmit: vi.fn() } 
            });

            expect(screen.getByText('Edit Pengguna')).toBeInTheDocument();
        });

        it('harus merender UserAccountFields (mock)', () => {
            render(UserAccountForm, { 
                props: { form: createMockForm(), errors: {}, onSubmit: vi.fn() } 
            });

            expect(screen.getByTestId('user-account-fields')).toBeInTheDocument();
        });
    });

    // ==========================================
    // 2. ROLE SELECTION
    // ==========================================
    describe('Role Selection', () => {
        it('harus merender dropdown role dengan opsi admin, mitra, client', () => {
            render(UserAccountForm, { 
                props: { form: createMockForm(), errors: {}, onSubmit: vi.fn() } 
            });

            const select = screen.getByLabelText(/Role/i) as HTMLSelectElement;
            const options = Array.from(select.options).map(o => o.value);
            
            expect(options).toContain('admin');
            expect(options).toContain('mitra');
            expect(options).toContain('client');
        });
    });

    // ==========================================
    // 3. PASSWORD (CONDITIONAL)
    // ==========================================
    describe('Password Field', () => {
        it('harus menampilkan input password jika isEdit=false', () => {
            render(UserAccountForm, { 
                props: { form: createMockForm(), errors: {}, isEdit: false, onSubmit: vi.fn() } 
            });

            expect(screen.getByLabelText(/Password/i)).toBeInTheDocument();
        });

        it('TIDAK boleh menampilkan input password jika isEdit=true', () => {
            render(UserAccountForm, { 
                props: { form: createMockForm(), errors: {}, isEdit: true, onSubmit: vi.fn() } 
            });

            expect(screen.queryByLabelText(/Password/i)).not.toBeInTheDocument();
        });
    });

    // ==========================================
    // 4. SUBMIT
    // ==========================================
    describe('Form Submission', () => {
        it('harus memanggil onSubmit saat form disubmit', async () => {
            const onSubmit = vi.fn();
            render(UserAccountForm, { 
                props: { form: createMockForm(), errors: {}, onSubmit } 
            });

            const form = document.querySelector('form');
            await fireEvent.submit(form!);

            expect(onSubmit).toHaveBeenCalledTimes(1);
        });
    });

    // ==========================================
    // 5. LOADING STATE
    // ==========================================
    describe('Loading State', () => {
        it('harus menonaktifkan tombol dan menampilkan "Menyimpan..." saat processing=true', () => {
            render(UserAccountForm, { 
                props: { form: createMockForm(), errors: {}, processing: true, onSubmit: vi.fn() } 
            });

            const btn = screen.getByRole('button');
            expect(btn).toBeDisabled();
            expect(screen.getByText('Menyimpan...')).toBeInTheDocument();
        });
    });
});