import { render, screen, fireEvent } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import UserPasswordForm from '../UserPasswordForm.svelte';

// ==========================================
// MOCK CHILD COMPONENT (Criterion)
// ==========================================
vi.mock('@/global/Criterion.svelte', () => ({
    default: (props: any) => `
        <div data-testid="criterion-${props.label}">
            ${props.label}: ${props.passed ? 'passed' : 'failed'}
        </div>
    `,
}));

describe('UserPasswordForm Component', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    // ==========================================
    // 1. RENDERING DASAR
    // ==========================================
    describe('Rendering', () => {
        it('harus merender input password dan tombol Generate', () => {
            render(UserPasswordForm, { props: { password: '' } });

            // PERBAIKAN: Gunakan placeholder atau role, bukan label
            expect(screen.getByPlaceholderText('Minimal 8 karakter')).toBeInTheDocument();
            expect(screen.getByRole('button', { name: /Generate/i })).toBeInTheDocument();
        });

        it('TIDAK boleh menampilkan strength meter jika password kosong', () => {
            render(UserPasswordForm, { props: { password: '' } });

            expect(screen.queryByRole('progressbar')).not.toBeInTheDocument();
        });
    });

    // ==========================================
    // 2. STRENGTH METER
    // ==========================================
    describe('Strength Meter', () => {
        it('harus menampilkan strength meter jika password diisi', () => {
            render(UserPasswordForm, { props: { password: 'Abcdefgh123!' } });

            const progressBars = screen.getAllByRole('progressbar');
            expect(progressBars.length).toBe(4);
        });

        it('harus menampilkan label strength (Kuat/Sangat Kuat)', () => {
            render(UserPasswordForm, { props: { password: 'Abcdefgh123!' } });

            expect(screen.getByText(/Kuat/i)).toBeInTheDocument();
        });
    });

    // ==========================================
    // 3. SHOW/HIDE PASSWORD
    // ==========================================
    describe('Show/Hide Password', () => {
        it('harus mengubah tipe input dari password ke text saat tombol toggle diklik', async () => {
            render(UserPasswordForm, { props: { password: 'test' } });

            // PERBAIKAN: Gunakan placeholder atau role
            const input = screen.getByPlaceholderText('Minimal 8 karakter') as HTMLInputElement;
            expect(input.type).toBe('password');

            const toggleBtn = screen.getByRole('button', { name: /Tampilkan password/i });
            await fireEvent.click(toggleBtn);

            expect(input.type).toBe('text');
        });
    });

    // ==========================================
    // 4. GENERATE PASSWORD
    // ==========================================
    describe('Generate Password', () => {
        it('harus mengisi password dengan karakter acak saat tombol Generate diklik', async () => {
            render(UserPasswordForm, { props: { password: '' } });

            const generateBtn = screen.getByRole('button', { name: /Generate/i });
            await fireEvent.click(generateBtn);

            // PERBAIKAN: Gunakan placeholder atau role
            const input = screen.getByPlaceholderText('Minimal 8 karakter') as HTMLInputElement;
            expect(input.value.length).toBe(12);
        });
    });

    // ==========================================
    // 5. ERROR STATE
    // ==========================================
    describe('Error State', () => {
        it('harus menampilkan pesan error jika ada', () => {
            render(UserPasswordForm, { props: { password: '', error: 'Password wajib diisi' } });

            expect(screen.getByText('Password wajib diisi')).toBeInTheDocument();
        });

        it('pesan error harus memiliki role="alert"', () => {
            render(UserPasswordForm, { props: { password: '', error: 'Error' } });

            const errorMessage = screen.getByRole('alert');
            expect(errorMessage).toBeInTheDocument();
        });
    });
});