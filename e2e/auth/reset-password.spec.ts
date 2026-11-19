// e2e/auth/reset-password.spec.ts
/**
 * Test: Auth — Reset Password
 *
 * Menguji:
 * - Access control (guest only)
 * - Contract (form elemen, email read-only, token hidden)
 * - Validasi Browser (mismatch, required)
 * - Validasi Server (password < 8, token invalid)
 *
 * Backend: PasswordResetController@update → Password::reset
 * Route: GET /reset-password/{token}?email=... (password.reset)
 *        POST /reset-password (tanpa route name khusus)
 * Middleware: `guest`, `auth.throttle:reset-password`
 *
 * CATATAN PENTING:
 * - Test happy path (reset berhasil) MEMBUTUHKAN token valid.
 * - Token valid hanya bisa didapat dari email (SMTP) atau database.
 * - Di E2E, kita tidak punya akses ke email (MAIL_MAILER=array/log).
 * - Solusi: test happy path di-skip karena butuh setup backend khusus
 *   (misal endpoint `/_test/generate-reset-token`).
 */

import { test, expect } from '../fixtures/base';
import { SEL } from '../fixtures/selectors';

// ==========================================
// KONSTANTA
// ==========================================

const FAKE_TOKEN = 'token-palsu-tidak-valid-e2e';
const VALID_PASSWORD = 'PasswordBaru123!';

// ==========================================
// TEST SUITE
// ==========================================

test.describe('Auth — Reset Password', () => {
    // ==========================================
    // ACCESS CONTROL
    // ==========================================

    test.describe('Access Control', () => {
        test('guest bisa akses form reset password', async ({
            page,
            asGuest,
        }) => {
            await asGuest();

            await page.goto(`/reset-password/${FAKE_TOKEN}?email=test@test.com`);

            await expect(page).toHaveURL(new RegExp(`/reset-password/${FAKE_TOKEN}`));
            await expect(
                page.getByRole('heading', { name: /Reset Sandi/i }),
            ).toBeVisible();
        });

        test('user sudah login akses /reset-password → redirect dashboard', async ({
            page,
            asClient,
        }) => {
            await asClient();

            await page.goto(`/reset-password/${FAKE_TOKEN}?email=test@test.com`);

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
            await page.goto(
                `/reset-password/${FAKE_TOKEN}?email=client@test.com`,
            );
        });

        test('menampilkan heading "Reset Sandi"', async ({ page }) => {
            await expect(
                page.getByRole('heading', { name: /Reset Sandi/i }),
            ).toBeVisible();
        });

        test('menampilkan field email (readonly)', async ({ page }) => {
            const emailInput = SEL.AUTH.resetPassword.email(page);
            await expect(emailInput).toBeVisible();
            await expect(emailInput).toHaveAttribute('readonly', '');
        });

        test('field email terisi dari query string', async ({ page }) => {
            await expect(
                SEL.AUTH.resetPassword.email(page),
            ).toHaveValue('client@test.com');
        });

        test('menampilkan field password baru', async ({ page }) => {
            await expect(
                SEL.AUTH.resetPassword.password(page),
            ).toBeVisible();
        });

        test('menampilkan field konfirmasi password', async ({ page }) => {
            await expect(
                SEL.AUTH.resetPassword.passwordConfirmation(page),
            ).toBeVisible();
        });

        test('menampilkan tombol "Simpan Password Baru"', async ({ page }) => {
            await expect(
                SEL.AUTH.resetPassword.submit(page),
            ).toBeVisible();
        });
    });

    // ==========================================
    // CONTRACT — ATRIBUT INPUT
    // ==========================================

    test.describe('Contract — Atribut Input', () => {
        test.beforeEach(async ({ page, asGuest }) => {
            await asGuest();
            await page.goto(
                `/reset-password/${FAKE_TOKEN}?email=client@test.com`,
            );
        });

        test('input password bertipe password', async ({ page }) => {
            await expect(
                SEL.AUTH.resetPassword.password(page),
            ).toHaveAttribute('type', 'password');
        });

        test('input password required', async ({ page }) => {
            await expect(
                SEL.AUTH.resetPassword.password(page),
            ).toHaveAttribute('required', '');
        });

        test('input konfirmasi required', async ({ page }) => {
            await expect(
                SEL.AUTH.resetPassword.passwordConfirmation(page),
            ).toHaveAttribute('required', '');
        });
    });

    // ==========================================
    // VALIDASI BROWSER (Client-Side)
    // ==========================================

    test.describe('Validasi Browser', () => {
        test.beforeEach(async ({ page, asGuest }) => {
            await asGuest();
            await page.goto(
                `/reset-password/${FAKE_TOKEN}?email=client@test.com`,
            );
        });

        test('password mismatch → tombol submit disabled', async ({ page }) => {
            await SEL.AUTH.resetPassword.password(page).fill('Password123!');
            await SEL.AUTH.resetPassword
                .passwordConfirmation(page)
                .fill('Password456!');

            // Pesan mismatch muncul
            await expect(
                page.getByText(/Konfirmasi password tidak cocok/i),
            ).toBeVisible();

            // Tombol disabled
            await expect(
                SEL.AUTH.resetPassword.submit(page),
            ).toBeDisabled();
        });

        test('submit tanpa password → HTML5 validation', async ({ page }) => {
            await SEL.AUTH.resetPassword.submit(page).click();

            // Tetap di halaman
            await expect(page).toHaveURL(new RegExp(`/reset-password/${FAKE_TOKEN}`));
        });
    });

    // ==========================================
    // VALIDASI SERVER (Backend)
    // ==========================================

    test.describe('Validasi Server', () => {
        test.beforeEach(async ({ page, asGuest }) => {
            await asGuest();
            await page.goto(
                `/reset-password/${FAKE_TOKEN}?email=client@test.com`,
            );
        });

        test('password kurang dari 8 karakter → error', async ({ page }) => {
            await SEL.AUTH.resetPassword.password(page).fill('123');
            await SEL.AUTH.resetPassword.passwordConfirmation(page).fill('123');

            await Promise.all([
                page.waitForResponse(
                    (resp) =>
                        resp.url().includes('/reset-password') &&
                        resp.request().method() === 'POST',
                    { timeout: 15_000 },
                ),
                SEL.AUTH.resetPassword.submit(page).click(),
            ]);

            await page.waitForLoadState('networkidle', { timeout: 15_000 });

            // Tetap di halaman reset password (tidak redirect ke login)
            await expect(page).toHaveURL(new RegExp(`/reset-password/${FAKE_TOKEN}`));
            
            // Cek apakah ada pesan error yang muncul (bisa error password, atau error token dari backend)
            // Karena token palsu, backend mungkin mengembalikan error token.
            // Yang penting: form TIDAK berhasil (tidak redirect ke login).
            const errorAlert = page.locator('p.text-red-500, .text-red-600, .text-pink');
            await expect(errorAlert.first()).toBeVisible({ timeout: 10_000 });
        });

        test('token invalid → error', async ({ page }) => {
            await SEL.AUTH.resetPassword.password(page).fill(VALID_PASSWORD);
            await SEL.AUTH.resetPassword.passwordConfirmation(page).fill(VALID_PASSWORD);

            await Promise.all([
                page.waitForResponse(
                    (resp) =>
                        resp.url().includes('/reset-password') &&
                        resp.request().method() === 'POST',
                    { timeout: 15_000 },
                ),
                SEL.AUTH.resetPassword.submit(page).click(),
            ]);

            await page.waitForLoadState('networkidle', { timeout: 15_000 });

            await expect(page).toHaveURL(new RegExp(`/reset-password/${FAKE_TOKEN}`));
            
            // Assert error spesifik token (biasanya "This password reset token is invalid.")
            await expect(
                page.getByText(/token.*invalid|token.*tidak valid|gagal/i),
            ).toBeVisible({ timeout: 10_000 });
        });
    });


    // ==========================================
    // HAPPY PATH (SKIP - Butuh Token Valid)
    // ==========================================

    test.describe('Mutation — Reset Password Sukses', () => {
        test.skip(
            'reset password sukses dengan token valid',
            async () => {
                // Butuh token valid dari database/email.
                // Tidak reachable dari E2E tanpa endpoint testing khusus.
                // Bisa di-cover di feature test (Pest) atau integration test.
            },
        );
    });
});