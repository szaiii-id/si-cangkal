import { render, screen, fireEvent } from '@testing-library/svelte';
import { describe, it, expect } from 'vitest';
import Alert from '../Alert.svelte';

describe('Alert Component (Refactored)', () => {
    // ==========================================
    // 1. PROPS & RENDERING
    // ==========================================
    describe('Rendering Props', () => {
        it('harus merender title dan message dengan benar', () => {
            render(Alert, {
                props: {
                    title: 'Berhasil!',
                    message: 'Data Anda telah disimpan.',
                },
            });

            expect(screen.getByText('Berhasil!')).toBeInTheDocument();
            expect(
                screen.getByText('Data Anda telah disimpan.'),
            ).toBeInTheDocument();
        });

        it('harus menerapkan warna (variant) error dengan benar', () => {
            render(Alert, {
                props: { variant: 'error', message: 'Terjadi kesalahan' },
            });

            // Menggunakan getByRole('alert') karena kita menambahkan role="alert"
            const alertDiv = screen.getByRole('alert');
            expect(alertDiv).toHaveClass('bg-red-50');
            expect(alertDiv).toHaveClass('border-red-500');
        });

        it('harus menerapkan class tambahan dari props className', () => {
            render(Alert, {
                props: { variant: 'info', message: 'Info', class: 'mt-4 mb-5' },
            });

            const alertDiv = screen.getByRole('alert');
            expect(alertDiv).toHaveClass('mt-4');
            expect(alertDiv).toHaveClass('mb-5');
        });
    });

    // ==========================================
    // 2. AKSESIBILITAS (a11y)
    // ==========================================
    describe('Accessibility', () => {
        it('harus memiliki role="alert" agar dibaca screen reader', () => {
            render(Alert, { props: { message: 'Peringatan' } });
            const alertDiv = screen.getByRole('alert');
            expect(alertDiv).toBeInTheDocument();
            expect(alertDiv).toHaveAttribute('aria-live', 'polite');
        });

        it('ikon SVG harus disembunyikan dari screen reader (aria-hidden)', () => {
            const { container } = render(Alert, { props: { message: 'Info' } });
            const svg = container.querySelector('svg');
            expect(svg).toHaveAttribute('aria-hidden', 'true');
        });
    });

    // ==========================================
    // 3. USER INTERACTION & REACTIVITY
    // ==========================================
    describe('User Interaction', () => {
        it('TIDAK boleh menampilkan tombol tutup jika dismissible=false', () => {
            render(Alert, { props: { message: 'Pesan', dismissible: false } });
            const closeButton = screen.queryByRole('button', {
                name: /tutup/i,
            });
            expect(closeButton).not.toBeInTheDocument();
        });

        it('harus menampilkan tombol tutup dan menyembunyikan alert saat diklik', async () => {
            render(Alert, {
                props: { message: 'Akan ditutup', dismissible: true },
            });

            // Pastikan alert awalnya ada
            expect(screen.getByRole('alert')).toBeInTheDocument();

            // Klik tombol tutup
            const closeButton = screen.getByRole('button', { name: /tutup/i });
            await fireEvent.click(closeButton);

            // Alert harus hilang dari DOM (karena $state visible = false)
            expect(screen.queryByRole('alert')).not.toBeInTheDocument();
        });
    });

    // ==========================================
    // 4. REACTIVITY (Rerender)
    // ==========================================
    describe('Reactivity', () => {
        it('harus mengubah warna jika prop variant diubah (Rerender)', async () => {
            const { rerender } = render(Alert, {
                props: { variant: 'info', message: 'Info' },
            });

            let alertDiv = screen.getByRole('alert');
            expect(alertDiv).toHaveClass('bg-blue-50');

            await rerender({ variant: 'warning', message: 'Info' });

            alertDiv = screen.getByRole('alert');
            expect(alertDiv).toHaveClass('bg-yellow-50');
        });
    });
});
