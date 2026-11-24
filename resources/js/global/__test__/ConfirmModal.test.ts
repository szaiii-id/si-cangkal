import { render, screen, fireEvent } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import ConfirmModal from '../ConfirmModal.svelte';

describe('ConfirmModal Component', () => {
    beforeEach(() => {
        // Reset body overflow sebelum setiap test
        document.body.style.overflow = '';
    });

    // ==========================================
    // 1. RENDERING & PROPS
    // ==========================================
    describe('Rendering', () => {
        it('TIDAK boleh merender apa pun jika open=false', () => {
            render(ConfirmModal, {
                props: { open: false, title: 'Test', onConfirm: vi.fn() },
            });
            expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
        });

        it('harus merender title dan description jika open=true', () => {
            render(ConfirmModal, {
                props: {
                    open: true,
                    title: 'Hapus Data',
                    description: 'Tidak bisa dibatalkan',
                    onConfirm: vi.fn(),
                },
            });
            expect(screen.getByRole('dialog')).toBeInTheDocument();
            expect(screen.getByText('Hapus Data')).toBeInTheDocument();
            expect(
                screen.getByText('Tidak bisa dibatalkan'),
            ).toBeInTheDocument();
        });
    });

    // ==========================================
    // 2. USER INTERACTION
    // ==========================================
    describe('User Interaction', () => {
        it('harus memanggil onConfirm saat tombol konfirmasi diklik', async () => {
            const onConfirm = vi.fn();
            render(ConfirmModal, {
                props: { open: true, title: 'Test', onConfirm },
            });

            await fireEvent.click(screen.getByText('Ya, Lanjutkan'));
            expect(onConfirm).toHaveBeenCalledTimes(1);
        });

        it('harus memanggil onCancel dan menutup modal saat tombol Batal diklik', async () => {
            const onCancel = vi.fn();
            render(ConfirmModal, {
                props: {
                    open: true,
                    title: 'Test',
                    onConfirm: vi.fn(),
                    onCancel,
                },
            });

            await fireEvent.click(screen.getByText('Batal'));
            expect(onCancel).toHaveBeenCalledTimes(1);
            // Catatan: Untuk menguji `open` berubah, Anda harus menggunakan bindable di parent.
            // Di test ini kita hanya memverifikasi callback terpanggil.
        });

        it('harus menutup modal saat tombol Escape ditekan', async () => {
            const onCancel = vi.fn();
            render(ConfirmModal, {
                props: {
                    open: true,
                    title: 'Test',
                    onConfirm: vi.fn(),
                    onCancel,
                },
            });

            await fireEvent.keyDown(window, { key: 'Escape' });
            expect(onCancel).toHaveBeenCalledTimes(1);
        });
    });

    // ==========================================
    // 3. REASON VALIDATION
    // ==========================================
    describe('Reason Validation', () => {
        it('tombol konfirmasi harus disabled jika requiresReason=true dan alasan kosong', () => {
            render(ConfirmModal, {
                props: {
                    open: true,
                    title: 'Test',
                    onConfirm: vi.fn(),
                    requiresReason: true,
                },
            });
            expect(screen.getByText('Ya, Lanjutkan')).toBeDisabled();
        });

        it('harus menampilkan hint jika alasan kurang dari minReasonLength', async () => {
            render(ConfirmModal, {
                props: {
                    open: true,
                    title: 'Test',
                    onConfirm: vi.fn(),
                    requiresReason: true,
                    minReasonLength: 5,
                },
            });

            const textarea = screen.getByRole('textbox');
            await fireEvent.input(textarea, { target: { value: 'abc' } }); // 3 karakter

            expect(screen.getByText('Minimal 5 karakter.')).toBeInTheDocument();
            expect(screen.getByText('Ya, Lanjutkan')).toBeDisabled();
        });

        it('harus mengaktifkan tombol jika alasan memenuhi syarat', async () => {
            render(ConfirmModal, {
                props: {
                    open: true,
                    title: 'Test',
                    onConfirm: vi.fn(),
                    requiresReason: true,
                    minReasonLength: 5,
                },
            });

            const textarea = screen.getByRole('textbox');
            await fireEvent.input(textarea, {
                target: { value: 'Alasan yang valid' },
            });

            expect(screen.getByText('Ya, Lanjutkan')).not.toBeDisabled();
        });
    });

    // ==========================================
    // 4. BODY SCROLL LOCK (EFFECT)
    // ==========================================
    describe('Body Scroll Lock', () => {
        it('harus mengunci scroll body saat modal terbuka', () => {
            render(ConfirmModal, {
                props: { open: true, title: 'Test', onConfirm: vi.fn() },
            });
            expect(document.body.style.overflow).toBe('hidden');
        });

        it('harus melepas scroll body saat modal ditutup (unmount)', () => {
            const { unmount } = render(ConfirmModal, {
                props: { open: true, title: 'Test', onConfirm: vi.fn() },
            });
            unmount();
            expect(document.body.style.overflow).toBe('');
        });
    });
});
