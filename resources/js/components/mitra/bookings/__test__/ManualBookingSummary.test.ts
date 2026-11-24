import { render, screen } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import ManualBookingSummary from '../ManualBookingSummary.svelte';

describe('ManualBookingSummary Component', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    // ==========================================
    // 1. RENDERING DASAR
    // ==========================================
    describe('Rendering', () => {
        it('harus merender jumlah peserta', () => {
            render(ManualBookingSummary, {
                props: {
                    hargaPerOrang: '500000',
                    hargaPerRombongan: null,
                    jumlahPeserta: 3,
                    totalHarga: 1500000,
                },
            });

            expect(screen.getByText('Jumlah Peserta')).toBeInTheDocument();
            expect(screen.getByText('3 orang')).toBeInTheDocument();
        });

        it('harus merender total tagihan yang terformat', () => {
            render(ManualBookingSummary, {
                props: {
                    hargaPerOrang: '500000',
                    hargaPerRombongan: null,
                    jumlahPeserta: 3,
                    totalHarga: 1500000,
                },
            });

            expect(screen.getByText(/Rp 1.500.000/)).toBeInTheDocument();
        });
    });

    // ==========================================
    // 2. LOGIKA HARGA (CONDITIONAL)
    // ==========================================
    describe('Price Logic', () => {
        it('harus menampilkan harga per orang jika hargaPerRombongan null', () => {
            render(ManualBookingSummary, {
                props: {
                    hargaPerOrang: '500000',
                    hargaPerRombongan: null,
                    jumlahPeserta: 3,
                    totalHarga: 1500000,
                },
            });

            expect(screen.getByText(/Rp 500.000 \/ orang/)).toBeInTheDocument();
        });

        it('harus menampilkan harga per rombongan jika hargaPerRombongan diberikan', () => {
            render(ManualBookingSummary, {
                props: {
                    hargaPerOrang: '500000',
                    hargaPerRombongan: '1200000',
                    jumlahPeserta: 3,
                    totalHarga: 1200000,
                },
            });

            expect(
                screen.getByText(/Rp 1.200.000 \/ rombongan/),
            ).toBeInTheDocument();
        });
    });

    // ==========================================
    // 3. INFO PEMBAYARAN
    // ==========================================
    describe('Payment Info', () => {
        it('harus merender info pembayaran', () => {
            render(ManualBookingSummary, {
                props: {
                    hargaPerOrang: '500000',
                    hargaPerRombongan: null,
                    jumlahPeserta: 3,
                    totalHarga: 1500000,
                },
            });

            expect(
                screen.getByText(
                    /Pembayaran dilakukan langsung ke rekening Anda/,
                ),
            ).toBeInTheDocument();
        });
    });
});
