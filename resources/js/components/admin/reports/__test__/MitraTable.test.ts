import { render, screen } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import MitraTable from '../MitraTable.svelte';

// ==========================================
// MOCK DATA
// ==========================================
const createMockMitra = (overrides: Record<string, unknown> = {}) => ({
    id: 1,
    name: 'Mitra Travel',
    email: 'mitra@test.com',
    total_trip: 10,
    total_revenue: 5000000,
    ...overrides,
});

describe('MitraTable Component', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    // ==========================================
    // 1. RENDERING DASAR
    // ==========================================
    describe('Rendering', () => {
        it('harus merender judul "Peringkat Kinerja"', () => {
            render(MitraTable, { props: { mitras: [createMockMitra()] } });
            expect(screen.getByText('Peringkat Kinerja')).toBeInTheDocument();
        });

        it('harus merender jumlah mitra terdaftar', () => {
            render(MitraTable, {
                props: {
                    mitras: [
                        createMockMitra({ id: 1, name: 'Mitra A' }),
                        createMockMitra({ id: 2, name: 'Mitra B' }),
                    ],
                },
            });
            expect(screen.getByText('2 mitra terdaftar')).toBeInTheDocument();
        });

        it('harus merender data mitra (nama, email, total_trip, total_revenue)', () => {
            render(MitraTable, { props: { mitras: [createMockMitra()] } });

            expect(screen.getAllByText('Mitra Travel').length).toBeGreaterThan(0);
            expect(screen.getAllByText('mitra@test.com').length).toBeGreaterThan(0);
            expect(screen.getAllByText('10').length).toBeGreaterThan(0);
            expect(screen.getAllByText(/Rp 5.000.000/).length).toBeGreaterThan(0);
        });
    });

    // ==========================================
    // 2. EMPTY STATE
    // ==========================================
    describe('Empty State', () => {
        it('harus menampilkan pesan kosong di mobile dan desktop', () => {
            render(MitraTable, { props: { mitras: [] } });

            // PERBAIKAN: Pesan muncul 2 kali (mobile & desktop)
            const emptyMessages = screen.getAllByText('Belum ada data mitra pada periode ini.');
            expect(emptyMessages.length).toBe(2);
        });
    });

    // ==========================================
    // 3. FORMATTING
    // ==========================================
    describe('Formatting', () => {
        it('harus memformat total_revenue menjadi Rupiah', () => {
            render(MitraTable, {
                props: { mitras: [createMockMitra({ total_revenue: 1500000 })] },
            });
            expect(screen.getAllByText(/Rp 1.500.000/).length).toBeGreaterThan(0);
        });
    });

    // ==========================================
    // 4. AKSESIBILITAS (A11Y)
    // ==========================================
    describe('Accessibility', () => {
        it('tabel harus memiliki aria-label', () => {
            render(MitraTable, { props: { mitras: [createMockMitra()] } });

            const table = screen.getByRole('table', { name: /Peringkat Kinerja Mitra/i });
            expect(table).toBeInTheDocument();
        });

        it('ikon SVG dekoratif harus disembunyikan dari screen reader', () => {
            const { container } = render(MitraTable, { props: { mitras: [createMockMitra()] } });

            const svgs = container.querySelectorAll('svg');
            svgs.forEach((svg) => {
                expect(svg).toHaveAttribute('aria-hidden', 'true');
            });
        });
    });
});