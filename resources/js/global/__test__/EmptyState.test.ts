import { render, screen, fireEvent } from '@testing-library/svelte';
import { describe, it, expect, vi } from 'vitest';
import EmptyState from '../EmptyState.svelte';
import { createRawSnippet } from 'svelte';

describe('EmptyState Component', () => {
    // ==========================================
    // 1. RENDERING DASAR
    // ==========================================
    describe('Rendering', () => {
        it('harus merender title dan description', () => {
            render(EmptyState, {
                props: { title: 'Tidak ada data', description: 'Silakan tambah data baru.' },
            });

            expect(screen.getByText('Tidak ada data')).toBeInTheDocument();
            expect(screen.getByText('Silakan tambah data baru.')).toBeInTheDocument();
        });

        it('harus merender ikon SVG default', () => {
            const { container } = render(EmptyState, { props: { title: 'Test' } });
            const svg = container.querySelector('svg');
            expect(svg).toBeInTheDocument();
            expect(svg).toHaveAttribute('aria-hidden', 'true');
        });
    });

    // ==========================================
    // 2. LOGIKA TOMBOL (Kondisional)
    // ==========================================
    describe('Action Buttons', () => {
        it('harus merender tombol "Hapus Pencarian" jika hasFilters=true', async () => {
            const onClear = vi.fn();
            render(EmptyState, {
                props: { title: 'Kosong', hasFilters: true, onClear, clearLabel: 'Reset Filter' },
            });

            const btn = screen.getByText('Reset Filter');
            expect(btn).toBeInTheDocument();

            await fireEvent.click(btn);
            expect(onClear).toHaveBeenCalledTimes(1);
        });

        it('harus merender tombol aksi tunggal jika actionLabel dan onAction diberikan', async () => {
            const onAction = vi.fn();
            render(EmptyState, {
                props: { title: 'Kosong', actionLabel: 'Tambah Data', onAction },
            });

            const btn = screen.getByText('Tambah Data');
            expect(btn).toBeInTheDocument();

            await fireEvent.click(btn);
            expect(onAction).toHaveBeenCalledTimes(1);
        });

        it('TIDAK boleh merender tombol apa pun jika tidak ada aksi yang diberikan', () => {
            render(EmptyState, { props: { title: 'Kosong' } });
            const btn = screen.queryByRole('button');
            expect(btn).not.toBeInTheDocument();
        });
    });

    // ==========================================
    // 3. PRIORITAS KONDISI (Edge Case)
    // ==========================================
    describe('Condition Priority', () => {
        it('tombol "hasFilters" harus menang jika keduanya (hasFilters & actionLabel) diberikan', () => {
            render(EmptyState, {
                props: {
                    title: 'Kosong',
                    hasFilters: true,
                    onClear: vi.fn(),
                    actionLabel: 'Tambah Data',
                    onAction: vi.fn(),
                },
            });

            // Tombol "Hapus Pencarian" harus muncul, "Tambah Data" tidak
            expect(screen.queryByText('Hapus Pencarian')).toBeInTheDocument();
            expect(screen.queryByText('Tambah Data')).not.toBeInTheDocument();
        });
    });

    // ==========================================
    // 4. CUSTOM SNIPPET (Svelte 5)
    // ==========================================
    describe('Custom Actions Snippet', () => {
        it('harus merender custom snippet jika diberikan', () => {
            // Membuat snippet palsu untuk Svelte 5
            const mockSnippet = createRawSnippet(() => ({
                render: () => `<button>Custom Button</button>`,
            }));

            render(EmptyState, {
                props: { title: 'Kosong', actions: mockSnippet },
            });

            expect(screen.getByText('Custom Button')).toBeInTheDocument();
        });
    });
});