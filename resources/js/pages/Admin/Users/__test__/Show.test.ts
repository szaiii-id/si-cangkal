/**
 * ============================================================
 * Admin Users — Show Page (Detail User) — Component Test
 * ============================================================
 *
 * Fokus pengujian (sesuai ketentuan):
 *   1. Isolation & Mocking  → Inertia, menu, formatCurrency helpers,
 *                             toast, & child components di-mock.
 *   2. Props & Rendering    → header, informasi usaha, rekening, meta.
 *   3. Interaction          → reveal/copy nomor rekening, toast trigger.
 *   4. Reactivity           → revealedAccounts state update DOM.
 *
 * Strategi:
 *   - Anchor-aware mock untuk DashboardLayout & BackButton.
 *   - Mock `@/utils/formatCurrency` → kontrol `maskAccountNumber`
 *     & `copyToClipboard` tanpa bergantung pada Clipboard API.
 *   - `within(card)` scoping untuk hindari bentrok query nomor
 *     identitas vs nomor rekening saat nomor sama.
 * ============================================================
 */

import { render, screen, fireEvent, within } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { tick } from 'svelte';
import Show from '../Show.svelte';

// ============================================================
// 1. HOISTED MOCK REGISTRY
// ============================================================
const mockUpdaters = vi.hoisted(() => new Set<() => void>());

// ============================================================
// 2. MOCK — INERTIA
// ============================================================
vi.mock('@inertiajs/svelte', () => ({
    inertia: vi.fn(),
}));

// ============================================================
// 3. MOCK — FORMAT CURRENCY (maskAccountNumber & copyToClipboard)
// ============================================================
vi.mock('@/utils/formatCurrency', () => ({
    maskAccountNumber: vi.fn((num: string | null | undefined) => {
        if (!num) return '-';
        const clean = num.replace(/\s+/g, '');
        if (clean.length <= 4) return '****';
        return `•••• •••• ${clean.slice(-4)}`;
    }),
    copyToClipboard: vi.fn(() => Promise.resolve()),
    formatCurrency: vi.fn(),
    formatNumber: vi.fn(),
    parseCurrency: vi.fn(),
}));

// ============================================================
// 4. MOCK — TOAST
// ============================================================
vi.mock('@/utils/toast.svelte', () => ({
    showToast: vi.fn(),
}));

// ============================================================
// 5. MOCK — MENUS
// ============================================================
vi.mock('@/utils/menus', () => ({
    adminMenu: [],
}));

// ============================================================
// 6. MOCK — DASHBOARD LAYOUT
// ============================================================
vi.mock('@/components/layout/DashboardLayout.svelte', () => ({
    default: (anchor: Node, props: { children?: (anchor: Node) => void }) => {
        props.children?.(anchor);
        return {};
    },
}));

// ============================================================
// 7. MOCK — BACK BUTTON
// ============================================================
vi.mock('@/global/BackButton.svelte', () => ({
    default: (anchor: Node, props: { href: string; label?: string }) => {
        const link = document.createElement('a');
        link.href = props.href;
        link.textContent = props.label ?? 'Kembali';
        link.setAttribute('data-testid', 'back-button');
        anchor.parentNode?.insertBefore(link, anchor);
        return {};
    },
}));

// ============================================================
// 8. STATIC IMPORT
// ============================================================
import { maskAccountNumber, copyToClipboard } from '@/utils/formatCurrency';
import { showToast } from '@/utils/toast.svelte';

// ============================================================
// 9. HELPERS
// ============================================================
async function syncUI(): Promise<void> {
    await tick();
    mockUpdaters.forEach((fn) => fn());
}

/**
 * Scope DOM ke card tertentu berdasarkan judul heading.
 * Menghindari ambiguity query antar card.
 */
function getCardByTitle(title: string): HTMLElement {
    const heading = screen.getByText(title);
    // Naik 2 level: h2 → wrapper header → card
    return heading.closest('.bg-white') as HTMLElement;
}

// ============================================================
// 10. FIXTURES
// ============================================================
const makeProfile = (overrides: Record<string, unknown> = {}) =>
    ({
        id: 1,
        user_id: '2',
        tipe: 'badan_usaha',
        logo: null,
        meta: null,
        nama_penyedia_usaha: 'PT Mitra Jaya',
        nomor_identitas: '1234567890',
        alamat: 'Jl. Merdeka No. 1',
        telepon: '+62 812 3456 7890',
        provinsi: 'Jawa Barat',
        kota: 'Bandung',
        kode_pos: '40111',
        deskripsi: 'Travel agent terpercaya',
        ...overrides,
    }) as any;

const makeBankAccount = (overrides: Record<string, unknown> = {}) =>
    ({
        id: 1,
        tipe: 'bank' as const,
        nama_penyedia: 'BCA',
        nomor_rekening: '1234567890',
        nama_pemilik: 'Mitra Satu',
        is_primary: true,
        ...overrides,
    }) as any;

const makeAdmin = (overrides: Record<string, unknown> = {}) =>
    ({
        id: 1,
        name: 'Super Admin',
        username: 'superadmin',
        email: 'admin@test.com',
        role: 'admin' as const,
        created_at: '2026-01-15T00:00:00Z',
        profile: null,
        bank_accounts: [],
        ...overrides,
    }) as any;

const makeMitra = (overrides: Record<string, unknown> = {}) =>
    ({
        id: 2,
        name: 'Mitra Satu',
        username: 'mitrasatu',
        email: 'mitra1@test.com',
        role: 'mitra' as const,
        created_at: '2026-01-15T00:00:00Z',
        profile: makeProfile(),
        bank_accounts: [makeBankAccount()],
        ...overrides,
    }) as any;

// ============================================================
// 11. TEST SUITE
// ============================================================
describe('Admin Users — Show Page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        mockUpdaters.clear();
    });

    // ----------------------------------------------------------
    // 11.1 RENDERING — Header
    // ----------------------------------------------------------
    describe('Rendering — Header', () => {
        it('menampilkan inisial, nama, email, dan role badge Admin', () => {
            render(Show, { user: makeAdmin() });

            expect(screen.getByText('S')).toBeInTheDocument();
            expect(
                screen.getByRole('heading', { name: 'Super Admin' }),
            ).toBeInTheDocument();
            expect(screen.getByText('admin@test.com')).toBeInTheDocument();
            expect(screen.getByText('Admin')).toBeInTheDocument();
        });

        it('menampilkan username dengan prefix @ jika ada', () => {
            render(Show, { user: makeAdmin() });
            expect(screen.getByText('@superadmin')).toBeInTheDocument();
        });

        it('menampilkan role badge Mitra untuk user mitra', () => {
            render(Show, { user: makeMitra() });

            expect(
                screen.getByRole('heading', { name: 'Mitra Satu' }),
            ).toBeInTheDocument();
            expect(screen.getByText('Mitra')).toBeInTheDocument();
        });

        it('menampilkan back button dengan href benar', () => {
            render(Show, { user: makeAdmin() });

            const backBtn = screen.getByTestId('back-button');
            expect(backBtn).toHaveAttribute('href', '/admin/users');
            expect(backBtn).toHaveTextContent('Kembali ke Daftar User');
        });

        it('menampilkan link Edit Profil dengan href benar', () => {
            render(Show, { user: makeAdmin() });

            const editLink = screen.getByRole('link', { name: 'Edit Profil' });
            expect(editLink).toHaveAttribute('href', '/admin/users/1/edit');
        });
    });

    // ----------------------------------------------------------
    // 11.2 RENDERING — Informasi Usaha
    // ----------------------------------------------------------
    describe('Rendering — Informasi Usaha', () => {
        it('menampilkan placeholder jika profile null', () => {
            render(Show, { user: makeAdmin() });
            expect(
                screen.getByText('Profil usaha belum dilengkapi.'),
            ).toBeInTheDocument();
        });

        it('merender data usaha lengkap saat profile ada', () => {
            render(Show, { user: makeMitra() });

            expect(screen.getByText('PT Mitra Jaya')).toBeInTheDocument();
            expect(screen.getByText('1234567890')).toBeInTheDocument();
            expect(screen.getByText('+62 812 3456 7890')).toBeInTheDocument();
            expect(screen.getByText('Jl. Merdeka No. 1')).toBeInTheDocument();
            expect(screen.getByText('Jawa Barat')).toBeInTheDocument();
            expect(screen.getByText('Bandung')).toBeInTheDocument();
            expect(screen.getByText('40111')).toBeInTheDocument();
            expect(
                screen.getByText('Travel agent terpercaya'),
            ).toBeInTheDocument();
        });

        it('menampilkan "-" untuk field yang kosong/null', () => {
            const userWithPartialProfile = makeMitra({
                profile: makeProfile({
                    nama_penyedia_usaha: '',
                    telepon: '',
                    deskripsi: '',
                }),
            });

            render(Show, { user: userWithPartialProfile });
            expect(screen.getAllByText('-').length).toBeGreaterThan(0);
        });
    });

    // ----------------------------------------------------------
    // 11.3 RENDERING — Rekening Bank
    // ----------------------------------------------------------
    describe('Rendering — Rekening Bank', () => {
        it('menampilkan placeholder jika bank_accounts kosong', () => {
            render(Show, { user: makeAdmin() });
            expect(screen.getByText('Belum ada rekening.')).toBeInTheDocument();
        });

        it('menampilkan nomor rekening yang di-mask secara default', () => {
            render(Show, { user: makeMitra() });

            expect(maskAccountNumber).toHaveBeenCalledWith('1234567890');
            expect(screen.getByText(/•••• •••• 7890/)).toBeInTheDocument();
        });

        it('menampilkan label "Bank" untuk tipe bank', () => {
            render(Show, { user: makeMitra() });
            expect(screen.getByText('Bank')).toBeInTheDocument();
        });

        it('menampilkan label "E-Wallet" untuk tipe ewallet', () => {
            const userWithEwallet = makeMitra({
                bank_accounts: [
                    makeBankAccount({
                        tipe: 'ewallet',
                        nama_penyedia: 'GoPay',
                    }),
                ],
            });

            render(Show, { user: userWithEwallet });

            expect(screen.getByText('E-Wallet')).toBeInTheDocument();
            expect(screen.getByText('GoPay')).toBeInTheDocument();
        });

        it('menampilkan badge "Utama" untuk rekening primary', () => {
            render(Show, { user: makeMitra() });
            expect(screen.getByText('Utama')).toBeInTheDocument();
        });

        it('menampilkan nama pemilik dengan prefix "a.n"', () => {
            render(Show, { user: makeMitra() });
            expect(screen.getByText('a.n Mitra Satu')).toBeInTheDocument();
        });

        it('merender multiple rekening', () => {
            const multiBankUser = makeMitra({
                bank_accounts: [
                    makeBankAccount({ id: 1, nama_penyedia: 'BCA' }),
                    makeBankAccount({
                        id: 2,
                        nama_penyedia: 'BNI',
                        is_primary: false,
                    }),
                ],
            });

            render(Show, { user: multiBankUser });

            expect(screen.getByText('BCA')).toBeInTheDocument();
            expect(screen.getByText('BNI')).toBeInTheDocument();
        });
    });

    // ----------------------------------------------------------
    // 11.4 INTERACTION — Reveal/Hide
    // ----------------------------------------------------------
    describe('Interaction — Reveal Nomor Rekening', () => {
        it('menampilkan nomor lengkap saat tombol mata diklik', async () => {
            render(Show, { user: makeMitra() });

            expect(screen.getByText(/•••• •••• 7890/)).toBeInTheDocument();

            await fireEvent.click(screen.getByTitle('Tampilkan'));
            await syncUI();

            // ✅ Scope ke card "Rekening Bank" — hindari bentrok dengan "Nomor Identitas"
            const bankCard = getCardByTitle('Rekening Bank');
            expect(
                within(bankCard).getByText('1234567890'),
            ).toBeInTheDocument();
            expect(
                within(bankCard).queryByText(/•••• •••• 7890/),
            ).not.toBeInTheDocument();
        });

        it('menyembunyikan nomor saat tombol diklik lagi', async () => {
            render(Show, { user: makeMitra() });

            // Reveal
            await fireEvent.click(screen.getByTitle('Tampilkan'));
            await syncUI();

            const bankCard = getCardByTitle('Rekening Bank');
            expect(
                within(bankCard).getByText('1234567890'),
            ).toBeInTheDocument();

            // Hide
            await fireEvent.click(screen.getByTitle('Sembunyikan'));
            await syncUI();

            expect(
                within(bankCard).getByText(/•••• •••• 7890/),
            ).toBeInTheDocument();
        });

        it('toggle reveal hanya untuk rekening yang diklik (multi-account)', async () => {
            const multiUser = makeMitra({
                bank_accounts: [
                    makeBankAccount({ id: 1, nomor_rekening: '111122223333' }),
                    makeBankAccount({
                        id: 2,
                        nomor_rekening: '444455556666',
                        is_primary: false,
                    }),
                ],
            });

            render(Show, { user: multiUser });

            const revealButtons = screen.getAllByTitle('Tampilkan');
            await fireEvent.click(revealButtons[0]);
            await syncUI();

            const bankCard = getCardByTitle('Rekening Bank');
            expect(
                within(bankCard).getByText('111122223333'),
            ).toBeInTheDocument();
            expect(
                within(bankCard).getByText(/•••• •••• 6666/),
            ).toBeInTheDocument();
        });
    });

    // ----------------------------------------------------------
    // 11.5 INTERACTION — Copy
    // ----------------------------------------------------------
    describe('Interaction — Copy Nomor Rekening', () => {
        it('memanggil copyToClipboard dengan nomor lengkap (bukan masked)', async () => {
            render(Show, { user: makeMitra() });

            await fireEvent.click(screen.getByTitle('Salin nomor rekening'));

            expect(copyToClipboard).toHaveBeenCalledWith('1234567890');
        });

        it('menampilkan toast success setelah copy', async () => {
            render(Show, { user: makeMitra() });

            await fireEvent.click(screen.getByTitle('Salin nomor rekening'));

            expect(showToast).toHaveBeenCalledWith(
                'Nomor rekening berhasil disalin.',
                'success',
            );
        });
    });

    // ----------------------------------------------------------
    // 11.6 RENDERING — Info Akun
    // ----------------------------------------------------------
    describe('Rendering — Info Akun', () => {
        it('menampilkan tanggal daftar dalam format Indonesia', () => {
            render(Show, { user: makeAdmin() });
            expect(screen.getByText(/15 Januari 2026/)).toBeInTheDocument();
        });

        it('menampilkan label "Tanggal Daftar"', () => {
            render(Show, { user: makeAdmin() });
            expect(screen.getByText('Tanggal Daftar')).toBeInTheDocument();
        });
    });

    // ----------------------------------------------------------
    // 11.7 EDGE CASE — Fallback User
    // ----------------------------------------------------------
    describe('Edge Case — Fallback User', () => {
        it('merender fallback "User Tidak Ditemukan" saat user falsy', () => {
            render(Show, { user: null as any });

            expect(
                screen.getByRole('heading', { name: 'User Tidak Ditemukan' }),
            ).toBeInTheDocument();
            expect(screen.getByText('-')).toBeInTheDocument();
        });
    });
});
