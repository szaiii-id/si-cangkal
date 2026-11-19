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
    getUsers,
    getUserDetail,
    createAdmin,
    updateUser,
    deleteUser,
    resetUserPassword,
    getClients,
    getBookings,
    regenerateETicket,
    getTrips,
    getPackages,
    getRefunds,
    getRevenueReport,
    getMitraReport,
} from '../admin';

describe('Admin API Wrapper', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    // ==========================================
    // 1. USERS
    // ==========================================
    describe('Users', () => {
        it('getUsers() memanggil GET /admin/users', () => {
            getUsers({ role: 'admin' });
            expect(router.get).toHaveBeenCalledWith('/admin/users', { role: 'admin' }, undefined);
        });

        it('getUserDetail() memanggil GET /admin/users/{id}', () => {
            getUserDetail(1);
            expect(router.get).toHaveBeenCalledWith('/admin/users/1', {}, undefined);
        });

        it('createAdmin() memanggil POST /admin/users', () => {
            const data = { name: 'Admin Baru', email: 'admin@test.com' };
            createAdmin(data);
            expect(router.post).toHaveBeenCalledWith('/admin/users', data, undefined);
        });

        it('updateUser() memanggil PUT /admin/users/{id}', () => {
            const data = { name: 'Update Nama' };
            updateUser(1, data);
            expect(router.put).toHaveBeenCalledWith('/admin/users/1', data, undefined);
        });

        it('deleteUser() memanggil DELETE /admin/users/{id}', () => {
            deleteUser(1);
            expect(router.delete).toHaveBeenCalledWith('/admin/users/1', undefined);
        });

        it('resetUserPassword() memanggil POST /admin/users/{id}/reset-password', () => {
            resetUserPassword(1);
            expect(router.post).toHaveBeenCalledWith('/admin/users/1/reset-password', {}, undefined);
        });
    });

    // ==========================================
    // 2. CLIENTS & MITRAS
    // ==========================================
    describe('Clients & Mitras', () => {
        it('getClients() memanggil GET /admin/clients', () => {
            getClients({ search: 'budi' });
            expect(router.get).toHaveBeenCalledWith('/admin/clients', { search: 'budi' }, undefined);
        });

    });

    // ==========================================
    // 3. BOOKINGS
    // ==========================================
    describe('Bookings', () => {
        it('getBookings() memanggil GET /admin/bookings', () => {
            getBookings({ status: 'pending' });
            expect(router.get).toHaveBeenCalledWith('/admin/bookings', { status: 'pending' }, undefined);
        });

        it('regenerateETicket() memanggil POST /admin/bookings/{id}/regenerate-eticket', () => {
            regenerateETicket(99);
            expect(router.post).toHaveBeenCalledWith('/admin/bookings/99/regenerate-eticket', {}, undefined);
        });
    });

    // ==========================================
    // 4. TRIPS & PACKAGES
    // ==========================================
    describe('Trips & Packages', () => {
        it('getTrips() memanggil GET /admin/trips', () => {
            getTrips({ page: 1 });
            expect(router.get).toHaveBeenCalledWith('/admin/trips', { page: 1 }, undefined);
        });

        it('getPackages() memanggil GET /admin/packages', () => {
            getPackages({ page: 1 });
            expect(router.get).toHaveBeenCalledWith('/admin/packages', { page: 1 }, undefined);
        });
    });

    // ==========================================
    // 5. REFUNDS & REPORTS
    // ==========================================
    describe('Refunds & Reports', () => {
        it('getRefunds() memanggil GET /admin/refunds', () => {
            getRefunds({ status: 'approved' });
            expect(router.get).toHaveBeenCalledWith('/admin/refunds', { status: 'approved' }, undefined);
        });

        it('getRevenueReport() memanggil GET /admin/reports', () => {
            getRevenueReport({ month: '10', year: '2026' });
            expect(router.get).toHaveBeenCalledWith('/admin/reports', { month: '10', year: '2026' }, undefined);
        });

        it('getMitraReport() memanggil GET /admin/reports/mitra', () => {
            getMitraReport({ month: '10' });
            expect(router.get).toHaveBeenCalledWith('/admin/reports/mitra', { month: '10' }, undefined);
        });
    });

    // ==========================================
    // 6. OPTIONS FORWARDING
    // ==========================================
    describe('Options Forwarding', () => {
        it('meneruskan VisitOptions ke updateUser', () => {
            const data = { name: 'Test' };
            const options = { preserveScroll: true, onSuccess: vi.fn() };
            updateUser(1, data, options);
            expect(router.put).toHaveBeenCalledWith('/admin/users/1', data, options);
        });

        it('meneruskan VisitOptions ke getUsers', () => {
            const options = { onFinish: vi.fn() };
            getUsers({ role: 'admin' }, options);
            expect(router.get).toHaveBeenCalledWith('/admin/users', { role: 'admin' }, options);
        });
    });
});