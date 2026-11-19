// e2e/auth/logout.spec.ts
/**
 * Test: Auth — Logout
 *
 * Menguji:
 * - Access control (hanya user login yang bisa logout)
 * - Contract (tombol logout ada di layout)
 * - Mutation (logout → redirect `/`, session invalid, cookie remember dihapus)
 * - Setelah logout, akses dashboard → redirect `/login`
 *
 * Backend: AuthController@logout → AuthService::logout()
 * - Auth::logout()
 * - session()->invalidate()
 * - session()->regenerateToken()
 * - forgetRememberCookie() — hapus semua cookie `remember_*`
 * - Redirect ke `/` (landing)
 *
 * Route: POST /logout (middleware `auth`)
 *
 * CATATAN PENTING:
 * - Logout di FE memakai `router.post('/logout')` (lihat `api/auth.ts`).
 * - Setelah logout, Inertia follow redirect `GET /` — sehingga cookie
 *   session di-set ulang (session kosong = guest), bukan hilang.
 * - Test yang assert "setelah logout → redirect login" menunggu response
 *   POST `/logout` & follow-redirect selesai (dan elemen landing page ter-render)
 *   tanpa menggantung pada `networkidle` yang rawan timeout jika ada koneksi
 *   latar belakang.
 * - Test ini TIDAK mengubah data seed (hanya session), jadi tidak perlu
 *   `describe.serial` untuk cleanup state DB.
 */

import { test, expect } from '../fixtures/base';
import { SEL } from '../fixtures/selectors';
import { SEED_USERS } from '../fixtures/auth';

// ==========================================
// HELPER
// ==========================================

/**
 * Klik tombol logout + tunggu redirect ke `/` + tunggu response logout & landing selesai.
 *
 * Kenapa tidak mengandalkan `waitForLoadState('networkidle')` keras?
 * - `router.post('/logout')` di FE melakukan:
 *   1. POST /logout → 302/303 redirect ke `/`
 *   2. Inertia follow redirect → GET / → render landing
 * - Menunggu response `POST /logout` selesai + `waitForURL('/')` + `domcontentloaded`
 *   memastikan cookie session baru sudah diterima browser tanpa terblokir oleh
 *   request background (mis. Firebase/fonts/assets) yang menahan `networkidle`.
 */
async function logoutAndWait(
    page: import('@playwright/test').Page,
    buttonName: RegExp = /Keluar/i,
): Promise<void> {
    await Promise.all([
        page.waitForResponse(
            (res) =>
                res.url().endsWith('/logout') &&
                res.request().method() === 'POST',
            { timeout: 20_000 },
        ),
        page.waitForURL('/', { timeout: 20_000 }),
        page.getByRole('button', { name: buttonName }).click(),
    ]);

    await page.waitForLoadState('domcontentloaded');
    await page.waitForLoadState('networkidle', { timeout: 3_000 }).catch(() => {});
}

// ==========================================
// TEST SUITE
// ==========================================

test.describe('Auth — Logout', () => {
    // ==========================================
    // ACCESS CONTROL
    // ==========================================

    test.describe('Access Control', () => {
        test('guest akses POST /logout tanpa CSRF → 419', async ({
            page,
            asGuest,
        }) => {
            await asGuest();

            // Tanpa CSRF token → Laravel return 419 (VerifyCsrfToken
            // lebih dulu dari middleware auth)
            const response = await page.request.post('/logout', {
                failOnStatusCode: false,
                maxRedirects: 0,
            });

            expect(response.status()).toBe(419);
        });

        test('guest akses POST /logout dengan CSRF → redirect /login', async ({
            page,
            asGuest,
        }) => {
            await asGuest();

            // Set cookie XSRF-TOKEN dulu
            await page.goto('/');
            const csrfToken = await page.evaluate(() => {
                const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
                return match ? decodeURIComponent(match[1]) : '';
            });

            const response = await page.request.post('/logout', {
                headers: {
                    'X-XSRF-TOKEN': csrfToken,
                    Accept: 'text/html',
                },
                failOnStatusCode: false,
                maxRedirects: 0,
            });

            // Middleware `auth` redirect ke login (302)
            expect(response.status()).toBe(302);
            expect(response.headers()['location']).toContain('/login');
        });
    });

    // ==========================================
    // CONTRACT — TOMBOL LOGOUT
    // ==========================================

    test.describe('Contract — Tombol Logout', () => {
        test('client dashboard punya tombol logout', async ({
            page,
            asClient,
        }) => {
            await asClient();
            await page.goto('/client/dashboard');

            await expect(SEL.LAYOUT.logout.button(page)).toBeVisible();
        });

        test('admin dashboard punya tombol logout', async ({
            page,
            asAdmin,
        }) => {
            await asAdmin();
            await page.goto('/admin/dashboard');

            await expect(SEL.LAYOUT.logout.button(page)).toBeVisible();
        });

        test('mitra dashboard punya tombol logout', async ({
            page,
            asMitra,
        }) => {
            await asMitra();
            await page.goto('/mitra/dashboard');

            await expect(SEL.LAYOUT.logout.button(page)).toBeVisible();
        });

        test('halaman change-password punya tombol logout', async ({
            page,
            asClient,
        }) => {
            await asClient();
            await page.goto('/change-password');

            await expect(
                page.getByRole('button', { name: /Keluar/i }),
            ).toBeVisible();
        });
    });

    // ==========================================
    // MUTATION — LOGOUT CLIENT
    // ==========================================

    test.describe('Mutation — Logout Client', () => {
        test('klik logout → redirect ke /', async ({ page, asClient }) => {
            await asClient();
            await page.goto('/client/dashboard');

            await logoutAndWait(page);

            await expect(page).toHaveURL('/');
        });

        test('setelah logout, akses /client/dashboard → redirect /login', async ({
            page,
            asClient,
        }) => {
            await asClient();
            await page.goto('/client/dashboard');

            await logoutAndWait(page);

            await page.goto('/client/dashboard');

            await expect(page).toHaveURL('/login');
        });

        test('setelah logout, akses /client/profile → redirect /login', async ({
            page,
            asClient,
        }) => {
            await asClient();
            await page.goto('/client/dashboard');

            await logoutAndWait(page);

            await page.goto('/client/profile');

            await expect(page).toHaveURL('/login');
        });
    });

    // ==========================================
    // MUTATION — LOGOUT ADMIN
    // ==========================================

    test.describe('Mutation — Logout Admin', () => {
        test('klik logout admin → redirect ke /', async ({ page, asAdmin }) => {
            await asAdmin();
            await page.goto('/admin/dashboard');

            await logoutAndWait(page);

            await expect(page).toHaveURL('/');
        });

        test('setelah logout admin, akses /admin/users → redirect /login', async ({
            page,
            asAdmin,
        }) => {
            await asAdmin();
            await page.goto('/admin/dashboard');

            await logoutAndWait(page);

            await page.goto('/admin/users');
            await expect(page).toHaveURL('/login');
        });
    });

    // ==========================================
    // MUTATION — LOGOUT MITRA
    // ==========================================

    test.describe('Mutation — Logout Mitra', () => {
        test('klik logout mitra → redirect ke /', async ({ page, asMitra }) => {
            await asMitra();
            await page.goto('/mitra/dashboard');

            await logoutAndWait(page);

            await expect(page).toHaveURL('/');
        });

        test('setelah logout mitra, akses /mitra/packages → redirect /login', async ({
            page,
            asMitra,
        }) => {
            await asMitra();
            await page.goto('/mitra/dashboard');

            await logoutAndWait(page);

            await page.goto('/mitra/packages');
            await expect(page).toHaveURL('/login');
        });
    });

    // ==========================================
    // MUTATION — COOKIE REMEMBER DIHAPUS
    // ==========================================

    test.describe('Mutation — Cookie Remember Dihapus', () => {
        test('setelah logout dengan remember me, cookie remember dihapus', async ({
            page,
            asGuest,
        }) => {
            // Login manual dengan remember me aktif
            await asGuest();
            await page.goto('/login');

            await SEL.AUTH.login.email(page).fill(SEED_USERS.client.email);
            await SEL.AUTH.login.password(page).fill(SEED_USERS.client.password);
            await SEL.AUTH.login.remember(page).check();

            await Promise.all([
                page.waitForURL('/client/dashboard', { timeout: 20_000 }),
                SEL.AUTH.login.submit(page).click(),
            ]);

            // Verifikasi cookie remember ada
            let cookies = await page.context().cookies();
            const rememberCookieBefore = cookies.find((c) =>
                c.name.startsWith('remember_'),
            );
            expect(rememberCookieBefore).toBeDefined();

            // Logout
            await logoutAndWait(page);

            // Verifikasi cookie remember hilang
            cookies = await page.context().cookies();
            const rememberCookieAfter = cookies.find((c) =>
                c.name.startsWith('remember_'),
            );

            // Cookie hilang atau value-nya kosong
            expect(
                rememberCookieAfter === undefined ||
                    rememberCookieAfter.value === '',
            ).toBe(true);
        });
    });

    // ==========================================
    // MUTATION — SESSION INVALID
    // ==========================================

    test.describe('Mutation — Session Invalid', () => {
        test('setelah logout, akses /notifications → redirect /login', async ({
            page,
            asClient,
        }) => {
            await asClient();
            await page.goto('/client/dashboard');

            await logoutAndWait(page);

            await page.goto('/notifications');

            await expect(page).toHaveURL('/login');
        });

        test('setelah logout, akses /change-password → redirect /login', async ({
            page,
            asClient,
        }) => {
            await asClient();
            await page.goto('/client/dashboard');

            await logoutAndWait(page);

            await page.goto('/change-password');

            await expect(page).toHaveURL('/login');
        });
    });

    // ==========================================
    // MUTATION — LOGOUT DARI HALAMAN CHANGE PASSWORD
    // ==========================================

    test.describe('Mutation — Logout dari Change Password', () => {
        test('tombol logout di halaman change-password bekerja', async ({
            page,
            asClient,
        }) => {
            await asClient();
            await page.goto('/change-password');

            await logoutAndWait(page);

            await expect(page).toHaveURL('/');
        });
    });
});