import { describe, it, expect, vi, beforeEach } from 'vitest';

// Mock modul Inertia
vi.mock('@inertiajs/svelte', () => ({
    router: {
        post: vi.fn(),
    },
}));

import { router } from '@inertiajs/svelte';
import { submitContactForm, type ContactPayload } from '../contact';

describe('Contact API Wrapper', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    // ==========================================
    // 1. HAPPY PATH — Endpoint & Payload
    // ==========================================
    describe('Happy Path', () => {
        it('submitContactForm() memanggil POST /contact dengan payload valid', () => {
            const payload: ContactPayload = {
                nama: 'Budi Santoso',
                email: 'budi@example.com',
                pesan: 'Saya ingin bertanya tentang paket wisata.',
            };

            submitContactForm(payload);

            expect(router.post).toHaveBeenCalledOnce();
            expect(router.post).toHaveBeenCalledWith('/contact', payload, undefined);
        });
    });

    // ==========================================
    // 2. OPTIONS FORWARDING
    // ==========================================
    describe('Options Forwarding', () => {
        it('meneruskan VisitOptions ke router.post', () => {
            const payload: ContactPayload = {
                nama: 'Ani',
                email: 'ani@example.com',
                pesan: 'Halo',
            };
            const options = {
                preserveScroll: true,
                onSuccess: vi.fn(),
                onError: vi.fn(),
            };

            submitContactForm(payload, options);

            expect(router.post).toHaveBeenCalledWith('/contact', payload, options);
        });
    });

    // ==========================================
    // 3. EDGE CASES & CORNER CASES
    // ==========================================
    describe('Edge Cases', () => {
        it('meneruskan string kosong pada semua field', () => {
            const emptyPayload: ContactPayload = {
                nama: '',
                email: '',
                pesan: '',
            };

            submitContactForm(emptyPayload);

            expect(router.post).toHaveBeenCalledWith('/contact', emptyPayload, undefined);
        });

        it('meneruskan teks panjang (pesan ekstrem)', () => {
            const longPayload: ContactPayload = {
                nama: 'Test User',
                email: 'test@example.com',
                pesan: 'A'.repeat(5000),
            };

            submitContactForm(longPayload);

            expect(router.post).toHaveBeenCalledWith('/contact', longPayload, undefined);
        });

        it('meneruskan karakter khusus & unicode', () => {
            const specialPayload: ContactPayload = {
                nama: 'Ñoño Budí',
                email: 'test+tag@example.co.id',
                pesan: 'Halo! <script>alert("xss")</script> & "quotes"',
            };

            submitContactForm(specialPayload);

            expect(router.post).toHaveBeenCalledWith('/contact', specialPayload, undefined);
        });
    });

    // ==========================================
    // 4. NEGATIVE PATH — Method Guard
    // ==========================================
    describe('Negative Path', () => {
        it('submitContactForm() tidak memanggil method router lain', () => {
            const payload: ContactPayload = {
                nama: 'Test',
                email: 'test@example.com',
                pesan: 'Halo',
            };

            submitContactForm(payload);

            // Pastikan hanya memanggil post()
            expect(router.post).toHaveBeenCalledOnce();
        });
    });
});