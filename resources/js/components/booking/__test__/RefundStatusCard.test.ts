import { render, screen } from '@testing-library/svelte';
import { describe, it, expect } from 'vitest';
import RefundStatusCard from '../RefundStatusCard.svelte';

describe('RefundStatusCard Component', () => {
    // ==========================================
    // 1. RENDERING DASAR
    // ==========================================
    describe('Rendering', () => {
        it('harus merender judul default "Status Refund"', () => {
            render(RefundStatusCard, {
                props: { amount: '500000', status: 'PENDING' },
            });
            expect(screen.getByText('Status Refund')).toBeInTheDocument();
        });

        it('harus merender judul kustom jika title diberikan', () => {
            render(RefundStatusCard, {
                props: {
                    amount: '500000',
                    status: 'PENDING',
                    title: 'Refund Berhasil',
                },
            });
            expect(screen.getByText('Refund Berhasil')).toBeInTheDocument();
        });

        it('harus menampilkan jumlah refund yang terformat', () => {
            render(RefundStatusCard, {
                props: { amount: '1500000', status: 'PENDING' },
            });
            expect(screen.getByText(/Rp 1.500.000/)).toBeInTheDocument();
        });
    });

    // ==========================================
    // 2. STATUS BADGE
    // ==========================================
    describe('Status Badge', () => {
        it('harus menampilkan label "Sedang Diproses" untuk status PENDING', () => {
            render(RefundStatusCard, {
                props: { amount: '500000', status: 'PENDING' },
            });
            expect(screen.getByText('Sedang Diproses')).toBeInTheDocument();
        });

        it('harus menampilkan label "Berhasil" untuk status SUCCEEDED', () => {
            render(RefundStatusCard, {
                props: { amount: '500000', status: 'SUCCEEDED' },
            });
            expect(screen.getByText('Berhasil')).toBeInTheDocument();
        });

        it('harus menampilkan label "Gagal" untuk status FAILED', () => {
            render(RefundStatusCard, {
                props: { amount: '500000', status: 'FAILED' },
            });
            expect(screen.getByText('Gagal')).toBeInTheDocument();
        });
    });

    // ==========================================
    // 3. AKSESIBILITAS (A11Y)
    // ==========================================
    describe('Accessibility', () => {
        it('ikon SVG harus disembunyikan dari screen reader', () => {
            const { container } = render(RefundStatusCard, {
                props: { amount: '500000', status: 'PENDING' },
            });

            const svg = container.querySelector('svg');
            expect(svg).toHaveAttribute('aria-hidden', 'true');
        });
    });
});
