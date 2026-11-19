import { router } from '@inertiajs/svelte';
import type { RequestPayload } from '@inertiajs/core';

/**
 * Mengambil tipe parameter ke-3 (options) secara otomatis dari method router.
 * Ini adalah cara paling aman dan otomatis jika tipe internal Inertia berubah.
 */
type RouterOptions = Parameters<typeof router.post>[2];

export const login = (data: RequestPayload, options?: RouterOptions) =>
    router.post('/login', data, options);

export const register = (data: RequestPayload, options?: RouterOptions) =>
    router.post('/register', data, options);

export const registerMitra = (data: RequestPayload, options?: RouterOptions) =>
    router.post('/register/mitra', data, options);

export const logout = (options?: RouterOptions) =>
    router.post('/logout', {}, options);

export const forgotPassword = (data: RequestPayload, options?: RouterOptions) =>
    router.post('/forgot-password', data, options);

export const resetPassword = (data: RequestPayload, options?: RouterOptions) =>
    router.post('/reset-password', data, options);

export const changePassword = (data: RequestPayload, options?: RouterOptions) =>
    router.put('/change-password', data, options);