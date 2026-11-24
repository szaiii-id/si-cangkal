import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';

// 1. Mock modul Inertia SEBELUM import API
vi.mock('@inertiajs/svelte', () => ({
    router: {
        post: vi.fn(),
        get: vi.fn(),
        put: vi.fn(),
        delete: vi.fn(),
    },
}));

import { router } from '@inertiajs/svelte';
import {
    updateFcmToken,
    markNotificationAsRead,
    markAllNotificationsAsRead,
} from '../notifications';

describe('Notifications API Wrapper', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    afterEach(() => {
        vi.restoreAllMocks();
    });

    // ==========================================
    // 1. updateFcmToken()
    // ==========================================
    describe('updateFcmToken()', () => {
        it('memanggil POST /notifications/fcm dengan payload token', () => {
            const payload = { token: 'fcm-token-123' };
            updateFcmToken(payload);
            expect(router.post).toHaveBeenCalledWith(
                '/notifications/fcm',
                payload,
                undefined,
            );
        });

        it('meneruskan VisitOptions ke router.post', () => {
            const payload = { token: 'fcm-token-123' };
            const options = {
                preserveScroll: true,
                preserveState: true,
                onSuccess: vi.fn(),
            };
            updateFcmToken(payload, options);
            expect(router.post).toHaveBeenCalledWith(
                '/notifications/fcm',
                payload,
                options,
            );
        });

        it('meneruskan token string panjang apa adanya', () => {
            const longToken = 'a'.repeat(150);
            updateFcmToken({ token: longToken });
            expect(router.post).toHaveBeenCalledWith(
                '/notifications/fcm',
                { token: longToken },
                undefined,
            );
        });
    });

    // ==========================================
    // 2. markNotificationAsRead()
    // ==========================================
    describe('markNotificationAsRead()', () => {
        it('memanggil POST /notifications/{id}/read', () => {
            markNotificationAsRead('uuid-123');
            expect(router.post).toHaveBeenCalledWith(
                '/notifications/uuid-123/read',
                {},
                undefined,
            );
        });

        it('meneruskan VisitOptions (onSuccess, preserveScroll)', () => {
            const options = {
                preserveScroll: true,
                onSuccess: vi.fn(),
            };
            markNotificationAsRead('uuid-123', options);
            expect(router.post).toHaveBeenCalledWith(
                '/notifications/uuid-123/read',
                {},
                options,
            );
        });

        it('menerima ID bertipe number', () => {
            markNotificationAsRead(456);
            expect(router.post).toHaveBeenCalledWith(
                '/notifications/456/read',
                {},
                undefined,
            );
        });
    });

    // ==========================================
    // 3. markAllNotificationsAsRead()
    // ==========================================
    describe('markAllNotificationsAsRead()', () => {
        it('memanggil POST /notifications/mark-all-read', () => {
            markAllNotificationsAsRead();
            expect(router.post).toHaveBeenCalledWith(
                '/notifications/mark-all-read',
                {},
                undefined,
            );
        });

        it('meneruskan VisitOptions (preserveScroll)', () => {
            const options = { preserveScroll: true };
            markAllNotificationsAsRead(options);
            expect(router.post).toHaveBeenCalledWith(
                '/notifications/mark-all-read',
                {},
                options,
            );
        });
    });

    // ==========================================
    // 4. EDGE CASES & GUARD
    // ==========================================
    describe('Edge Cases & Guard', () => {
        it('tidak memanggil method router lainnya (guard regresi)', () => {
            updateFcmToken({ token: 'abc' });
            markNotificationAsRead('uuid-123');
            markAllNotificationsAsRead();

            expect(router.get).not.toHaveBeenCalled();
            expect(router.put).not.toHaveBeenCalled();
            expect(router.delete).not.toHaveBeenCalled();
        });
    });
});
