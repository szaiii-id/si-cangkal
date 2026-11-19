import { render, screen } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import PackageFacilities from '../PackageFacilities.svelte';
import type { PackageFacility } from '@/types/models/package';

// ==========================================
// MOCK DATA
// ==========================================
const createMockFacility = (overrides: Partial<PackageFacility> = {}): PackageFacility => ({
    id: 1,
    package_id: 1,
    tipe: 'include',
    nama: 'Transportasi',
    urutan: 1,
    ...overrides,
});

describe('PackageFacilities Component', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    // ==========================================
    // 1. RENDERING DASAR
    // ==========================================
    describe('Rendering', () => {
        it('harus merender judul "Fasilitas"', () => {
            render(PackageFacilities, { props: { facilities: [] } });
            expect(screen.getByText('Fasilitas')).toBeInTheDocument();
        });

        it('harus merender dua kolom: Termasuk dan Tidak Termasuk', () => {
            render(PackageFacilities, { props: { facilities: [] } });

            expect(screen.getByText('Termasuk')).toBeInTheDocument();
            expect(screen.getByText('Tidak Termasuk')).toBeInTheDocument();
        });
    });

    // ==========================================
    // 2. MENAMPILKAN FASILITAS
    // ==========================================
    describe('Displaying Facilities', () => {
        it('harus menampilkan fasilitas "include" di kolom Termasuk', () => {
            const facilities = [
                createMockFacility({ id: 1, tipe: 'include', nama: 'Makan' }),
            ];
            render(PackageFacilities, { props: { facilities } });

            expect(screen.getByText('Makan')).toBeInTheDocument();
        });

        it('harus menampilkan fasilitas "exclude" di kolom Tidak Termasuk', () => {
            const facilities = [
                createMockFacility({ id: 2, tipe: 'exclude', nama: 'Tiket Pesawat' }),
            ];
            render(PackageFacilities, { props: { facilities } });

            expect(screen.getByText('Tiket Pesawat')).toBeInTheDocument();
        });
    });

    // ==========================================
    // 3. EMPTY STATE
    // ==========================================
    describe('Empty State', () => {
        it('harus menampilkan "Belum ada info." jika tidak ada fasilitas', () => {
            render(PackageFacilities, { props: { facilities: [] } });

            const emptyMessages = screen.getAllByText('Belum ada info.');
            expect(emptyMessages.length).toBe(2);
        });
    });

    // ==========================================
    // 4. AKSESIBILITAS (A11Y)
    // ==========================================
    describe('Accessibility', () => {
        it('ikon SVG dekoratif harus disembunyikan dari screen reader', () => {
            const { container } = render(PackageFacilities, {
                props: {
                    facilities: [
                        createMockFacility({ id: 1, tipe: 'include', nama: 'Makan' }),
                    ],
                },
            });

            const svgs = container.querySelectorAll('svg');
            svgs.forEach((svg) => {
                expect(svg).toHaveAttribute('aria-hidden', 'true');
            });
        });

        it('empty state harus memiliki role="status"', () => {
            render(PackageFacilities, { props: { facilities: [] } });

            const statuses = screen.getAllByRole('status');
            expect(statuses.length).toBe(2);
        });
    });
});