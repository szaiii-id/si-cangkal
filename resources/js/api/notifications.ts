import { router } from '@inertiajs/svelte';
import type { RequestPayload } from '@inertiajs/core';

// Best practice: Tipe otomatis dari method router
type NotificationOptions = Parameters<typeof router.post>[2];

export const updateFcmToken = (
    data: { token: string },
    options?: NotificationOptions,
) => router.post('/notifications/fcm', data, options);

/**
 * Menandai satu notifikasi sebagai sudah dibaca.
 */
export const markNotificationAsRead = (
    id: string | number,
    options?: NotificationOptions,
) => router.post(`/notifications/${id}/read`, {}, options);

/**
 * Menandai semua notifikasi sebagai sudah dibaca.
 */
export const markAllNotificationsAsRead = (
    options?: NotificationOptions,
) => router.post('/notifications/mark-all-read', {}, options);