import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';

// 1. Mock modul Inertia SEBELUM mengimpor auth.ts
vi.mock('@inertiajs/svelte', () => ({
    router: {
        post: vi.fn(),
        put: vi.fn(),
        get: vi.fn(),
        delete: vi.fn(),
    },
}));

// 2. Import fungsi yang akan diuji
import { router } from '@inertiajs/svelte';
import {
    login,
    register,
    registerMitra,
    logout,
    forgotPassword,
    resetPassword,
    changePassword,
} from '../auth';

describe('Auth API Wrapper (Frontend Pure Logic)', () => {
    // Bersihkan riwayat mock sebelum setiap test
    beforeEach(() => {
        vi.clearAllMocks();
    });

    afterEach(() => {
        vi.restoreAllMocks();
    });

    // ==========================================
    // 1. Happy Path (Memastikan URL, Method, & Payload Benar)
    // ==========================================
    describe('Happy Path — Endpoint & Method', () => {
        it('login() memanggil POST /login', () => {
            const payload = { email: 'user@example.com', password: 'password123' };
            login(payload);
            expect(router.post).toHaveBeenCalledOnce();
            expect(router.post).toHaveBeenCalledWith('/login', payload, undefined);
        });

        it('register() memanggil POST /register', () => {
            const payload = { name: 'John Doe', email: 'john@example.com', password: 'secret' };
            register(payload);
            expect(router.post).toHaveBeenCalledWith('/register', payload, undefined);
        });

        it('registerMitra() memanggil POST /register/mitra', () => {
            const payload = { company: 'PT Maju Jaya', email: 'mitra@example.com' };
            registerMitra(payload);
            expect(router.post).toHaveBeenCalledWith('/register/mitra', payload, undefined);
        });

        it('logout() memanggil POST /logout dengan payload kosong {}', () => {
            logout();
            expect(router.post).toHaveBeenCalledWith('/logout', {}, undefined);
        });

        it('forgotPassword() memanggil POST /forgot-password', () => {
            const payload = { email: 'user@example.com' };
            forgotPassword(payload);
            expect(router.post).toHaveBeenCalledWith('/forgot-password', payload, undefined);
        });

        it('resetPassword() memanggil POST /reset-password', () => {
            const payload = { token: 'token-abc-123', password: 'newPassword' };
            resetPassword(payload);
            expect(router.post).toHaveBeenCalledWith('/reset-password', payload, undefined);
        });

        it('changePassword() memanggil PUT /change-password', () => {
            const payload = { current_password: 'oldPassword', password: 'newPassword' };
            changePassword(payload);
            expect(router.put).toHaveBeenCalledOnce();
            expect(router.put).toHaveBeenCalledWith('/change-password', payload, undefined);
        });
    });

    // ==========================================
    // 2. Options Forwarding (Memastikan VisitOptions diteruskan)
    // ==========================================
    describe('Options Forwarding', () => {
        it('meneruskan VisitOptions ke router.post (contoh: login)', () => {
            const payload = { email: 'user@example.com', password: 'password123' };
            const options = { preserveScroll: true, onSuccess: vi.fn() };
            
            login(payload, options);
            
            expect(router.post).toHaveBeenCalledWith('/login', payload, options);
        });

        it('meneruskan VisitOptions ke router.put (contoh: changePassword)', () => {
            const payload = { current_password: 'old', password: 'new' };
            const options = { preserveState: true, onError: vi.fn() };
            
            changePassword(payload, options);
            
            expect(router.put).toHaveBeenCalledWith('/change-password', payload, options);
        });

        it('meneruskan VisitOptions ke logout', () => {
            const options = { onFinish: vi.fn() };
            logout(options);
            expect(router.post).toHaveBeenCalledWith('/logout', {}, options);
        });
    });

    // ==========================================
    // 3. Edge Cases & Negative Path (Batas Fungsi)
    // ==========================================
    describe('Edge Cases & Negative Path', () => {
        it('meneruskan payload kosong pada login', () => {
            login({});
            expect(router.post).toHaveBeenCalledWith('/login', {}, undefined);
        });

        it('meneruskan objek kompleks pada register', () => {
            const complexPayload = { 
                name: 'Budi',
                roles: ['user', 'mitra'],
                meta: { source: 'web' }
            };
            register(complexPayload);
            expect(router.post).toHaveBeenCalledWith('/register', complexPayload, undefined);
        });

        it('memastikan login() TIDAK memanggil method router lainnya', () => {
            login({ email: 'a@b.com', password: 'x' });
            expect(router.get).not.toHaveBeenCalled();
            expect(router.put).not.toHaveBeenCalled();
            expect(router.delete).not.toHaveBeenCalled();
        });

        it('memastikan changePassword() TIDAK memanggil router.post', () => {
            changePassword({ current_password: 'a', password: 'b' });
            expect(router.post).not.toHaveBeenCalled();
        });
    });
});