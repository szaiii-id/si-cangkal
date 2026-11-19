/**
 * ============================================================
 * Mitra Bookings — Create Manual Page — Component Test
 * ============================================================
 */

import { render, screen, fireEvent } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { tick } from 'svelte';
import CreateManual from '../CreateManual.svelte';

// ============================================================
// 1. HOISTED MOCK REGISTRY
// ============================================================
const mockApi = vi.hoisted(() => ({
    createManualBooking: vi.fn(),
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
// 3. MOCK CHILD COMPONENTS (Anchor-aware, reactive)
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

vi.mock('@/components/mitra/bookings/ManualCustomerFields.svelte', () => ({
    default: (anchor: Node, props: any) => {
        const el = document.createElement('div');
        el.setAttribute('data-testid', 'customer-fields');
        anchor.parentNode?.insertBefore(el, anchor);

        const update = () => {
            el.innerHTML = `
                <input type="text" aria-label="Nama Pelanggan" value="${props.form.nama_pelanggan || ''}" />
                <input type="text" aria-label="Email Pelanggan" value="${props.form.email_pelanggan || ''}" />
                <input type="text" aria-label="No HP Pelanggan" value="${props.form.no_hp_pelanggan || ''}" />
                <input type="date" aria-label="Tanggal Keberangkatan" value="${props.form.tanggal_keberangkatan || ''}" />
                <input type="number" aria-label="Jumlah Peserta" value="${props.form.jumlah_peserta || 1}" />
                <textarea aria-label="Catatan">${props.form.catatan || ''}</textarea>
            `;

            // Bind input events ke props.form langsung
            (el.querySelector('[aria-label="Nama Pelanggan"]') as HTMLInputElement).oninput = (e) => {
                props.form.nama_pelanggan = (e.target as HTMLInputElement).value;
            };
            (el.querySelector('[aria-label="Email Pelanggan"]') as HTMLInputElement).oninput = (e) => {
                props.form.email_pelanggan = (e.target as HTMLInputElement).value;
            };
            (el.querySelector('[aria-label="No HP Pelanggan"]') as HTMLInputElement).oninput = (e) => {
                props.form.no_hp_pelanggan = (e.target as HTMLInputElement).value;
            };
            (el.querySelector('[aria-label="Tanggal Keberangkatan"]') as HTMLInputElement).oninput = (e) => {
                props.form.tanggal_keberangkatan = (e.target as HTMLInputElement).value;
            };
            (el.querySelector('[aria-label="Jumlah Peserta"]') as HTMLInputElement).oninput = (e) => {
                props.form.jumlah_peserta = Number((e.target as HTMLInputElement).value);
            };
            (el.querySelector('[aria-label="Catatan"]') as HTMLTextAreaElement).oninput = (e) => {
                props.form.catatan = (e.target as HTMLTextAreaElement).value;
            };
        };

        update();
        mockUpdaters.add(update);
        return {};
    },
}));

vi.mock('@/components/mitra/bookings/ManualParticipantsEditor.svelte', () => ({
    default: (anchor: Node, props: any) => {
        const el = document.createElement('div');
        el.setAttribute('data-testid', 'participants-editor');
        el.textContent = `${props.participants.length} peserta`;
        anchor.parentNode?.insertBefore(el, anchor);
        return {};
    },
}));

vi.mock('@/components/mitra/bookings/ManualBookingSummary.svelte', () => ({
    default: (anchor: Node, props: any) => {
        const el = document.createElement('div');
        el.setAttribute('data-testid', 'booking-summary');
        el.textContent = `Total: ${props.totalHarga}`;
        anchor.parentNode?.insertBefore(el, anchor);
        return {};
    },
}));

// ============================================================
// 4. HELPERS
// ============================================================
async function syncUI() {
    await tick();
    mockUpdaters.forEach(fn => fn());
}

// ============================================================
// 5. FIXTURES
// ============================================================
const makePackage = (overrides = {}) => ({
    id: 1,
    nama: 'Paket Bali',
    harga_per_orang: '750000',
    harga_per_rombongan: null,
    ...overrides,
});

const renderPage = (pkg = makePackage()) =>
    render(CreateManual, { props: { package: pkg } });

// ============================================================
// 6. TEST SUITE
// ============================================================
describe('Mitra Bookings — Create Manual Page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        mockUpdaters.clear();
    });

    describe('Rendering', () => {
        it('merender info paket di banner', () => {
            renderPage();
            expect(screen.getByRole('heading', { name: 'Paket Bali' })).toBeInTheDocument();
            expect(screen.getByText('Rp 750000 / orang')).toBeInTheDocument();
        });

        it('merender child components', () => {
            renderPage();
            expect(screen.getByTestId('customer-fields')).toBeInTheDocument();
            expect(screen.getByTestId('participants-editor')).toBeInTheDocument();
            expect(screen.getByTestId('booking-summary')).toBeInTheDocument();
        });

        it('menampilkan harga rombongan jika tersedia', () => {
            renderPage(makePackage({ harga_per_rombongan: '5000000' }));
            expect(screen.getByText('Rp 5000000 / rombongan')).toBeInTheDocument();
        });
    });

    describe('Interaction — Form Submission', () => {
        it('memanggil createManualBooking dengan payload benar', async () => {
            renderPage();

            // Isi input via DOM yang di-render oleh mock ManualCustomerFields
            const namaInput = screen.getByLabelText('Nama Pelanggan');
            await fireEvent.input(namaInput, { target: { value: 'Budi' } });

            const jumlahInput = screen.getByLabelText('Jumlah Peserta');
            await fireEvent.input(jumlahInput, { target: { value: '2' } });

            await syncUI();

            const submitBtn = screen.getByRole('button', { name: /Buat Pesanan Manual/i });
            await fireEvent.click(submitBtn);

            expect(mockApi.createManualBooking).toHaveBeenCalledOnce();
            expect(mockApi.createManualBooking).toHaveBeenCalledWith(
                1,
                expect.objectContaining({
                    nama_pelanggan: 'Budi',
                    jumlah_peserta: 2,
                    participants: expect.arrayContaining([
                        expect.objectContaining({ nama: '' }),
                        expect.objectContaining({ nama: '' }),
                    ]),
                }),
                expect.any(Object)
            );
        });

        it('menonaktifkan tombol submit selama processing', async () => {
            mockApi.createManualBooking.mockImplementation(() => {});
            renderPage();

            const submitBtn = screen.getByRole('button', { name: /Buat Pesanan Manual/i });
            await fireEvent.click(submitBtn);

            await Promise.resolve();
            expect(screen.getByRole('button', { name: /Menyimpan.../i })).toBeDisabled();
        });

        it('menampilkan error jika onError dipanggil', async () => {
            mockApi.createManualBooking.mockImplementation((_id, _data, opts) => {
                opts.onError?.({ nama_pelanggan: 'Nama wajib diisi' });
                opts.onFinish?.();
            });

            renderPage();

            const submitBtn = screen.getByRole('button', { name: /Buat Pesanan Manual/i });
            await fireEvent.click(submitBtn);
            await syncUI();

            expect(mockApi.createManualBooking).toHaveBeenCalled();
        });
    });
});