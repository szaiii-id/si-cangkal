/**
 * ============================================================
 * ResetPasswordConfirmModal — Component Test
 * ============================================================
 *
 * Fokus pengujian (sesuai ketentuan):
 *   1. Isolation & Mocking  → Komponen leaf murni (tanpa Inertia,
 *                             tanpa API, tanpa Clipboard) → tidak
 *                             butuh mock external.
 *   2. Props & Rendering    → render kondisi `open`, nama & email,
 *                             kontrak UI (heading, warning, tombol).
 *   3. Interaction          → close (Batal & backdrop), confirm
 *                             (callback).
 *   4. Reactivity           → transisi open true ⇄ false update DOM.
 *
 * Strategi:
 *   - A11y-first query: `getByRole('dialog')`, `getByRole('button')`,
 *     `getByLabelText('Tutup modal')`.
 *   - Exact match `name: 'Batal'` / `name: 'Ya, Reset Password'` untuk
 *     hindari bentrok dengan `aria-label="Tutup modal"` di backdrop.
 *   - Callback di-verifikasi via `vi.fn()` (spy).
 * ============================================================
 */

import { render, screen, fireEvent } from '@testing-library/svelte';
import { describe, it, expect, vi } from 'vitest';
import ResetPasswordConfirmModal from '../ResetPasswordConfirmModal.svelte';

// ============================================================
// 1. HELPERS
// ============================================================
const renderModal = (props: {
    open?: boolean;
    userName?: string;
    userEmail?: string;
    onConfirm?: () => void;
}) =>
    render(ResetPasswordConfirmModal, {
        props: {
            open: true,
            userName: 'Mitra Satu',
            userEmail: 'mitra1@test.com',
            onConfirm: vi.fn(),
            ...props,
        },
    });

// ============================================================
// 2. TEST SUITE
// ============================================================
describe('ResetPasswordConfirmModal', () => {
    // ----------------------------------------------------------
    // 2.1 CONDITIONAL RENDERING
    // ----------------------------------------------------------
    describe('Rendering — Conditional', () => {
        it('TIDAK render modal saat open=false', () => {
            renderModal({ open: false });
            expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
        });

        it('render modal saat open=true', () => {
            renderModal({ open: true });
            expect(screen.getByRole('dialog')).toBeInTheDocument();
        });

        it('TIDAK render backdrop saat open=false', () => {
            renderModal({ open: false });
            expect(screen.queryByLabelText('Tutup modal')).not.toBeInTheDocument();
        });

        it('render backdrop saat open=true', () => {
            renderModal({ open: true });
            expect(screen.getByLabelText('Tutup modal')).toBeInTheDocument();
        });
    });

    // ----------------------------------------------------------
    // 2.2 UI CONTRACT
    // ----------------------------------------------------------
    describe('Rendering — UI Contract', () => {
        it('menampilkan heading "Reset Password?"', () => {
            renderModal({});
            expect(
                screen.getByRole('heading', { name: 'Reset Password?' }),
            ).toBeInTheDocument();
        });

        it('menampilkan pesan konfirmasi', () => {
            renderModal({});
            expect(
                screen.getByText(/Yakin ingin mereset password user berikut/i),
            ).toBeInTheDocument();
        });

        it('menampilkan info "Password baru akan ditampilkan sekali"', () => {
            renderModal({});
            expect(
                screen.getByText(/Password baru akan/i),
            ).toBeInTheDocument();
            expect(
                screen.getByText(/ditampilkan sekali/i),
            ).toBeInTheDocument();
        });

        it('menampilkan info "user harus menggantinya saat login"', () => {
            renderModal({});
            expect(
                screen.getByText(/user harus menggantinya saat login/i),
            ).toBeInTheDocument();
        });

        it('menampilkan tombol "Batal"', () => {
            renderModal({});
            expect(screen.getByRole('button', { name: 'Batal' })).toBeInTheDocument();
        });

        it('menampilkan tombol "Ya, Reset Password"', () => {
            renderModal({});
            expect(
                screen.getByRole('button', { name: 'Ya, Reset Password' }),
            ).toBeInTheDocument();
        });

        it('tombol confirm punya class warna sun-dark (palet brand)', () => {
            renderModal({});
            const confirmBtn = screen.getByRole('button', { name: 'Ya, Reset Password' });
            expect(confirmBtn).toHaveClass('bg-sun-dark');
        });
    });

    // ----------------------------------------------------------
    // 2.3 USER INFO DISPLAY
    // ----------------------------------------------------------
    describe('Rendering — User Info', () => {
        it('menampilkan nama user', () => {
            renderModal({ userName: 'Budi Santoso' });
            expect(screen.getByText('Budi Santoso')).toBeInTheDocument();
        });

        it('menampilkan email user', () => {
            renderModal({ userEmail: 'budi@test.com' });
            expect(screen.getByText('budi@test.com')).toBeInTheDocument();
        });

        it('nama user pakai class font-bold', () => {
            renderModal({ userName: 'Budi' });
            expect(screen.getByText('Budi')).toHaveClass('font-bold');
        });

        it('email user pakai class text-xs', () => {
            renderModal({ userEmail: 'budi@test.com' });
            expect(screen.getByText('budi@test.com')).toHaveClass('text-xs');
        });

        it('handle userName default (string kosong)', () => {
            renderModal({ userName: '' });
            // Elemen <p> kosong tetap ada di DOM
            const dialog = screen.getByRole('dialog');
            expect(dialog).toBeInTheDocument();
        });

        it('handle email user berbeda', () => {
            renderModal({ userEmail: 'other@domain.co.id' });
            expect(screen.getByText('other@domain.co.id')).toBeInTheDocument();
        });
    });

    // ----------------------------------------------------------
    // 2.4 INTERACTION — Cancel
    // ----------------------------------------------------------
    describe('Interaction — Cancel', () => {
        it('klik tombol "Batal" menutup modal', async () => {
            renderModal({});
            await fireEvent.click(screen.getByRole('button', { name: 'Batal' }));
            expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
        });

        it('klik backdrop menutup modal', async () => {
            renderModal({});
            await fireEvent.click(screen.getByLabelText('Tutup modal'));
            expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
        });

        it('klik "Batal" TIDAK memanggil onConfirm', async () => {
            const onConfirm = vi.fn();
            renderModal({ onConfirm });
            await fireEvent.click(screen.getByRole('button', { name: 'Batal' }));
            expect(onConfirm).not.toHaveBeenCalled();
        });

        it('klik backdrop TIDAK memanggil onConfirm', async () => {
            const onConfirm = vi.fn();
            renderModal({ onConfirm });
            await fireEvent.click(screen.getByLabelText('Tutup modal'));
            expect(onConfirm).not.toHaveBeenCalled();
        });
    });

    // ----------------------------------------------------------
    // 2.5 INTERACTION — Confirm
    // ----------------------------------------------------------
    describe('Interaction — Confirm', () => {
        it('klik "Ya, Reset Password" memanggil onConfirm', async () => {
            const onConfirm = vi.fn();
            renderModal({ onConfirm });
            await fireEvent.click(
                screen.getByRole('button', { name: 'Ya, Reset Password' }),
            );
            expect(onConfirm).toHaveBeenCalledOnce();
        });

        it('klik "Ya, Reset Password" TIDAK menutup modal otomatis', async () => {
            // Parent bertanggung jawab menutup setelah async selesai
            const onConfirm = vi.fn();
            renderModal({ onConfirm });
            await fireEvent.click(
                screen.getByRole('button', { name: 'Ya, Reset Password' }),
            );
            expect(screen.getByRole('dialog')).toBeInTheDocument();
        });

        it('klik "Ya, Reset Password" berkali-kali memanggil onConfirm berkali-kali', async () => {
            const onConfirm = vi.fn();
            renderModal({ onConfirm });
            const btn = screen.getByRole('button', { name: 'Ya, Reset Password' });
            await fireEvent.click(btn);
            await fireEvent.click(btn);
            await fireEvent.click(btn);
            expect(onConfirm).toHaveBeenCalledTimes(3);
        });

        it('TIDAK error kalau onConfirm undefined', async () => {
            renderModal({ onConfirm: undefined });
            await expect(
                fireEvent.click(screen.getByRole('button', { name: 'Ya, Reset Password' })),
            ).resolves.not.toThrow();
        });
    });

    // ----------------------------------------------------------
    // 2.6 A11Y
    // ----------------------------------------------------------
    describe('Accessibility', () => {
        it('dialog punya aria-modal="true"', () => {
            renderModal({});
            expect(screen.getByRole('dialog')).toHaveAttribute('aria-modal', 'true');
        });

        it('dialog punya aria-labelledby yang mengarah ke heading', () => {
            renderModal({});
            const dialog = screen.getByRole('dialog');
            const labelledBy = dialog.getAttribute('aria-labelledby');
            expect(labelledBy).toBe('reset-password-modal-title');
            expect(document.getElementById(labelledBy!)).toHaveTextContent('Reset Password?');
        });

        it('backdrop punya aria-label "Tutup modal"', () => {
            renderModal({});
            expect(screen.getByLabelText('Tutup modal')).toBeInTheDocument();
        });

        it('heading pakai h2', () => {
            renderModal({});
            expect(
                screen.getByRole('heading', { level: 2, name: 'Reset Password?' }),
            ).toBeInTheDocument();
        });
    });

    // ----------------------------------------------------------
    // 2.7 REACTIVITY
    // ----------------------------------------------------------
    describe('Reactivity — Open Transitions', () => {
        it('berubah dari open=false ke open=true menampilkan modal', async () => {
            const { rerender } = renderModal({ open: false });
            expect(screen.queryByRole('dialog')).not.toBeInTheDocument();

            await rerender({ open: true });
            expect(screen.getByRole('dialog')).toBeInTheDocument();
        });

        it('berubah dari open=true ke open=false menyembunyikan modal', async () => {
            const { rerender } = renderModal({ open: true });
            expect(screen.getByRole('dialog')).toBeInTheDocument();

            await rerender({ open: false });
            expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
        });

        it('onConfirm baru tetap bekerja setelah re-render', async () => {
            const firstConfirm = vi.fn();
            const secondConfirm = vi.fn();

            const { rerender } = renderModal({ onConfirm: firstConfirm });
            await fireEvent.click(
                screen.getByRole('button', { name: 'Ya, Reset Password' }),
            );
            expect(firstConfirm).toHaveBeenCalledOnce();

            await rerender({ onConfirm: secondConfirm });
            await fireEvent.click(
                screen.getByRole('button', { name: 'Ya, Reset Password' }),
            );
            expect(secondConfirm).toHaveBeenCalledOnce();
        });
    });

    // ----------------------------------------------------------
    // 2.8 EDGE CASES
    // ----------------------------------------------------------
    describe('Edge Cases', () => {
        it('handle userName dengan karakter khusus', () => {
            renderModal({ userName: "O'Brien & Sons" });
            expect(screen.getByText("O'Brien & Sons")).toBeInTheDocument();
        });

        it('handle userName dengan emoji', () => {
            renderModal({ userName: '🚀 Rocket User 🚀' });
            expect(screen.getByText('🚀 Rocket User 🚀')).toBeInTheDocument();
        });

        it('handle email dengan subdomain', () => {
            renderModal({ userEmail: 'user@mail.sub.domain.co.id' });
            expect(screen.getByText('user@mail.sub.domain.co.id')).toBeInTheDocument();
        });

        it('modal punya max-width class max-w-md', () => {
            renderModal({});
            expect(screen.getByRole('dialog')).toHaveClass('max-w-md');
        });
    });
});