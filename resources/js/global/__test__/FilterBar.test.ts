import { render, screen, fireEvent } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import FilterBar from '../FilterBar.svelte';

// ==========================================
// MOCK CHILD COMPONENTS (Mengarah ke folder __mocks__)
// ==========================================
vi.mock('@/global/SearchInput.svelte', () => import('../__mocks__/SearchInput.svelte'));
vi.mock('@/global/StatusTabs.svelte', () => import('../__mocks__/StatusTabs.svelte'));

describe('FilterBar Component', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    // ==========================================
    // 1. RENDERING DASAR
    // ==========================================
    describe('Rendering', () => {
        it('harus merender SearchInput jika onSearch diberikan', () => {
            render(FilterBar, { props: { onClear: vi.fn(), onSearch: vi.fn() } });
            expect(screen.getByTestId('search-input')).toBeInTheDocument();
        });

        it('harus merender StatusTabs jika statusTabs diberikan', () => {
            render(FilterBar, {
                props: { onClear: vi.fn(), statusTabs: [{ value: '', label: 'Semua' }] },
            });
            expect(screen.getByTestId('status-tabs')).toBeInTheDocument();
        });

        it('harus merender dropdown kategori jika kategoriOptions diberikan', () => {
            render(FilterBar, {
                props: {
                    onClear: vi.fn(),
                    kategoriOptions: [{ value: 'budaya', label: 'Budaya' }],
                },
            });
            expect(screen.getByRole('combobox')).toBeInTheDocument();
        });

        it('harus menampilkan tombol "Filter Tanggal" jika showDateRange=true', () => {
            render(FilterBar, { props: { onClear: vi.fn(), showDateRange: true } });
            expect(screen.getByText('Filter Tanggal')).toBeInTheDocument();
        });
    });

    // ==========================================
    // 2. INTERAKSI DROPDOWN
    // ==========================================
    describe('Kategori Dropdown', () => {
        it('harus memanggil onKategoriChange saat dropdown diubah', async () => {
            const onKategoriChange = vi.fn();
            render(FilterBar, {
                props: {
                    onClear: vi.fn(),
                    kategoriOptions: [
                        { value: '', label: 'Semua' },
                        { value: 'budaya', label: 'Budaya' },
                    ],
                    onKategoriChange,
                },
            });

            const select = screen.getByRole('combobox');
            await fireEvent.change(select, { target: { value: 'budaya' } });

            expect(onKategoriChange).toHaveBeenCalledWith('budaya');
        });
    });

    // ==========================================
    // 3. DATE RANGE PANEL & VALIDATION
    // ==========================================
    describe('Date Range', () => {
        it('harus membuka panel tanggal saat tombol "Filter Tanggal" diklik', async () => {
            render(FilterBar, { props: { onClear: vi.fn(), showDateRange: true } });

            expect(screen.queryByLabelText('Dari')).not.toBeInTheDocument();

            await fireEvent.click(screen.getByText('Filter Tanggal'));
            
            expect(screen.getByLabelText('Dari')).toBeInTheDocument();
            expect(screen.getByLabelText('Sampai')).toBeInTheDocument();
        });

        it('harus memvalidasi rentang tanggal (From > To)', async () => {
            render(FilterBar, {
                props: {
                    onClear: vi.fn(),
                    showDateRange: true,
                    dateFrom: '2026-10-20',
                    dateTo: '2026-10-10',
                    onApplyDate: vi.fn(),
                },
            });

            await fireEvent.click(screen.getByText('Filter Tanggal'));

            const applyBtn = screen.getByText('Terapkan');
            expect(applyBtn).toBeDisabled();
            expect(screen.getByText(/tidak boleh lebih besar/i)).toBeInTheDocument();
        });

        it('harus memanggil onApplyDate jika rentang valid', async () => {
            const onApplyDate = vi.fn();
            render(FilterBar, {
                props: {
                    onClear: vi.fn(),
                    showDateRange: true,
                    dateFrom: '2026-10-10',
                    dateTo: '2026-10-20',
                    onApplyDate,
                },
            });

            await fireEvent.click(screen.getByText('Filter Tanggal'));
            await fireEvent.click(screen.getByText('Terapkan'));

            expect(onApplyDate).toHaveBeenCalledTimes(1);
        });
    });

    // ==========================================
    // 4. CLEAR ALL
    // ==========================================
    describe('Clear All Filters', () => {
        it('TIDAK boleh merender tombol "Hapus Semua" jika hasActiveFilters=false', () => {
            render(FilterBar, { props: { onClear: vi.fn(), hasActiveFilters: false } });
            expect(screen.queryByText('Hapus Semua Filter')).not.toBeInTheDocument();
        });

        it('harus memanggil onClear saat tombol "Hapus Semua" diklik', async () => {
            const onClear = vi.fn();
            render(FilterBar, { props: { onClear, hasActiveFilters: true } });

            await fireEvent.click(screen.getByText('Hapus Semua Filter'));
            expect(onClear).toHaveBeenCalledTimes(1);
        });
    });
});