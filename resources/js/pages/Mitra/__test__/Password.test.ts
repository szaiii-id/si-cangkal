/**
 * ============================================================
 * Mitra Password Page (Wrapper) — Component Test
 * ============================================================
 */

import { render, screen } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import Password from '../Password.svelte';

// ============================================================
// MOCK REGISTRY
// ============================================================
const mockPage = vi.hoisted(() => ({
    props: {
        auth: {
            user: { id: 1, name: 'Mitra Jaya', username: 'mitrajaya', email: 'mitra@test.com' },
        },
    },
}));

// ============================================================
// MOCK DEPENDENCIES
// ============================================================
vi.mock('@inertiajs/svelte', () => ({
    page: mockPage,
}));

vi.mock('@/utils/menus', () => ({
    mitraMenu: [],
}));

vi.mock('@/components/layout/DashboardLayout.svelte', () => ({
    default: (anchor: Node, props: { children?: (anchor: Node) => void }) => {
        props.children?.(anchor);
        return {};
    },
}));

vi.mock('@/global/PageHeader.svelte', () => ({
    default: (anchor: Node, props: { title: string; description?: string }) => {
        const el = document.createElement('div');
        el.setAttribute('data-testid', 'page-header');
        el.innerHTML = `<h1>${props.title}</h1><p>${props.description ?? ''}</p>`;
        anchor.parentNode?.insertBefore(el, anchor);
        return {};
    },
}));

vi.mock('@/components/mitra/PasswordForm.svelte', () => ({
    default: (anchor: Node) => {
        const el = document.createElement('div');
        el.setAttribute('data-testid', 'password-form');
        anchor.parentNode?.insertBefore(el, anchor);
        return {};
    },
}));

// ============================================================
// TEST SUITE
// ============================================================
describe('Mitra Password Page (Wrapper)', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        mockPage.props.auth = {
            user: { id: 1, name: 'Mitra Jaya', username: 'mitrajaya', email: 'mitra@test.com' },
        };
    });

    describe('Rendering — Header', () => {
        it('menampilkan judul & deskripsi halaman', () => {
            render(Password);

            expect(screen.getByTestId('page-header')).toHaveTextContent('Ganti Password');
            expect(
                screen.getByText('Pastikan password baru Anda kuat dan mudah diingat.'),
            ).toBeInTheDocument();
        });
    });

    describe('Rendering — User Card', () => {
        it('menampilkan nama, username, dan email user', () => {
            render(Password);

            expect(screen.getByText('Mitra Jaya')).toBeInTheDocument();
            expect(screen.getByText('@mitrajaya · mitra@test.com')).toBeInTheDocument();
        });

        it('menampilkan inisial user di avatar', () => {
            render(Password);
            expect(screen.getByText('M')).toBeInTheDocument();
        });

        it('menampilkan badge "KEAMANAN AKUN"', () => {
            render(Password);
            expect(screen.getByText('KEAMANAN AKUN')).toBeInTheDocument();
        });

        it('menampilkan fallback saat user null', () => {
            mockPage.props.auth = { user: null } as any;
            render(Password);

            expect(screen.getByText('User')).toBeInTheDocument();
            expect(screen.getByText('@user · -')).toBeInTheDocument();
            expect(screen.getByText('?')).toBeInTheDocument(); // fallback initial
        });
    });

    describe('Rendering — Child Components', () => {
        it('merender PasswordForm', () => {
            render(Password);
            expect(screen.getByTestId('password-form')).toBeInTheDocument();
        });
    });
});