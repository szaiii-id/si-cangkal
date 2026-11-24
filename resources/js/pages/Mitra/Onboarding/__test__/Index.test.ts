/**
 * ============================================================
 * Mitra Onboarding — Index Page — Component Test
 * ============================================================
 *
 * Fokus pengujian:
 *   - Isolation & Mocking  : Inertia, API, child components
 *   - Props & Rendering    : Form wrapper + child form
 *   - Interaction          : Submit tanpa input -> error; logout
 *   - Reactivity           : errors object update memicu re-render
 * ============================================================
 */

import { render, screen, fireEvent } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { tick } from 'svelte';
import Index from '../Index.svelte';

// ============================================================
// 1. HOISTED MOCK REGISTRY
// ============================================================
const mockSubmit = vi.hoisted(() => vi.fn());
const mockLogout = vi.hoisted(() => vi.fn());
const mockUpdaters = vi.hoisted(() => new Set<() => void>());

// ============================================================
// 2. MOCK DEPENDENCIES
// ============================================================
vi.mock('@/api/mitra', () => ({
    submitOnboarding: mockSubmit,
}));

vi.mock('@/api/auth', () => ({
    logout: mockLogout,
}));

vi.mock('@/utils/toast.svelte', () => ({
    showToast: vi.fn(),
}));

// ============================================================
// 3. MOCK CHILD COMPONENTS (Reaktif via mockUpdaters)
// ============================================================
vi.mock('@/components/onboarding/BusinessProfileForm.svelte', () => ({
    default: (anchor: Node, props: any) => {
        const el = document.createElement('div');
        el.setAttribute('data-testid', 'business-form');
        anchor.parentNode?.insertBefore(el, anchor);

        const update = () => {
            el.innerHTML = `
                <input type="text" aria-label="Nama Usaha" />
                ${props.errors?.nama_penyedia_usaha ? `<p role="alert">${props.errors.nama_penyedia_usaha}</p>` : ''}
                <input type="text" aria-label="No HP" />
                ${props.errors?.no_hp ? `<p role="alert">${props.errors.no_hp}</p>` : ''}
                <textarea aria-label="Deskripsi"></textarea>
            `;
        };

        update();
        mockUpdaters.add(update);
        return {};
    },
}));

vi.mock('@/components/onboarding/BankAccountManager.svelte', () => ({
    default: (anchor: Node, props: any) => {
        const el = document.createElement('div');
        el.setAttribute('data-testid', 'bank-manager');
        anchor.parentNode?.insertBefore(el, anchor);

        const update = () => {
            el.innerHTML = `
                <div>${props.accounts?.length ?? 0} accounts</div>
                ${props.errors?.accounts ? `<p role="alert">${props.errors.accounts}</p>` : ''}
            `;
        };

        update();
        mockUpdaters.add(update);
        return {};
    },
}));

// ============================================================
// 4. HELPERS
// ============================================================
async function syncUI(): Promise<void> {
    await tick();
    mockUpdaters.forEach((fn) => fn());
}

// ============================================================
// 5. TEST SUITE
// ============================================================
describe('Mitra Onboarding — Index Page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        mockUpdaters.clear();
    });

    it('merender child components', () => {
        render(Index);
        expect(screen.getByTestId('business-form')).toBeInTheDocument();
        expect(screen.getByTestId('bank-manager')).toBeInTheDocument();
    });

    it('memanggil logout saat tombol keluar diklik', async () => {
        render(Index);
        await fireEvent.click(screen.getByRole('button', { name: /Keluar/i }));
        expect(mockLogout).toHaveBeenCalledOnce();
    });

    it('menampilkan error banner + error per-field saat submit kosong', async () => {
        render(Index);

        await fireEvent.click(
            screen.getByRole('button', { name: /Simpan & Masuk Dashboard/i }),
        );
        await syncUI();

        // Banner error count (dari parent)
        expect(
            screen.getByText(/Terdapat 5 kesalahan pada form/i),
        ).toBeInTheDocument();

        // Error per-field (dari mock child)
        expect(
            screen.getByText('Nama penyedia usaha wajib diisi.'),
        ).toBeInTheDocument();
        expect(screen.getByText('Nomor HP wajib diisi.')).toBeInTheDocument();

        // API tidak terpanggil
        expect(mockSubmit).not.toHaveBeenCalled();
    });

    it('TIDAK submit jika form kosong', async () => {
        render(Index);

        await fireEvent.click(
            screen.getByRole('button', { name: /Simpan & Masuk Dashboard/i }),
        );
        await syncUI();

        expect(mockSubmit).not.toHaveBeenCalled();
    });
});
