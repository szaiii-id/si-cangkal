import { render, screen, fireEvent } from '@testing-library/svelte';
import { describe, it, expect, vi } from 'vitest';
import ReportFilter from '../ReportFilter.svelte';

describe('ReportFilter Component', () => {
    // ==========================================
    // 1. RENDERING DASAR
    // ==========================================
    describe('Rendering', () => {
        it('harus merender dua input tanggal dan tombol Terapkan Filter', () => {
            render(ReportFilter, {
                props: {
                    startDate: '2026-10-01',
                    endDate: '2026-10-31',
                    onApply: vi.fn(),
                },
            });

            expect(screen.getByLabelText('Dari Tanggal')).toBeInTheDocument();
            expect(screen.getByLabelText('Sampai Tanggal')).toBeInTheDocument();
            expect(
                screen.getByRole('button', {
                    name: /Terapkan filter tanggal/i,
                }),
            ).toBeInTheDocument();
        });

        it('harus menampilkan nilai startDate dan endDate dari props', () => {
            render(ReportFilter, {
                props: {
                    startDate: '2026-10-01',
                    endDate: '2026-10-31',
                    onApply: vi.fn(),
                },
            });

            const startInput = screen.getByLabelText(
                'Dari Tanggal',
            ) as HTMLInputElement;
            const endInput = screen.getByLabelText(
                'Sampai Tanggal',
            ) as HTMLInputElement;

            expect(startInput.value).toBe('2026-10-01');
            expect(endInput.value).toBe('2026-10-31');
        });
    });

    // ==========================================
    // 2. INTERAKSI
    // ==========================================
    describe('User Interaction', () => {
        it('harus memanggil onApply saat tombol "Terapkan Filter" diklik', async () => {
            const onApply = vi.fn();
            render(ReportFilter, {
                props: {
                    startDate: '2026-10-01',
                    endDate: '2026-10-31',
                    onApply,
                },
            });

            const btn = screen.getByRole('button', {
                name: /Terapkan filter tanggal/i,
            });
            await fireEvent.click(btn);

            expect(onApply).toHaveBeenCalledTimes(1);
        });
    });

    // ==========================================
    // 3. VALIDASI TANGGAL
    // ==========================================
    describe('Date Range Validation', () => {
        it('harus menonaktifkan tombol jika startDate > endDate', () => {
            render(ReportFilter, {
                props: {
                    startDate: '2026-10-31',
                    endDate: '2026-10-01', // Lebih kecil dari start
                    onApply: vi.fn(),
                },
            });

            const btn = screen.getByRole('button', {
                name: /Terapkan filter tanggal/i,
            });
            expect(btn).toBeDisabled();
            expect(
                screen.getByText(/tidak boleh lebih besar/i),
            ).toBeInTheDocument();
        });

        it('harus mengaktifkan tombol jika rentang tanggal valid', () => {
            render(ReportFilter, {
                props: {
                    startDate: '2026-10-01',
                    endDate: '2026-10-31',
                    onApply: vi.fn(),
                },
            });

            const btn = screen.getByRole('button', {
                name: /Terapkan filter tanggal/i,
            });
            expect(btn).not.toBeDisabled();
        });
    });

    // ==========================================
    // 4. LOADING STATE (OPSIONAL)
    // ==========================================
    describe('Loading State', () => {
        it('harus menonaktifkan tombol dan menampilkan spinner jika isProcessing=true', () => {
            render(ReportFilter, {
                props: {
                    startDate: '2026-10-01',
                    endDate: '2026-10-31',
                    onApply: vi.fn(),
                    isProcessing: true,
                },
            });

            const btn = screen.getByRole('button', {
                name: /Terapkan filter tanggal/i,
            });
            expect(btn).toBeDisabled();
            expect(screen.getByText(/Memproses…/i)).toBeInTheDocument();
        });
    });

    // ==========================================
    // 5. AKSESIBILITAS (A11Y)
    // ==========================================
    describe('Accessibility', () => {
        it('input harus memiliki aria-invalid=true jika tanggal tidak valid', () => {
            render(ReportFilter, {
                props: {
                    startDate: '2026-10-31',
                    endDate: '2026-10-01',
                    onApply: vi.fn(),
                },
            });

            const startInput = screen.getByLabelText('Dari Tanggal');
            const endInput = screen.getByLabelText('Sampai Tanggal');

            expect(startInput).toHaveAttribute('aria-invalid', 'true');
            expect(endInput).toHaveAttribute('aria-invalid', 'true');
        });
    });
});
