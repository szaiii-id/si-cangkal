import { render, screen, fireEvent } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { createRawSnippet } from 'svelte';
import Button from '../Button.svelte';
import { router } from '@inertiajs/svelte';

// ==========================================
// MOCK INERTIA ROUTER
// ==========================================
vi.mock('@inertiajs/svelte', () => ({
    router: {
        get: vi.fn(),
        post: vi.fn(),
        put: vi.fn(),
        patch: vi.fn(),
        delete: vi.fn(),
    },
}));

// ==========================================
// HELPER: Buat Snippet valid untuk children
// ==========================================
const mockSnippet = (text: string) =>
    createRawSnippet(() => ({
        render: () => `<span>${text}</span>`,
    }));

describe('Button Component', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    // ==========================================
    // 1. RENDERING DASAR
    // ==========================================
    describe('Rendering', () => {
        it('harus merender tag <button> secara default', () => {
            render(Button, { props: { children: mockSnippet('Klik Saya') } });
            const btn = screen.getByRole('button');
            expect(btn).toBeInTheDocument();
            expect(btn.tagName).toBe('BUTTON');
            expect(screen.getByText('Klik Saya')).toBeInTheDocument();
        });

        it('harus merender tag <a> jika props href dan method="get"', () => {
            render(Button, {
                props: {
                    href: '/dashboard',
                    method: 'get',
                    children: mockSnippet('Link'),
                },
            });
            // Source menambahkan role="button" pada <a>, jadi query via button
            const link = screen.getByRole('button', { name: 'Link' });
            expect(link.tagName).toBe('A');
            expect(link).toHaveAttribute('href', '/dashboard');
            expect(link).toHaveAttribute('role', 'button');
        });

        it('harus menerapkan class variant dan size dengan benar', () => {
            render(Button, {
                props: {
                    variant: 'danger',
                    size: 'lg',
                    children: mockSnippet('Hapus'),
                },
            });
            const btn = screen.getByRole('button');
            expect(btn).toHaveClass('bg-red-600');
            expect(btn).toHaveClass('px-8');
        });
    });

    // ==========================================
    // 2. STATE: DISABLED & LOADING
    // ==========================================
    describe('State Disabled & Loading', () => {
        it('harus menonaktifkan tombol jika disabled=true', () => {
            render(Button, {
                props: { disabled: true, children: mockSnippet('Disabled') },
            });
            const btn = screen.getByRole('button');
            expect(btn).toBeDisabled();
            expect(btn).toHaveClass('pointer-events-none');
        });

        it('harus menampilkan spinner dan menonaktifkan tombol jika loading=true', () => {
            render(Button, {
                props: { loading: true, children: mockSnippet('Loading') },
            });
            const btn = screen.getByRole('button');
            expect(btn).toBeDisabled();
            expect(btn.querySelector('svg')).toBeInTheDocument();
        });

        it('harus mencegah klik jika loading=true', async () => {
            const handleClick = vi.fn();
            render(Button, {
                props: {
                    loading: true,
                    onclick: handleClick,
                    children: mockSnippet('Loading'),
                },
            });

            const btn = screen.getByRole('button');
            await fireEvent.click(btn);

            expect(handleClick).not.toHaveBeenCalled();
        });
    });

    // ==========================================
    // 3. INTERAKSI & INERTIA ROUTER
    // ==========================================
    describe('Interaction & Router', () => {
        it('harus memanggil callback onclick saat diklik', async () => {
            const handleClick = vi.fn();
            render(Button, {
                props: { onclick: handleClick, children: mockSnippet('Klik') },
            });

            await fireEvent.click(screen.getByRole('button'));
            expect(handleClick).toHaveBeenCalledTimes(1);
        });

        it('harus memanggil router.post jika method="post"', async () => {
            render(Button, {
                props: {
                    href: '/logout',
                    method: 'post',
                    children: mockSnippet('Logout'),
                },
            });

            await fireEvent.click(screen.getByRole('button'));

            expect(router.post).toHaveBeenCalledWith('/logout');
        });

        it('TIDAK boleh memanggil router jika link <a> dalam kondisi loading', async () => {
            render(Button, {
                props: {
                    href: '/dashboard',
                    method: 'get',
                    loading: true,
                    children: mockSnippet('Loading'),
                },
            });

            // Source menambahkan role="button" pada <a>
            const link = screen.getByRole('button', { name: 'Loading' });
            await fireEvent.click(link);

            expect(link).toHaveAttribute('aria-disabled', 'true');
            expect(link).toHaveAttribute('tabindex', '-1');
        });
    });
});
