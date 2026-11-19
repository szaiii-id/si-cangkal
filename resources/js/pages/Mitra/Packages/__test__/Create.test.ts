/**
 * ============================================================
 * Mitra Packages — Create Page (Wizard) — Component Test
 * ============================================================
 */

import { render, screen, fireEvent } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { tick } from 'svelte';
import Create from '../Create.svelte';

// ============================================================
// 1. HOISTED MOCK REGISTRY
// ============================================================
const mockApi = vi.hoisted(() => ({
    createPackage: vi.fn(),
}));
const mockUpdaters = vi.hoisted(() => new Set<() => void>());

// ============================================================
// 2. MOCK DEPENDENCIES
// ============================================================
vi.mock('@/api/mitra', () => mockApi);

vi.mock('@/utils/formatCurrency', () => ({
    formatCurrency: vi.fn((v: string | number) => `Rp ${v}`),
}));

vi.mock('@/utils/menus', () => ({
    mitraMenu: [],
}));

vi.mock('@inertiajs/svelte', () => ({
    inertia: vi.fn(),
}));

// ============================================================
// 3. MOCK CHILD COMPONENTS
// ============================================================
vi.mock('@/components/layout/DashboardLayout.svelte', () => ({
    default: (anchor: Node, props: any) => {
        props.children?.(anchor);
        return {};
    },
}));

vi.mock('@/global/BackButton.svelte', () => ({
    default: (anchor: Node, props: any) => {
        const el = document.createElement('div');
        el.setAttribute('data-testid', 'back-button');
        el.textContent = props.label;
        anchor.parentNode?.insertBefore(el, anchor);
        return {};
    },
}));

vi.mock('@/global/InputText.svelte', () => ({
    default: (anchor: Node, props: any) => {
        const el = document.createElement('div');
        anchor.parentNode?.insertBefore(el, anchor);

        const update = () => {
            el.innerHTML = `
                <label for="${props.id}">${props.label}</label>
                <input type="text" id="${props.id}" aria-label="${props.label}" value="${props.value || ''}" ${props.required ? 'required' : ''} />
                ${props.error ? `<p role="alert">${props.error}</p>` : ''}
            `;
            (el.querySelector('input') as HTMLInputElement).oninput = (e) =>
                props.value = (e.target as HTMLInputElement).value;
        };

        update();
        mockUpdaters.add(update);
        return {};
    },
}));

vi.mock('@/global/RupiahInput.svelte', () => ({
    default: (anchor: Node, props: any) => {
        const el = document.createElement('div');
        anchor.parentNode?.insertBefore(el, anchor);

        const update = () => {
            el.innerHTML = `
                <label>${props.label}</label>
                <input type="number" aria-label="${props.label}" value="${props.value || 0}" />
            `;
            (el.querySelector('input') as HTMLInputElement).oninput = (e) =>
                props.value = Number((e.target as HTMLInputElement).value);
        };

        update();
        mockUpdaters.add(update);
        return {};
    },
}));

vi.mock('@/global/ImageUploader.svelte', () => ({
    default: (anchor: Node) => {
        const el = document.createElement('div');
        el.setAttribute('data-testid', 'image-uploader');
        anchor.parentNode?.insertBefore(el, anchor);
        return {};
    },
}));

// ============================================================
// 4. HELPERS
// ============================================================
async function syncUI() {
    await tick();
    mockUpdaters.forEach((fn) => fn());
}

async function fillStep1() {
    const namaInput = screen.getByLabelText(/Nama Paket/i);
    await fireEvent.input(namaInput, { target: { value: 'Paket Bali' } });
    await syncUI();
}

// ============================================================
// 5. TEST SUITE
// ============================================================
describe('Mitra Packages — Create Page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        mockUpdaters.clear();
    });

    describe('Rendering — Step 1 (Info Paket)', () => {
        it('menampilkan stepper dan form step 1', () => {
            render(Create);

            expect(screen.getByText('Fase 1: Pembuatan Paket Dasar')).toBeInTheDocument();
            expect(screen.getByText('Info Paket')).toBeInTheDocument();
            expect(screen.getByLabelText(/Nama Paket/i)).toBeInTheDocument();
            expect(screen.getByLabelText(/Kategori/i)).toBeInTheDocument();
        });

        it('tombol "Lanjut" disabled jika form belum valid', () => {
            render(Create);
            const nextBtn = screen.getByRole('button', { name: /Lanjut/i });
            expect(nextBtn).toBeDisabled();
        });
    });

    describe('Interaction — Navigasi Wizard', () => {
        it('bisa pindah ke step 2 setelah mengisi form step 1', async () => {
            render(Create);
            await fillStep1();

            const nextBtn = screen.getByRole('button', { name: /Lanjut/i });
            expect(nextBtn).not.toBeDisabled();

            await fireEvent.click(nextBtn);
            await syncUI();

            expect(screen.getByText('Durasi & Harga')).toBeInTheDocument();
            expect(screen.getByLabelText(/Durasi Hari/i)).toBeInTheDocument();
        });

        it('bisa kembali dari step 2 ke step 1', async () => {
            render(Create);
            await fillStep1();
            await fireEvent.click(screen.getByRole('button', { name: /Lanjut/i }));
            await syncUI();

            expect(screen.getByText('Durasi & Harga')).toBeInTheDocument();

            await fireEvent.click(screen.getByRole('button', { name: /Kembali/i }));
            await syncUI();

            expect(screen.getByLabelText(/Nama Paket/i)).toBeInTheDocument();
        });
    });

    describe('Rendering — Step 2 (Harga & Durasi)', () => {
        beforeEach(async () => {
            render(Create);
            await fillStep1();
            await fireEvent.click(screen.getByRole('button', { name: /Lanjut/i }));
            await syncUI();
        });

        it('menampilkan input durasi & harga', () => {
            expect(screen.getByLabelText(/Durasi Hari/i)).toBeInTheDocument();
            expect(screen.getByLabelText(/Durasi Malam/i)).toBeInTheDocument();
            expect(screen.getByLabelText(/Harga per Orang/i)).toBeInTheDocument();
        });

        it('bisa toggle checkbox harga rombongan', async () => {
            const checkbox = screen.getByRole('checkbox', { name: /Gunakan Harga Rombongan/i });
            await fireEvent.click(checkbox);
            await syncUI();

            // Pakai exact match agar tidak ambigu dengan label checkbox
            expect(screen.getByLabelText('Harga Rombongan', { exact: true })).toBeInTheDocument();
            expect(screen.getByLabelText(/Maksimal Peserta/i)).toBeInTheDocument();
        });
    });

    describe('Interaction — Submit Step 3', () => {
        it('memanggil createPackage dengan FormData saat submit', async () => {
            render(Create);

            // Step 1
            await fillStep1();
            await fireEvent.click(screen.getByRole('button', { name: /Lanjut/i }));
            await syncUI();

            // Step 2
            const durasiHari = screen.getByLabelText(/Durasi Hari/i);
            await fireEvent.input(durasiHari, { target: { value: '3' } });
            await syncUI();

            const hargaPerOrang = screen.getByLabelText(/Harga per Orang/i);
            await fireEvent.input(hargaPerOrang, { target: { value: '750000' } });
            await syncUI();

            await fireEvent.click(screen.getByRole('button', { name: /Lanjut/i }));
            await syncUI();

            // Step 3
            expect(screen.getByText('Review Paket Anda')).toBeInTheDocument();

            const submitBtn = screen.getByRole('button', { name: /Simpan & Lanjut Isi Kegiatan/i });
            await fireEvent.click(submitBtn);
            await syncUI();

            expect(mockApi.createPackage).toHaveBeenCalledOnce();
            expect(mockApi.createPackage).toHaveBeenCalledWith(
                expect.any(FormData),
                expect.any(Object)
            );

            const formData = mockApi.createPackage.mock.calls[0][0];
            expect(formData.get('nama')).toBe('Paket Bali');
            expect(formData.get('kategori')).toBe('wisata_alam');
            expect(formData.get('durasi_hari')).toBe('3');
            expect(formData.get('harga_per_orang')).toBe('750000');
        });
    });
});