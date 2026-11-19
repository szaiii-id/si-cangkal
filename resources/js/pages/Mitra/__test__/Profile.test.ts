/**
 * ============================================================
 * Mitra Profile — Component Test
 * ============================================================
 *
 * Fokus pengujian:
 *   1. Isolation & Mocking  → Inertia, menu, & child components
 *                             (DashboardLayout, PageHeader,
 *                             ProfileInfoForm, BankAccountManager)
 *                             di-mock anchor-aware.
 *   2. Props & Rendering    → PageHeader, ProfileInfoForm, BankAccountManager.
 *   3. Interaction          → (tidak ada interaksi langsung di parent).
 *
 * Strategi:
 *   - Parent fokus meneruskan props ke child.
 *   - Assertion dilakukan via `data-*` attribute yang di-set oleh mock child.
 * ============================================================
 */

import { render, screen } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import Profile from '../Profile.svelte';

// ============================================================
// 1. MOCK — INERTIA
// ============================================================
vi.mock('@inertiajs/svelte', () => ({
    inertia: vi.fn(),
}));

// ============================================================
// 2. MOCK — MENUS
// ============================================================
vi.mock('@/utils/menus', () => ({
    mitraMenu: [],
}));

// ============================================================
// 3. MOCK — LAYOUT & PAGE HEADER
// ============================================================
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

// ============================================================
// 4. MOCK — CHILD COMPONENTS
// ============================================================
vi.mock('@/components/mitra/ProfileInfoForm.svelte', () => ({
    default: (anchor: Node, props: { profile: any }) => {
        const el = document.createElement('div');
        el.setAttribute('data-testid', 'profile-info-form');
        el.setAttribute('data-profile-id', String(props.profile?.id ?? 'null'));
        el.textContent = `Profile: ${props.profile?.nama_penyedia_usaha ?? 'null'}`;
        anchor.parentNode?.insertBefore(el, anchor);
        return {};
    },
}));

vi.mock('@/components/mitra/BankAccountManager.svelte', () => ({
    default: (anchor: Node, props: { bankAccounts: any[] }) => {
        const el = document.createElement('div');
        el.setAttribute('data-testid', 'bank-account-manager');
        el.setAttribute('data-accounts-count', String(props.bankAccounts?.length ?? 0));
        el.textContent = `${props.bankAccounts?.length ?? 0} rekening`;
        anchor.parentNode?.insertBefore(el, anchor);
        return {};
    },
}));

// ============================================================
// 5. FIXTURES
// ============================================================
const makeProfile = (overrides = {}) => ({
    id: 1,
    nama_penyedia_usaha: 'PT Mitra Jaya',
    nomor_identitas: '1234567890',
    alamat: 'Jl. Merdeka No. 1',
    telepon: '+62 812 3456 7890',
    provinsi: 'Bali',
    kota: 'Denpasar',
    kode_pos: '80119',
    logo: 'logos/mitra.jpg',
    deskripsi: 'Travel agent terpercaya',
    ...overrides,
});

const makeBankAccounts = () => [
    { id: 1, tipe: 'bank', nama_penyedia: 'BCA', nomor_rekening: '123456789', nama_pemilik: 'PT Mitra Jaya' },
];

const renderPage = (props: any = {}) =>
    render(Profile, {
        props: {
            profile: makeProfile(),
            bankAccounts: makeBankAccounts(),
            ...props,
        },
    });

// ============================================================
// 6. TEST SUITE
// ============================================================
describe('Mitra Profile Page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    // ----------------------------------------------------------
    // 6.1 RENDERING — Header
    // ----------------------------------------------------------
    describe('Rendering — Header', () => {
        it('menampilkan judul & deskripsi halaman', () => {
            renderPage();

            expect(screen.getByTestId('page-header')).toHaveTextContent('Profil Usaha');
            expect(screen.getByText('Kelola informasi usaha dan rekening pembayaran Anda.')).toBeInTheDocument();
        });
    });

    // ----------------------------------------------------------
    // 6.2 RENDERING — Child Components
    // ----------------------------------------------------------
    describe('Rendering — Child Components', () => {
        it('merender ProfileInfoForm', () => {
            renderPage();
            expect(screen.getByTestId('profile-info-form')).toBeInTheDocument();
        });

        it('meneruskan profile ke ProfileInfoForm', () => {
            renderPage();
            const infoForm = screen.getByTestId('profile-info-form');
            expect(infoForm).toHaveAttribute('data-profile-id', '1');
            expect(infoForm).toHaveTextContent('PT Mitra Jaya');
        });

        it('merender BankAccountManager', () => {
            renderPage();
            expect(screen.getByTestId('bank-account-manager')).toBeInTheDocument();
        });

        it('meneruskan bankAccounts ke BankAccountManager', () => {
            renderPage();
            const manager = screen.getByTestId('bank-account-manager');
            expect(manager).toHaveAttribute('data-accounts-count', '1');
            expect(manager).toHaveTextContent('1 rekening');
        });
    });

    // ----------------------------------------------------------
    // 6.3 EDGE CASE
    // ----------------------------------------------------------
    describe('Edge Cases', () => {
        it('handle profile=null', () => {
            renderPage({ profile: null });

            const infoForm = screen.getByTestId('profile-info-form');
            expect(infoForm).toHaveAttribute('data-profile-id', 'null');
            expect(infoForm).toHaveTextContent('Profile: null');
        });

        it('handle bankAccounts kosong', () => {
            renderPage({ bankAccounts: [] });

            const manager = screen.getByTestId('bank-account-manager');
            expect(manager).toHaveAttribute('data-accounts-count', '0');
            expect(manager).toHaveTextContent('0 rekening');
        });

        it('handle bankAccounts default (tidak dipass)', () => {
            render(Profile, {
                props: {
                    profile: makeProfile(),
                },
            });

            const manager = screen.getByTestId('bank-account-manager');
            expect(manager).toHaveAttribute('data-accounts-count', '0');
        });
    });
});