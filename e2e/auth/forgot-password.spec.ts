// e2e/auth/forgot-password.spec.ts
/**
 * Test: Auth — Forgot Password
 *
 * Menguji:
 * - Access control (guest only, logged-in redirect)
 * - Contract (form elemen, atribut)
 * - Validasi Browser (email required, format email)
 * - Validasi Server (email tidak terdaftar)
 * - Mutation (email valid → flash sukses)
 *
 * Backend: PasswordResetController@store → Password::sendResetLink
 * Route: GET /forgot-password, POST /forgot-password (password.request)
 * Middleware: `guest`, `auth.throttle:forgot-password`
 *
 * CATATAN PENTING:
 * - Laravel memiliki bawaan `RESET_THROTTLED` (60 detik per email) dari
 *   `PasswordBroker`. Jika test lain menembak email yang sama dalam < 60 detik,
 *   test akan gagal karena dibalas dengan "Mohon tunggu beberapa saat".
 * - Untuk menghindari flakiness (terutama di Firefox yang eksekusinya bisa
 *   lebih lambat), test mutation di bawah ini MEMBUAT USER BARU (email unik)
 *   via `/_test/create-user` sebelum memicu forgot-password.
 */

import { test, expect } from '../fixtures/base';
import { SEL } from '../fixtures/selectors';
import { seed } from '../fixtures/seed';

// ==========================================
// TEST SUITE
// ==========================================

test.describe('Auth — Forgot Password', () => {
    // ==========================================
    // ACCESS CONTROL
    // ==========================================

    test.describe('Access Control', () => {
        test('guest bisa akses form forgot password', async ({
            page,
            asGuest,
        }) => {
            await asGuest();
            await page.goto('/forgot-password');

            await expect(page).toHaveURL('/forgot-password');
            await expect(
                page.getByRole('heading', { name: /Pemulihan/i }),
            ).toBeVisible();
        });

        test('user sudah login akses /forgot-password → redirect dashboard', async ({
            page,
            asClient,
        }) => {
            await asClient();
            await page.goto('/forgot-password');

            // Middleware `guest` redirect ke dashboard
            await expect(page).toHaveURL('/client/dashboard');
        });
    });

    // ==========================================
    // CONTRACT — ELEMEN HALAMAN
    // ==========================================

    test.describe('Contract', () => {
        test.beforeEach(async ({ page, asGuest }) => {
            await asGuest();
            await page.goto('/forgot-password');
        });

        test('menampilkan heading "Pemulihan"', async ({ page }) => {
            await expect(
                page.getByRole('heading', { name: /Pemulihan/i }),
            ).toBeVisible();
        });

        test('menampilkan input email', async ({ page }) => {
            await expect(SEL.AUTH.forgotPassword.email(page)).toBeVisible();
        });

        test('menampilkan tombol "Kirim Tautan Pemulihan"', async ({
            page,
        }) => {
            await expect(SEL.AUTH.forgotPassword.submit(page)).toBeVisible();
        });

        test('menampilkan link "Kembali ke Login"', async ({ page }) => {
            await expect(
                SEL.AUTH.forgotPassword.backToLogin(page),
            ).toBeVisible();
        });

        test('link "Kembali ke Login" mengarah ke /login', async ({ page }) => {
            await expect(
                SEL.AUTH.forgotPassword.backToLogin(page),
            ).toHaveAttribute('href', /\/login$/);
        });
    });

    // ==========================================
    // CONTRACT — ATRIBUT INPUT
    // ==========================================

    test.describe('Contract — Atribut Input', () => {
        test.beforeEach(async ({ page, asGuest }) => {
            await asGuest();
            await page.goto('/forgot-password');
        });

        test('input email bertipe email', async ({ page }) => {
            await expect(SEL.AUTH.forgotPassword.email(page)).toHaveAttribute(
                'type',
                'email',
            );
        });

        test('input email required', async ({ page }) => {
            await expect(SEL.AUTH.forgotPassword.email(page)).toHaveAttribute(
                'required',
                '',
            );
        });

        test('input email punya autocomplete="email"', async ({ page }) => {
            await expect(SEL.AUTH.forgotPassword.email(page)).toHaveAttribute(
                'autocomplete',
                'email',
            );
        });
    });

    // ==========================================
    // VALIDASI BROWSER (Client-Side)
    // ==========================================

    test.describe('Validasi Browser', () => {
        test.beforeEach(async ({ page, asGuest }) => {
            await asGuest();
            await page.goto('/forgot-password');
        });

        test('submit email kosong → tetap di /forgot-password', async ({
            page,
        }) => {
            await SEL.AUTH.forgotPassword.submit(page).click();
            await expect(page).toHaveURL('/forgot-password');
        });

        test('submit email invalid format → tetap di /forgot-password', async ({
            page,
        }) => {
            await SEL.AUTH.forgotPassword.email(page).fill('bukan-email');
            await SEL.AUTH.forgotPassword.submit(page).click();
            await expect(page).toHaveURL('/forgot-password');
        });
    });

    // ==========================================
    // VALIDASI SERVER (Backend)
    // ==========================================

    test.describe('Validasi Server', () => {
        test.beforeEach(async ({ page, asGuest }) => {
            await asGuest();
            await page.goto('/forgot-password');
        });

        test('email tidak terdaftar → error', async ({ page }) => {
            // Pakai email acak yang dijamin tidak ada di DB
            const randomEmail = `tidak-ada-${Date.now()}@test.com`;

            await SEL.AUTH.forgotPassword.email(page).fill(randomEmail);

            await Promise.all([
                page.waitForResponse(
                    (resp) =>
                        resp.url().includes('/forgot-password') &&
                        resp.request().method() === 'POST',
                    { timeout: 15_000 },
                ),
                SEL.AUTH.forgotPassword.submit(page).click(),
            ]);

            // Tunggu render halaman
            await page.waitForLoadState('networkidle', { timeout: 15_000 });

            await expect(page).toHaveURL('/forgot-password');
            await expect(
                page.getByText(/tidak terdaftar di sistem kami/i),
            ).toBeVisible({ timeout: 10_000 });
        });
    });

    // ==========================================
    // MUTATION — SUCCESS (Isolated, Anti Throttle)
    // ==========================================

    test.describe.serial('Mutation — Forgot Password Sukses', () => {
        // Email unik khusus untuk test ini agar tidak bentrok throttle
        // dengan test lain (misal rate-limit.spec.ts).
        const uniqueEmail = `reset-flow-${Date.now()}@test.com`;

        test.beforeAll(async ({ browser }) => {
            // Setup: Buat user baru via API /_test/create-user
            // Gunakan request context terpisah agar tidak mengganggu page context
            const context = await browser.newContext();
            const page = await context.newPage();

            await seed.createUser(page, {
                email: uniqueEmail,
                name: 'User Reset Flow',
                role: 'client',
                password: 'password',
            });

            await context.close();
        });

        test('kirim email valid → flash sukses', async ({ page, asGuest }) => {
            await asGuest();
            await page.goto('/forgot-password');

            await SEL.AUTH.forgotPassword.email(page).fill(uniqueEmail);

            await Promise.all([
                page.waitForResponse(
                    (resp) =>
                        resp.url().includes('/forgot-password') &&
                        resp.request().method() === 'POST',
                    { timeout: 15_000 },
                ),
                SEL.AUTH.forgotPassword.submit(page).click(),
            ]);

            // Tunggu halaman baru selesai render (anti race Firefox)
            await page.waitForLoadState('networkidle', { timeout: 15_000 });

            // Poll untuk memastikan flash muncul (anti timing race)
            await expect
                .poll(
                    async () =>
                        await page
                            .getByText(
                                /Tautan pemulihan password telah dikirim/i,
                            )
                            .isVisible()
                            .catch(() => false),
                    { timeout: 15_000, intervals: [100, 200, 500] },
                )
                .toBe(true);
        });
    });
});
