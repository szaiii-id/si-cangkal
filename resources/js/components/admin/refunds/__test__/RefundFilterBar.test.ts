import { render, screen, fireEvent } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import RefundFilterBar from '../RefundFilterBar.svelte';

// ==========================================
// MOCK CHILD COMPONENTS
// ==========================================
vi.mock('@/global/SearchInput.svelte', () => import('@/global/__mocks__/SearchInput.svelte'));
vi.mock('@/global/StatusTabs.svelte', () => import('@/global/__mocks__/StatusTabs.svelte'));

describe('RefundFilterBar Component', () => {
    const defaultProps = {
        search: '',
        onSearch: vi.fn(),
        statusTabs: [{ value: 'pending', label: 'Pending', count: 5 }],
        selectedStatus: '',
        onStatusChange: vi.fn(),
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
            render(RefundFilterBar, { props: defaultProps });

            expect(screen.getByTestId('search-input')).toBeInTheDocument();
            expect(screen.getByTestId('status-tabs')).toBeInTheDocument();
        });

        it('harus merender placeholder yang benar pada SearchInput', () => {
            render(RefundFilterBar, { props: defaultProps });

            const searchInput = screen.getByTestId('search-input');
            expect(searchInput).toHaveAttribute('placeholder', 'Cari kode pesanan atau nama pelanggan...');
        });
    });

    // ==========================================
    // 2. CLEAR FILTERS
    // ==========================================
    describe('Clear Filters', () => {
        it('harus menampilkan tombol "Hapus Semua Filter" jika hasActiveFilters=true', () => {
            render(RefundFilterBar, { 
                props: { ...defaultProps, hasActiveFilters: true } 
            });

            expect(screen.getByText('Hapus Semua Filter')).toBeInTheDocument();
        });

        it('TIDAK boleh menampilkan tombol "Hapus Semua Filter" jika hasActiveFilters=false', () => {
            render(RefundFilterBar, { 
                props: { ...defaultProps, hasActiveFilters: false } 
            });

            expect(screen.queryByText('Hapus Semua Filter')).not.toBeInTheDocument();
        });

        it('harus memanggil onClear saat tombol "Hapus Semua Filter" diklik', async () => {
            const onClear = vi.fn();
            render(RefundFilterBar, { 
                props: { ...defaultProps, hasActiveFilters: true, onClear } 
            });

            const clearBtn = screen.getByLabelText('Hapus semua filter');
            await fireEvent.click(clearBtn);

            expect(onClear).toHaveBeenCalledTimes(1);
        });
    });

    // ==========================================
    // 3. AKSESIBILITAS (A11Y)
    // ==========================================
    describe('Accessibility', () => {
        it('tombol "Hapus Semua Filter" harus memiliki aria-label', () => {
            render(RefundFilterBar, { 
                props: { ...defaultProps, hasActiveFilters: true } 
            });

            const clearBtn = screen.getByLabelText('Hapus semua filter');
            expect(clearBtn).toBeInTheDocument();
        });

        it('area filter harus memiliki aria-live="polite"', () => {
            const { container } = render(RefundFilterBar, { 
                props: { ...defaultProps, hasActiveFilters: true } 
            });

            const filterArea = container.querySelector('[aria-live="polite"]');
            expect(filterArea).toBeInTheDocument();
        });
    });
});