import { render, screen, fireEvent } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import BankAccountList from '../BankAccountList.svelte';
import type { BankAccount } from '@/types/models';

// ==========================================
// MOCK DATA (SNAKE_CASE)
// ==========================================
const createMockAccount = (
    overrides: Partial<BankAccount> = {},
): BankAccount => ({
    id: 1,
    tipe: 'bank',
    nama_penyedia: 'BCA',
    nomor_rekening: '1234567890',
    nama_pemilik: 'Budi Santoso',
    is_primary: false,
    ...overrides,
});

describe('BankAccountList Component', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    // ==========================================
    // 1. RENDERING DASAR
    // ==========================================
    describe('Rendering', () => {
        it('harus merender pesan default jika accounts kosong', () => {
            render(BankAccountList, {
                props: { accounts: [], onCopy: vi.fn() },
            });

            expect(
                screen.getByText('Mitra belum mengatur rekening.'),
            ).toBeInTheDocument();
            expect(
                screen.getByText(
                    'Silakan hubungi mitra langsung via WhatsApp.',
                ),
            ).toBeInTheDocument();
        });

        it('harus merender daftar rekening jika ada', () => {
            render(BankAccountList, {
                props: { accounts: [createMockAccount()], onCopy: vi.fn() },
            });

            expect(screen.getByText('BCA')).toBeInTheDocument();
            expect(screen.getByText('1234567890')).toBeInTheDocument();
            expect(screen.getByText('Budi Santoso')).toBeInTheDocument();
        });
    });

    // ==========================================
    // 2. SORTING (PRIMARY FIRST)
    // ==========================================
    describe('Sorting', () => {
        it('harus menampilkan rekening primary di urutan pertama', () => {
            const accounts = [
                createMockAccount({
                    id: 1,
                    nama_penyedia: 'BCA',
                    is_primary: false,
                }),
                createMockAccount({
                    id: 2,
                    nama_penyedia: 'Mandiri',
                    is_primary: true,
                }),
            ];
            render(BankAccountList, { props: { accounts, onCopy: vi.fn() } });

            const providerNames = screen
                .getAllByText(/BCA|Mandiri/)
                .map((el) => el.textContent);

            expect(providerNames[0]).toBe('Mandiri');
            expect(providerNames[1]).toBe('BCA');
        });
    });

    // ==========================================
    // 3. NORMALIZATION
    // ==========================================
    describe('Normalization', () => {
        it('harus menangani input string JSON', () => {
            const accounts = JSON.stringify([
                createMockAccount({ nama_penyedia: 'BNI' }),
            ]);
            render(BankAccountList, {
                props: { accounts: accounts as any, onCopy: vi.fn() },
            });

            expect(screen.getByText('BNI')).toBeInTheDocument();
        });

        it('harus menangani input objek dengan properti data', () => {
            const accounts = {
                data: [createMockAccount({ nama_penyedia: 'BRI' })],
            };
            render(BankAccountList, {
                props: { accounts: accounts as any, onCopy: vi.fn() },
            });

            expect(screen.getByText('BRI')).toBeInTheDocument();
        });
    });

    // ==========================================
    // 4. INTERAKSI COPY
    // ==========================================
    describe('User Interaction', () => {
        it('harus memanggil onCopy dengan nomor rekening saat tombol Copy diklik', async () => {
            const onCopy = vi.fn();
            render(BankAccountList, {
                props: { accounts: [createMockAccount()], onCopy },
            });

            const copyBtn = screen.getByRole('button', {
                name: /Copy nomor rekening/i,
            });
            await fireEvent.click(copyBtn);

            expect(onCopy).toHaveBeenCalledWith('1234567890');
        });
    });

    // ==========================================
    // 5. AKSESIBILITAS (A11Y)
    // ==========================================
    describe('Accessibility', () => {
        it('tombol copy harus memiliki aria-label', () => {
            render(BankAccountList, {
                props: { accounts: [createMockAccount()], onCopy: vi.fn() },
            });

            const copyBtn = screen.getByRole('button', {
                name: /Copy nomor rekening BCA/i,
            });
            expect(copyBtn).toBeInTheDocument();
        });

        it('ikon SVG harus disembunyikan dari screen reader', () => {
            const { container } = render(BankAccountList, {
                props: { accounts: [createMockAccount()], onCopy: vi.fn() },
            });

            const svgs = container.querySelectorAll('svg');
            svgs.forEach((svg) => {
                expect(svg).toHaveAttribute('aria-hidden', 'true');
            });
        });
    });
});
