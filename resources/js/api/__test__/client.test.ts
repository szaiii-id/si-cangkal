import { describe, it, expect, vi, beforeEach } from 'vitest';

// 1. Mock modul Inertia
vi.mock('@inertiajs/svelte', () => ({
    router: {
        get: vi.fn(),
        post: vi.fn(),
        put: vi.fn(),
        delete: vi.fn(),
    },
}));

import { router } from '@inertiajs/svelte';
import {
    updateProfile,
    getCatalog,
    getBookings,
    createBooking,
    cancelBooking,
    createReview,
    uploadProof,
    createXenditCharge,
    getPaymentProofUrl,
    getETicketDownloadUrl,
} from '../client';

describe('Client API Wrapper', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    // ==========================================
    // 1. HAPPY PATH — Memastikan Method & URL Benar
    // ==========================================
    describe('Happy Path — Endpoint & Method', () => {
        it('updateProfile() memanggil PUT /client/profile', () => {
            const payload = { name: 'Budi', phone: '08123456789' };
            updateProfile(payload);
            expect(router.put).toHaveBeenCalledOnce();
            expect(router.put).toHaveBeenCalledWith(
                '/client/profile',
                payload,
                undefined,
            );
        });

        it('getCatalog() memanggil GET /client/catalog dengan params', () => {
            const params = { search: 'bali', page: 1 };
            getCatalog(params);
            expect(router.get).toHaveBeenCalledWith(
                '/client/catalog',
                params,
                undefined,
            );
        });

        it('getBookings() memanggil GET /client/bookings dengan params', () => {
            const params = { status: 'pending' };
            getBookings(params);
            expect(router.get).toHaveBeenCalledWith(
                '/client/bookings',
                params,
                undefined,
            );
        });

        it('createBooking() memanggil POST /client/bookings/{id}', () => {
            const data = { date: '2026-12-01', guests: 2 };
            createBooking(99, data);
            expect(router.post).toHaveBeenCalledWith(
                '/client/bookings/99',
                data,
                undefined,
            );
        });

        it('cancelBooking() memanggil POST /client/bookings/{id}/cancel', () => {
            const data = { reason: 'Berubah pikiran' };
            cancelBooking(42, data);
            expect(router.post).toHaveBeenCalledWith(
                '/client/bookings/42/cancel',
                data,
                undefined,
            );
        });

        it('createXenditCharge() memanggil POST /client/bookings/{id}/pay', () => {
            createXenditCharge(42);
            expect(router.post).toHaveBeenCalledWith(
                '/client/bookings/42/pay',
                {},
                undefined,
            );
        });

        it('createReview() memanggil POST /client/bookings/{id}/review', () => {
            const data = { rating: 4, komentar: 'Perjalanan menyenangkan.' };
            createReview(42, data);
            expect(router.post).toHaveBeenCalledWith(
                '/client/bookings/42/review',
                data,
                undefined,
            );
        });

        it('uploadProof() memanggil POST /client/bookings/{id}/upload-proof', () => {
            const formData = { file: 'proof.jpg' };
            uploadProof(42, formData);
            expect(router.post).toHaveBeenCalledWith(
                '/client/bookings/42/upload-proof',
                formData,
                undefined,
            );
        });
    });

    // ==========================================
    // 2. PURE URL BUILDERS — String Purity
    // ==========================================
    describe('URL Builders (Pure String Logic)', () => {
        it('getPaymentProofUrl() menghasilkan URL yang benar', () => {
            expect(getPaymentProofUrl(42)).toBe(
                '/client/bookings/42/payment-proof',
            );
        });

        it('getETicketDownloadUrl() menghasilkan URL dengan UUID', () => {
            const uuid = 'abc-123-def';
            expect(getETicketDownloadUrl(uuid)).toBe(
                `/client/e-tickets/${uuid}/download`,
            );
        });

        it('URL Builder menangani ID ekstrem (angka besar & nol)', () => {
            expect(getPaymentProofUrl(0)).toBe(
                '/client/bookings/0/payment-proof',
            );
            expect(getPaymentProofUrl(9999999)).toBe(
                '/client/bookings/9999999/payment-proof',
            );
        });
    });

    // ==========================================
    // 3. OPTIONS FORWARDING
    // ==========================================
    describe('Options Forwarding', () => {
        it('meneruskan VisitOptions ke updateProfile', () => {
            const payload = { name: 'Budi' };
            const options = { preserveScroll: true, onSuccess: vi.fn() };
            updateProfile(payload, options);
            expect(router.put).toHaveBeenCalledWith(
                '/client/profile',
                payload,
                options,
            );
        });

        it('meneruskan VisitOptions ke createBooking', () => {
            const data = { guests: 3 };
            const options = { preserveState: true };
            createBooking(10, data, options);
            expect(router.post).toHaveBeenCalledWith(
                '/client/bookings/10',
                data,
                options,
            );
        });
    });

    // ==========================================
    // 4. EDGE CASES — Default Parameter
    // ==========================================
    describe('Edge Cases — Default Parameters', () => {
        it('getCatalog() tanpa argumen menggunakan params default {}', () => {
            getCatalog();
            expect(router.get).toHaveBeenCalledWith(
                '/client/catalog',
                {},
                undefined,
            );
        });

        it('getBookings() tanpa argumen menggunakan params default {}', () => {
            getBookings();
            expect(router.get).toHaveBeenCalledWith(
                '/client/bookings',
                {},
                undefined,
            );
        });

        it('cancelBooking() hanya dengan bookingId menggunakan payload default {}', () => {
            cancelBooking(42);
            expect(router.post).toHaveBeenCalledWith(
                '/client/bookings/42/cancel',
                {},
                undefined,
            );
        });

        it('createBooking() dengan payload kosong tetap diteruskan', () => {
            createBooking(1, {});
            expect(router.post).toHaveBeenCalledWith(
                '/client/bookings/1',
                {},
                undefined,
            );
        });
    });

    // ==========================================
    // 5. NEGATIVE PATH — Guard Regresi
    // ==========================================
    describe('Negative Path — Method Guard', () => {
        it('updateProfile() tidak memanggil router.post atau router.get', () => {
            updateProfile({ name: 'Budi' });
            expect(router.post).not.toHaveBeenCalled();
            expect(router.get).not.toHaveBeenCalled();
        });

        it('getCatalog() tidak memanggil router.post', () => {
            getCatalog({});
            expect(router.post).not.toHaveBeenCalled();
        });

        it('URL Builders tidak memanggil router sama sekali', () => {
            getPaymentProofUrl(1);
            getETicketDownloadUrl('abc');
            expect(router.get).not.toHaveBeenCalled();
            expect(router.post).not.toHaveBeenCalled();
            expect(router.put).not.toHaveBeenCalled();
            expect(router.delete).not.toHaveBeenCalled();
        });
    });
});
