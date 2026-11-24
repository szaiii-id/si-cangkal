import { render, screen, fireEvent } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { createRawSnippet } from 'svelte';
import FormModal from '../FormModal.svelte';

// Helper untuk membuat snippet palsu
const mockSnippet = (text: string) =>
    createRawSnippet(() => ({
        render: () => `<div data-testid="mock-snippet">${text}</div>`,
    }));

describe('FormModal Component', () => {
    beforeEach(() => {
        document.body.style.overflow = '';
    });

    // ==========================================
    // 1. RENDERING & PROPS
    // ==========================================
    describe('Rendering', () => {
        it('TIDAK boleh merender apa pun jika open=false', () => {
            render(FormModal, {
                props: {
                    open: false,
                    title: 'Test',
                    onSubmit: vi.fn(),
                    children: mockSnippet('Form'),
                },
            });
            expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
        });

        it('harus merender title, description, dan children jika open=true', () => {
            render(FormModal, {
                props: {
                    open: true,
                    title: 'Tambah Data',
                    description: 'Isi form di bawah',
                    onSubmit: vi.fn(),
                    children: mockSnippet('Isi Form'),
                },
            });

            expect(screen.getByRole('dialog')).toBeInTheDocument();
            expect(screen.getByText('Tambah Data')).toBeInTheDocument();
            expect(screen.getByText('Isi form di bawah')).toBeInTheDocument();
            expect(screen.getByTestId('mock-snippet')).toBeInTheDocument();
        });
    });

    // ==========================================
    // 2. SUBMIT & CANCEL
    // ==========================================
    describe('Interaction', () => {
        it('harus memanggil onSubmit saat form disubmit', async () => {
            const onSubmit = vi.fn();
            render(FormModal, {
                props: {
                    open: true,
                    title: 'Test',
                    onSubmit,
                    children: mockSnippet('Form'),
                },
            });

            const form = screen.getByRole('dialog').querySelector('form')!;
            await fireEvent.submit(form);

            expect(onSubmit).toHaveBeenCalledTimes(1);
        });

        it('harus memanggil onCancel dan menutup modal saat tombol Batal diklik', async () => {
            const onCancel = vi.fn();
            render(FormModal, {
                props: {
                    open: true,
                    title: 'Test',
                    onSubmit: vi.fn(),
                    onCancel,
                    children: mockSnippet('Form'),
                },
            });

            await fireEvent.click(screen.getByText('Batal'));
            expect(onCancel).toHaveBeenCalledTimes(1);
        });

        it('harus menutup modal saat tombol Escape ditekan', async () => {
            const onCancel = vi.fn();
            render(FormModal, {
                props: {
                    open: true,
                    title: 'Test',
                    onSubmit: vi.fn(),
                    onCancel,
                    children: mockSnippet('Form'),
                },
            });

            await fireEvent.keyDown(window, { key: 'Escape' });
            expect(onCancel).toHaveBeenCalledTimes(1);
        });
    });

    // ==========================================
    // 3. PROCESSING STATE
    // ==========================================
    describe('Processing State', () => {
        it('harus menonaktifkan tombol dan menampilkan spinner saat processing=true', () => {
            render(FormModal, {
                props: {
                    open: true,
                    title: 'Test',
                    processing: true,
                    onSubmit: vi.fn(),
                    children: mockSnippet('Form'),
                },
            });

            const submitBtn = screen.getByText('Menyimpan...');
            expect(submitBtn).toBeDisabled();

            const cancelBtn = screen.getByText('Batal');
            expect(cancelBtn).toBeDisabled();
        });

        it('TIDAK boleh memanggil onSubmit jika processing=true', async () => {
            const onSubmit = vi.fn();
            render(FormModal, {
                props: {
                    open: true,
                    title: 'Test',
                    processing: true,
                    onSubmit,
                    children: mockSnippet('Form'),
                },
            });

            const form = screen.getByRole('dialog').querySelector('form')!;
            await fireEvent.submit(form);

            expect(onSubmit).not.toHaveBeenCalled();
        });
    });

    // ==========================================
    // 4. BODY SCROLL LOCK
    // ==========================================
    describe('Body Scroll Lock', () => {
        it('harus mengunci scroll body saat modal terbuka', () => {
            render(FormModal, {
                props: {
                    open: true,
                    title: 'Test',
                    onSubmit: vi.fn(),
                    children: mockSnippet('Form'),
                },
            });
            expect(document.body.style.overflow).toBe('hidden');
        });

        it('harus melepas scroll body saat modal ditutup (unmount)', () => {
            const { unmount } = render(FormModal, {
                props: {
                    open: true,
                    title: 'Test',
                    onSubmit: vi.fn(),
                    children: mockSnippet('Form'),
                },
            });
            unmount();
            expect(document.body.style.overflow).toBe('');
        });
    });
});
