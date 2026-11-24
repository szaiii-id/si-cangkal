import { render, screen } from '@testing-library/svelte';
import { describe, it, expect } from 'vitest';
import { createRawSnippet } from 'svelte';
import PageHeader from '../PageHeader.svelte';

describe('PageHeader Component', () => {
    // ==========================================
    // 1. RENDERING DASAR
    // ==========================================
    describe('Rendering', () => {
        it('harus merender judul dengan tag <h1>', () => {
            render(PageHeader, { props: { title: 'Paket Wisata' } });

            const heading = screen.getByRole('heading', { level: 1 });
            expect(heading).toBeInTheDocument();
            expect(heading).toHaveTextContent('Paket Wisata');
        });

        it('harus merender deskripsi jika diberikan', () => {
            render(PageHeader, {
                props: {
                    title: 'Paket Wisata',
                    description: 'Kelola semua paket wisata Anda.',
                },
            });

            expect(
                screen.getByText('Kelola semua paket wisata Anda.'),
            ).toBeInTheDocument();
        });

        it('TIDAK boleh merender deskripsi jika props description kosong', () => {
            const { container } = render(PageHeader, {
                props: { title: 'Test' },
            });

            // Pastikan hanya ada <h1>, tidak ada <p>
            const paragraphs = container.querySelectorAll('p');
            expect(paragraphs.length).toBe(0);
        });
    });

    // ==========================================
    // 2. ACTIONS SNIPPET
    // ==========================================
    describe('Actions Snippet', () => {
        it('harus merender tombol aksi jika snippet actions diberikan', () => {
            // Membuat snippet palsu untuk Svelte 5
            const mockActions = createRawSnippet(() => ({
                render: () =>
                    `<button data-testid="action-btn">Tambah Data</button>`,
            }));

            render(PageHeader, {
                props: { title: 'Test', actions: mockActions },
            });

            expect(screen.getByTestId('action-btn')).toBeInTheDocument();
            expect(screen.getByText('Tambah Data')).toBeInTheDocument();
        });

        it('TIDAK boleh merender apa pun jika snippet actions tidak diberikan', () => {
            const { container } = render(PageHeader, {
                props: { title: 'Test' },
            });

            // Pastikan tidak ada div pembungkus aksi
            // (Hanya div pembungkus judul yang ada)
            const divs = container.querySelectorAll('div');
            // Struktur minimal: 1 div luar, 1 div judul = 2 div
            expect(divs.length).toBe(2);
        });
    });
});
