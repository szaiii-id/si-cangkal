// e2e/fixtures/auth.ts
/**
 * Helper untuk autentikasi E2E.
 *
 * Semua login via `/_test/login` (HTTP) — BUKAN UI.
 * Alasan: cepat (~50ms vs ~2s), deterministik, tidak flaky.
 *
 * Pemakaian:
 *   import { loginAs, logout, SEED_USERS } from '../fixtures/auth';
 *
 *   await loginAs(page, 'admin');
 *   await logout(page);
 */

import type { Page } from '@playwright/test';
import { apiPost } from './api';

// ==========================================
// TIPE
// ==========================================

export type SeedRole = 'admin' | 'mitra' | 'client';

export type SeedUser = {
    email: string;
    username: string;
    password: string;
    name: string;
    role: SeedRole;
    timezone: string;
    dashboardPath: string;
};

// ==========================================
// KONSTANTA USER SEED
// ==========================================
/**
 * User seed (sesuai `TestSeeder`).
 *
 * ⚠️ Password harus sama dengan yang di-seed.
 * Kalau `TestSeeder` diubah, update di sini juga.
 */
export const SEED_USERS: Record<SeedRole, SeedUser> = {
    admin: {
        email: 'admin@test.com',
        username: 'admintest',
        password: 'password',
        name: 'Admin Test',
        role: 'admin',
        timezone: 'Asia/Makassar',
        dashboardPath: '/admin/dashboard',
    },
    mitra: {
        email: 'mitra@test.com',
        username: 'mitratest',
        password: 'password',
        name: 'Mitra Test',
        role: 'mitra',
        timezone: 'Asia/Makassar',
        dashboardPath: '/mitra/dashboard',
    },
    client: {
        email: 'client@test.com',
        username: 'clienttest',
        password: 'password',
        name: 'Client Test',
        role: 'client',
        timezone: 'Asia/Makassar',
        dashboardPath: '/client/dashboard',
    },
} as const;

/**
 * User tambahan (untuk test yang butuh >1 mitra/client).
 */
export const SEED_EXTRA = {
    mitra2: {
        email: 'mitra2@test.com',
        username: 'mitradua',
        password: 'password',
        name: 'Mitra Dua',
        role: 'mitra' as const,
        timezone: 'Asia/Makassar',
        dashboardPath: '/mitra/dashboard',
    },
} as const;

// ==========================================
// LOGIN
// ==========================================

/**
 * Login sebagai user seed via `/_test/login`.
 *
 * ⚠️ Setelah login, session cookie ter-set di browser context.
 * Semua request berikutnya otomatis authenticated.
 *
 * @example
 *   await loginAs(page, 'admin');
 *   await page.goto('/admin/users');  // sudah login
 */
export async function loginAs(
    page: Page,
    role: SeedRole,
    options: { password?: string } = {},
): Promise<void> {
    const user = SEED_USERS[role];
    const password = options.password ?? user.password;

    await apiPost(page, '/_test/login', {
        email: user.email,
        password,
    });
}

/**
 * Login user non-seed (mis. hasil `createUser`).
 *
 * @example
 *   await seed.createUser(page, { email: 'new@test.com', ... });
 *   await loginAsCustom(page, 'new@test.com', 'password123');
 */
export async function loginAsCustom(
    page: Page,
    email: string,
    password = 'password',
): Promise<void> {
    await apiPost(page, '/_test/login', { email, password });
}

// ==========================================
// LOGOUT
// ==========================================

/**
 * Logout via `/_test/logout` + clear cookie.
 *
 * ⚠️ Ini memanggil server (session di-invalidate) — lebih bersih
 * daripada hanya clear cookie.
 */
export async function logout(page: Page): Promise<void> {
    try {
        await apiPost(page, '/_test/logout');
    } catch {
        // Abaikan — mungkin belum login
    }

    // Clear cookie untuk jaga-jaga
    await page.context().clearCookies();
}

/**
 * Force logout — clear cookie TANPA server call.
 *
 * Gunakan untuk setup test yang butuh "guest" state
 * tanpa harus memanggil server.
 */
export async function forceLogout(page: Page): Promise<void> {
    await page.context().clearCookies();
}

// ==========================================
// USER INFO
// ==========================================

/**
 * Get user seed berdasarkan role.
 *
 * @example
 *   const admin = getSeedUser('admin');
 *   console.log(admin.email);  // admin@test.com
 */
export function getSeedUser(role: SeedRole): SeedUser {
    return SEED_USERS[role];
}

// ==========================================
// VERIFIKASI
// ==========================================

/**
 * Verifikasi user sudah login dengan role yang benar.
 *
 * Cek via `page.goto(dashboardPath)` — kalau redirect ke login,
 * berarti session tidak valid.
 *
 * @example
 *   await loginAs(page, 'admin');
 *   await assertLoggedIn(page, 'admin');  // throw kalau gagal
 */
export async function assertLoggedIn(
    page: Page,
    role: SeedRole,
): Promise<void> {
    const user = SEED_USERS[role];

    await page.goto(user.dashboardPath, {
        waitUntil: 'domcontentloaded',
    });

    if (page.url().includes('/login')) {
        throw new Error(
            `assertLoggedIn: user ${user.email} tidak ter-login. ` +
                `URL: ${page.url()}. ` +
                `Cek apakah /_test/login berhasil.`,
        );
    }
}
