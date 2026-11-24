import { render, screen, fireEvent } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import TripFilterBar from '../TripFilterBar.svelte';

// ==========================================
// MOCK CHILD COMPONENTS
// ==========================================
vi.mock(
    '@/global/SearchInput.svelte',
    () => import('@/global/__mocks__/SearchInput.svelte'),
);
vi.mock(
    '@/global/StatusTabs.svelte',
    () => import('@/global/__mocks__/StatusTabs.svelte'),
);

describe('TripFilterBar Component', () => {
    const defaultProps = {
        search: '',
        onSearch: vi.fn(),
        statusTabs: [{ value: 'active', label: 'Aktif', count: 5 }],
        selectedStatus: '',
        onStatusChange: vi.fn(),
        dateFrom: '',
        dateTo: '',
        onApplyDate: vi.fn(),
        onClear: vi.fn(),
        hasActiveFilters: false,
    };

    beforeEach(() => {
        vi.clearAllMocks();
    });

    // ==========================================
    // 1. RENDERING DASAR
    // ==========================================
    describe('Rendering', () => {
        it('harus merender SearchInput dan StatusTabs', () => {
            render(TripFilterBar, { props: defaultProps });

            expect(screen.getByTestId('search-input')).toBeInTheDocument();
            expect(screen.getByTestId('status-tabs')).toBeInTheDocument();
        });

        it('harus merender tombol "Filter Tanggal"', () => {
            render(TripFilterBar, { props: defaultProps });
            expect(screen.getByText('Filter Tanggal')).toBeInTheDocument();
        });
    });

    // ==========================================
    // 2. INTERAKSI FILTER TANGGAL
    // ==========================================
    describe('Date Filter Toggle', () => {
        it('harus membuka panel tanggal saat tombol "Filter Tanggal" diklik', async () => {
            render(TripFilterBar, { props: defaultProps });

            const filterBtn = screen.getByText('Filter Tanggal');
            await fireEvent.click(filterBtn);

            expect(screen.getByLabelText('Dari Tanggal')).toBeInTheDocument();
            expect(screen.getByLabelText('Sampai Tanggal')).toBeInTheDocument();
        });

        it('harus menampilkan indikator "!" jika ada tanggal yang dipilih', () => {
            render(TripFilterBar, {
                props: { ...defaultProps, dateFrom: '2026-10-01' },
            });

            const indicator = screen.getByText('!');
            expect(indicator).toBeInTheDocument();
        });
    });

    // ==========================================
    // 3. INTERAKSI CLEAR
    // ==========================================
    describe('Clear Filters', () => {
        it('harus memanggil onClear saat tombol "Hapus Semua Filter" diklik', async () => {
            const onClear = vi.fn();
            render(TripFilterBar, {
                props: { ...defaultProps, hasActiveFilters: true, onClear },
            });

            const clearBtn = screen.getByLabelText('Hapus semua filter');
            await fireEvent.click(clearBtn);

            expect(onClear).toHaveBeenCalledTimes(1);
        });

        it('TIDAK boleh merender tombol "Hapus Semua Filter" jika hasActiveFilters=false', () => {
            render(TripFilterBar, {
                props: { ...defaultProps, hasActiveFilters: false },
            });
            expect(
                screen.queryByText('Hapus Semua Filter'),
            ).not.toBeInTheDocument();
        });
    });

    // ==========================================
    // 4. AKSESIBILITAS (A11Y)
    // ==========================================
    describe('Accessibility', () => {
        it('tombol "Filter Tanggal" harus memiliki aria-expanded', () => {
            render(TripFilterBar, { props: defaultProps });

            const filterBtn = screen
                .getByText('Filter Tanggal')
                .closest('button');
            expect(filterBtn).toHaveAttribute('aria-expanded', 'false');
        });

        it('ikon SVG harus disembunyikan dari screen reader', () => {
            const { container } = render(TripFilterBar, {
                props: defaultProps,
            });

            const svgs = container.querySelectorAll('svg');
            svgs.forEach((svg) => {
                expect(svg).toHaveAttribute('aria-hidden', 'true');
            });
        });
    });
});
