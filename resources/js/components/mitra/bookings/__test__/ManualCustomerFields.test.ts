import { render, screen, fireEvent } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import ManualCustomerFields from '../ManualCustomerFields.svelte';

// ==========================================
// MOCK DATA
// ==========================================
const createMockForm = (overrides = {}) => ({
    nama_pelanggan: 'Budi Santoso',
    email_pelanggan: 'budi@test.com',
    no_hp_pelanggan: '081234567890',
    tanggal_keberangkatan: '2026-10-20',
    jumlah_peserta: 2,
    catatan: 'Jemput di bandara',
    ...overrides,
});

describe('ManualCustomerFields Component', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    // ==========================================
    // 1. RENDERING DASAR
    // ==========================================
    describe('Rendering', () => {
        it('harus merender semua field', () => {
            render(ManualCustomerFields, {
                props: { form: createMockForm(), errors: {} },
            });

            expect(
                screen.getByLabelText(/Nama Pelanggan/i),
            ).toBeInTheDocument();
            expect(
                screen.getByLabelText(/Email Pelanggan/i),
            ).toBeInTheDocument();
            expect(screen.getByLabelText(/No. HP/i)).toBeInTheDocument();
            expect(
                screen.getByLabelText(/Tanggal Keberangkatan/i),
            ).toBeInTheDocument();
            expect(
                screen.getByLabelText(/Jumlah Peserta/i),
            ).toBeInTheDocument();
            expect(screen.getByLabelText(/Catatan/i)).toBeInTheDocument();
        });

        it('harus menampilkan nilai awal dari props form', () => {
            render(ManualCustomerFields, {
                props: { form: createMockForm(), errors: {} },
            });

            const namaInput = screen.getByLabelText(
                /Nama Pelanggan/i,
            ) as HTMLInputElement;
            expect(namaInput.value).toBe('Budi Santoso');

            const emailInput = screen.getByLabelText(
                /Email Pelanggan/i,
            ) as HTMLInputElement;
            expect(emailInput.value).toBe('budi@test.com');
        });
    });

    // ==========================================
    // 2. ERROR STATE
    // ==========================================
    describe('Error State', () => {
        it('harus menampilkan pesan error untuk nama jika ada', () => {
            render(ManualCustomerFields, {
                props: {
                    form: createMockForm(),
                    errors: { nama_pelanggan: 'Nama wajib diisi' },
                },
            });

            expect(screen.getByText('Nama wajib diisi')).toBeInTheDocument();
        });

        it('harus menampilkan pesan error untuk email jika ada', () => {
            render(ManualCustomerFields, {
                props: {
                    form: createMockForm(),
                    errors: { email_pelanggan: 'Email tidak valid' },
                },
            });

            expect(screen.getByText('Email tidak valid')).toBeInTheDocument();
        });

        it('input harus memiliki aria-invalid=true jika error', () => {
            render(ManualCustomerFields, {
                props: {
                    form: createMockForm(),
                    errors: { email_pelanggan: 'Error' },
                },
            });

            const emailInput = screen.getByLabelText(/Email Pelanggan/i);
            expect(emailInput).toHaveAttribute('aria-invalid', 'true');
        });
    });

    // ==========================================
    // 3. USER INTERACTION
    // ==========================================
    describe('User Interaction', () => {
        it('harus memperbarui nilai input saat user mengetik', async () => {
            render(ManualCustomerFields, {
                props: { form: createMockForm(), errors: {} },
            });

            const namaInput = screen.getByLabelText(
                /Nama Pelanggan/i,
            ) as HTMLInputElement;
            await fireEvent.input(namaInput, {
                target: { value: 'Budi Baru' },
            });

            expect(namaInput.value).toBe('Budi Baru');
        });
    });

    // ==========================================
    // 4. AKSESIBILITAS (A11Y)
    // ==========================================
    describe('Accessibility', () => {
        it('pesan error harus memiliki role="alert"', () => {
            render(ManualCustomerFields, {
                props: {
                    form: createMockForm(),
                    errors: { nama_pelanggan: 'Nama wajib diisi' },
                },
            });

            const errorMessage = screen.getByRole('alert');
            expect(errorMessage).toHaveTextContent('Nama wajib diisi');
        });
    });
});
