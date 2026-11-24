import { render, screen, fireEvent } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import BookingFilterBar from '../BookingFilterBar.svelte';

// ==========================================
// MOCK CHILD COMPONENTS (Mengarah ke file fisik)
// ==========================================
vi.mock(
    '@/global/SearchInput.svelte',
    () => import('@/global/__mocks__/SearchInput.svelte'),
);
vi.mock(
    '@/global/StatusTabs.svelte',
    () => import('@/global/__mocks__/StatusTabs.svelte'),
);

describe('BookingFilterBar Component', () => {
    const defaultProps = {
        search: '',
        onSearch: vi.fn(),
        onClearSearch: vi.fn(),
        statusTabs: [{ value: 'pending', label: 'Pending', count: 5 }],
        selectedStatus: '',
        onStatusChange: vi.fn(),
        showDateRange: true,
        dateFrom: '',
        dateTo: '',
        onApplyDate: vi.fn(),
        onClearAll: vi.fn(),
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
            render(BookingFilterBar, { props: defaultProps });

            expect(screen.getByTestId('search-input')).toBeInTheDocument();
            expect(screen.getByTestId('status-tabs')).toBeInTheDocument();
        });

        it('harus merender tombol "Filter Tanggal" jika showDateRange=true', () => {
            render(BookingFilterBar, { props: defaultProps });
            expect(screen.getByText('Filter Tanggal')).toBeInTheDocument();
        });

        it('TIDAK boleh merender tombol "Filter Tanggal" jika showDateRange=false', () => {
            render(BookingFilterBar, {
                props: { ...defaultProps, showDateRange: false },
            });
            expect(
                screen.queryByText('Filter Tanggal'),
            ).not.toBeInTheDocument();
        });
    });

    // ==========================================
    // 2. INTERAKSI FILTER TANGGAL
    // ==========================================
    describe('Date Filter Toggle', () => {
        it('harus membuka panel tanggal saat tombol "Filter Tanggal" diklik', async () => {
            render(BookingFilterBar, { props: defaultProps });

            const filterBtn = screen.getByText('Filter Tanggal');
            await fireEvent.click(filterBtn);

            expect(screen.getByLabelText('Dari Tanggal')).toBeInTheDocument();
            expect(screen.getByLabelText('Sampai Tanggal')).toBeInTheDocument();
        });

        it('harus menampilkan indikator "!" jika ada tanggal yang dipilih', () => {
            render(BookingFilterBar, {
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
        it('harus memanggil onClearAll saat tombol "Hapus Semua Filter" diklik', async () => {
            const onClearAll = vi.fn();
            render(BookingFilterBar, {
                props: { ...defaultProps, hasActiveFilters: true, onClearAll },
            });

            const clearBtn = screen.getByLabelText('Hapus semua filter');
            await fireEvent.click(clearBtn);

            expect(onClearAll).toHaveBeenCalledTimes(1);
        });

        it('TIDAK boleh merender tombol "Hapus Semua Filter" jika hasActiveFilters=false', () => {
            render(BookingFilterBar, {
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
            render(BookingFilterBar, { props: defaultProps });

            const filterBtn = screen
                .getByText('Filter Tanggal')
                .closest('button');
            expect(filterBtn).toHaveAttribute('aria-expanded', 'false');
        });

        it('ikon SVG harus disembunyikan dari screen reader', () => {
            const { container } = render(BookingFilterBar, {
                props: defaultProps,
            });

            const svgs = container.querySelectorAll('svg');
            svgs.forEach((svg) => {
                expect(svg).toHaveAttribute('aria-hidden', 'true');
            });
        });
    });
});
