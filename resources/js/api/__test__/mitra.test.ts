import { describe, it, expect, vi, beforeEach } from 'vitest';

// Mock Inertia
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
    getPackages,
    getPackage,
    createPackage,
    updatePackage,
    deletePackage,
    publishPackage,
    archivePackage,
    restorePackage,
    getCompletePhase2,
    getTrips,
    getTripManifestUrl,
    getBookings,
    verifyPayment,
    rejectPayment,
    cancelBooking,
    uploadPaymentProof,
    createPaymentLink,
    createManualBooking,
    getRefunds,
    approveRefund,
    rejectRefund,
    submitOnboarding,
    updateProfile,
    updateBankAccounts,
    updatePassword,
} from '../mitra';

describe('Mitra API Wrapper', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    describe('createPaymentLink() — data request (bukan navigasi)', () => {
        it('POST ke endpoint payment-link dengan header JSON', async () => {
            const fetchMock = vi.fn().mockResolvedValue({
                ok: true,
                json: async () => ({
                    url: 'https://sandbox.doku.com/checkout-link-v2/abc',
                    expires_at: '2026-11-25T00:00:00Z',
                }),
            });
            vi.stubGlobal('fetch', fetchMock);

            const result = await createPaymentLink(7);

            expect(fetchMock).toHaveBeenCalledWith(
                '/mitra/bookings/7/payment-link',
                expect.objectContaining({
                    method: 'POST',
                    headers: expect.objectContaining({
                        'Content-Type': 'application/json',
                    }),
                }),
            );
            expect(result.url).toBe(
                'https://sandbox.doku.com/checkout-link-v2/abc',
            );

            vi.unstubAllGlobals();
        });

        it('melempar error saat response tidak ok', async () => {
            vi.stubGlobal(
                'fetch',
                vi.fn().mockResolvedValue({ ok: false, status: 403 }),
            );

            await expect(createPaymentLink(7)).rejects.toThrow(
                'Gagal membuat link pembayaran.',
            );

            vi.unstubAllGlobals();
        });
    });

    // ==========================================
    // 1. PACKAGES
    // ==========================================
    describe('Packages', () => {
        it('getPackages() memanggil GET /mitra/packages', () => {
            getPackages({ search: 'bali' });
            expect(router.get).toHaveBeenCalledWith(
                '/mitra/packages',
                { search: 'bali' },
                undefined,
            );
        });

        it('getPackage() memanggil GET /mitra/packages/{id}', () => {
            getPackage(10);
            expect(router.get).toHaveBeenCalledWith(
                '/mitra/packages/10',
                {},
                undefined,
            );
        });

        it('createPackage() memanggil POST /mitra/packages', () => {
            const data = { name: 'Paket Bali' };
            createPackage(data);
            expect(router.post).toHaveBeenCalledWith(
                '/mitra/packages',
                data,
                undefined,
            );
        });

        it('updatePackage() memanggil POST (method spoofing) /mitra/packages/{id}', () => {
            const data = { name: 'Paket Bali Update', _method: 'PUT' };
            updatePackage(10, data);
            expect(router.post).toHaveBeenCalledWith(
                '/mitra/packages/10',
                data,
                undefined,
            );
        });

        it('deletePackage() memanggil DELETE /mitra/packages/{id}', () => {
            deletePackage(10);
            expect(router.delete).toHaveBeenCalledWith(
                '/mitra/packages/10',
                undefined,
            );
        });

        it('publishPackage() memanggil POST /mitra/packages/{id}/publish', () => {
            publishPackage(10);
            expect(router.post).toHaveBeenCalledWith(
                '/mitra/packages/10/publish',
                {},
                undefined,
            );
        });

        it('archivePackage() memanggil POST /mitra/packages/{id}/archive', () => {
            archivePackage(10);
            expect(router.post).toHaveBeenCalledWith(
                '/mitra/packages/10/archive',
                {},
                undefined,
            );
        });

        it('restorePackage() memanggil POST /mitra/packages/{id}/restore', () => {
            restorePackage(10);
            expect(router.post).toHaveBeenCalledWith(
                '/mitra/packages/10/restore',
                {},
                undefined,
            );
        });

        it('getCompletePhase2() memanggil GET /mitra/packages/{id}/complete-phase2', () => {
            getCompletePhase2(10);
            expect(router.get).toHaveBeenCalledWith(
                '/mitra/packages/10/complete-phase2',
                {},
                undefined,
            );
        });
    });

    // ==========================================
    // 2. TRIPS
    // ==========================================
    describe('Trips', () => {
        it('getTrips() memanggil GET /mitra/trips', () => {
            getTrips({ status: 'active' });
            expect(router.get).toHaveBeenCalledWith(
                '/mitra/trips',
                { status: 'active' },
                undefined,
            );
        });

        it('getTripManifestUrl() menghasilkan URL manifest dengan benar', () => {
            expect(getTripManifestUrl(5)).toBe('/mitra/trips/5/manifest');
        });
    });

    // ==========================================
    // 3. BOOKINGS
    // ==========================================
    describe('Bookings', () => {
        it('getBookings() memanggil GET /mitra/bookings', () => {
            getBookings({ page: 2 });
            expect(router.get).toHaveBeenCalledWith(
                '/mitra/bookings',
                { page: 2 },
                undefined,
            );
        });

        it('verifyPayment() memanggil POST /mitra/bookings/{id}/verify', () => {
            verifyPayment(1);
            expect(router.post).toHaveBeenCalledWith(
                '/mitra/bookings/1/verify',
                {},
                undefined,
            );
        });

        it('rejectPayment() memanggil POST /mitra/bookings/{id}/reject', () => {
            const data = { reason: 'Bukti tidak valid' };
            rejectPayment(1, data);
            expect(router.post).toHaveBeenCalledWith(
                '/mitra/bookings/1/reject',
                data,
                undefined,
            );
        });

        it('cancelBooking() memanggil POST /mitra/bookings/{id}/cancel', () => {
            const data = { reason: 'Batal' };
            cancelBooking(1, data);
            expect(router.post).toHaveBeenCalledWith(
                '/mitra/bookings/1/cancel',
                data,
                undefined,
            );
        });

        it('uploadPaymentProof() memanggil POST /mitra/bookings/{id}/upload-proof', () => {
            const data = { file: 'proof.jpg' };
            uploadPaymentProof(1, data);
            expect(router.post).toHaveBeenCalledWith(
                '/mitra/bookings/1/upload-proof',
                data,
                undefined,
            );
        });

        it('createManualBooking() memanggil POST /mitra/packages/{id}/manual-booking', () => {
            const data = { customer: 'Budi' };
            createManualBooking(10, data);
            expect(router.post).toHaveBeenCalledWith(
                '/mitra/packages/10/manual-booking',
                data,
                undefined,
            );
        });
    });

    // ==========================================
    // 4. REFUNDS
    // ==========================================
    describe('Refunds', () => {
        it('getRefunds() memanggil GET /mitra/refunds', () => {
            getRefunds({ status: 'pending' });
            expect(router.get).toHaveBeenCalledWith(
                '/mitra/refunds',
                { status: 'pending' },
                undefined,
            );
        });

        it('approveRefund() memanggil POST /mitra/refunds/{id}/approve', () => {
            const data = { amount: 500000 };
            approveRefund(1, data);
            expect(router.post).toHaveBeenCalledWith(
                '/mitra/refunds/1/approve',
                data,
                undefined,
            );
        });

        it('rejectRefund() memanggil POST /mitra/refunds/{id}/reject', () => {
            const data = { reason: 'Tidak sesuai' };
            rejectRefund(1, data);
            expect(router.post).toHaveBeenCalledWith(
                '/mitra/refunds/1/reject',
                data,
                undefined,
            );
        });
    });

    // ==========================================
    // 5. PROFILE & PASSWORD
    // ==========================================
    describe('Profile & Password', () => {
        it('submitOnboarding() memanggil POST /mitra/onboarding', () => {
            const data = { company: 'PT Maju' };
            submitOnboarding(data);
            expect(router.post).toHaveBeenCalledWith(
                '/mitra/onboarding',
                data,
                undefined,
            );
        });

        it('updateProfile() memanggil POST /mitra/profile', () => {
            const data = { name: 'Budi' };
            updateProfile(data);
            expect(router.post).toHaveBeenCalledWith(
                '/mitra/profile',
                data,
                undefined,
            );
        });

        it('updateBankAccounts() memanggil PUT /mitra/profile/bank-accounts', () => {
            const data = { bank: 'BCA', account: '123' };
            updateBankAccounts(data);
            expect(router.put).toHaveBeenCalledWith(
                '/mitra/profile/bank-accounts',
                data,
                undefined,
            );
        });

        it('updatePassword() memanggil PUT /mitra/password', () => {
            const data = { current_password: 'old', password: 'new' };
            updatePassword(data);
            expect(router.put).toHaveBeenCalledWith(
                '/mitra/password',
                data,
                undefined,
            );
        });
    });

    // ==========================================
    // 6. EDGE CASES (Default Params)
    // ==========================================
    describe('Edge Cases — Default Parameters', () => {
        it('getPackages() tanpa argumen menggunakan default {}', () => {
            getPackages();
            expect(router.get).toHaveBeenCalledWith(
                '/mitra/packages',
                {},
                undefined,
            );
        });

        it('getTrips() tanpa argumen menggunakan default {}', () => {
            getTrips();
            expect(router.get).toHaveBeenCalledWith(
                '/mitra/trips',
                {},
                undefined,
            );
        });

        it('getBookings() tanpa argumen menggunakan default {}', () => {
            getBookings();
            expect(router.get).toHaveBeenCalledWith(
                '/mitra/bookings',
                {},
                undefined,
            );
        });

        it('getRefunds() tanpa argumen menggunakan default {}', () => {
            getRefunds();
            expect(router.get).toHaveBeenCalledWith(
                '/mitra/refunds',
                {},
                undefined,
            );
        });
    });

    // ==========================================
    // 7. OPTIONS FORWARDING
    // ==========================================
    describe('Options Forwarding', () => {
        it('meneruskan VisitOptions ke router.post (createPackage)', () => {
            const data = { name: 'Paket' };
            const options = { preserveScroll: true, onSuccess: vi.fn() };
            createPackage(data, options);
            expect(router.post).toHaveBeenCalledWith(
                '/mitra/packages',
                data,
                options,
            );
        });

        it('meneruskan VisitOptions ke router.delete (deletePackage)', () => {
            const options = { onFinish: vi.fn() };
            deletePackage(10, options);
            expect(router.delete).toHaveBeenCalledWith(
                '/mitra/packages/10',
                options,
            );
        });
    });
});
