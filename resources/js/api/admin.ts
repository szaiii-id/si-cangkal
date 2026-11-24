import { router } from '@inertiajs/svelte';
import type { RequestPayload } from '@inertiajs/core';

// Best practice: Ambil tipe parameter ke-3 secara otomatis dari method router.
// Menghindari error 'optimistic' dan otomatis sinkron dengan versi Inertia Anda.
type AdminOptions = Parameters<typeof router.post>[2];

// ==========================================
// USERS (Admin & Mitra)
// ==========================================
export const getUsers = (params: RequestPayload, options?: AdminOptions) =>
    router.get('/admin/users', params, options);

export const getUserDetail = (id: number, options?: AdminOptions) =>
    router.get(`/admin/users/${id}`, {}, options);

export const createAdmin = (data: RequestPayload, options?: AdminOptions) =>
    router.post('/admin/users', data, options);

export const updateUser = (
    id: number,
    data: RequestPayload,
    options?: AdminOptions,
) => router.put(`/admin/users/${id}`, data, options);

export const deleteUser = (id: number, options?: AdminOptions) =>
    router.delete(`/admin/users/${id}`, options);

export const resetUserPassword = (id: number, options?: AdminOptions) =>
    router.post(`/admin/users/${id}/reset-password`, {}, options);

// ==========================================
// CLIENTS (Read-Only)
// ==========================================
export const getClients = (params: RequestPayload, options?: AdminOptions) =>
    router.get('/admin/clients', params, options);

// ==========================================
// BOOKINGS
// ==========================================
export const getBookings = (params: RequestPayload, options?: AdminOptions) =>
    router.get('/admin/bookings', params, options);

export const regenerateETicket = (bookingId: number, options?: AdminOptions) =>
    router.post(`/admin/bookings/${bookingId}/regenerate-eticket`, {}, options);

// ==========================================
// TRIPS
// ==========================================
export const getTrips = (params: RequestPayload, options?: AdminOptions) =>
    router.get('/admin/trips', params, options);

export const getPackages = (params: RequestPayload, options?: AdminOptions) =>
    router.get('/admin/packages', params, options);

// ==========================================
// REFUNDS
// ==========================================
export const getRefunds = (params: RequestPayload, options?: AdminOptions) =>
    router.get('/admin/refunds', params, options);

// ==========================================
// REPORTS
// ==========================================
export const getRevenueReport = (
    params: RequestPayload,
    options?: AdminOptions,
) => router.get('/admin/reports', params, options);

export const getMitraReport = (
    params: RequestPayload,
    options?: AdminOptions,
) => router.get('/admin/reports/mitra', params, options);
