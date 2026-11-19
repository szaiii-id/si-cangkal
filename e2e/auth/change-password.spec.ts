// e2e/auth/change-password.spec.ts
/**
 * Test: Auth — Change Password
 *
 * Menguji:
 * - Access control (guest redirect, user login bisa akses)
 * - Contract (elemen form, toggle password)
 * - Validasi Browser (required, mismatch)
 * - Validasi Server (old password salah, password baru < 8)
 * - Mutation: Normal Change (wajib old_password) -> tetap login
 * - Mutation: Force Change (must_change_password=true, tanpa old_password) 
 *   -> logout paksa -> login ulang dengan password baru
 *
 * Backend:
 * - Normal: ChangePasswordController@update -> PasswordService::changePassword
 * - Force: ChangePasswordController@update -> PasswordService::forceChangePassword
 *
 * Route: GET /change-password, PUT /change-password (password.change)
 */

import { test, expect } from '../fixtures/base';
import { SEL } from '../fixtures/selectors';
import { SEED_USERS } from '../fixtures/auth';

// ==========================================
// KONSTANTA
// ==========================================

const SEED_PASSWORD = SEED_USERS.client.password; // "password"
const NEW_PASSWORD = 'PasswordBaru123!';

// ==========================================
// TEST SUITE
// ==========================================

test.describe('Auth — Change Password', () => {
    // ==========================================
    // ACCESS CONTROL
    // ==========================================

    test.describe('Access Control', () => {
        test('guest akses /change-password → redirect /login', async ({
            page,
            asGuest,
        }) => {
            await asGuest();
            await page.goto('/change-password');
            await expect(page).toHaveURL('/login');
        });

        test('client bisa akses /change-password', async ({
            page,
            asClient,
        }) => {
            await asClient();
            await page.goto('/change-password');
            await expect(page).toHaveURL('/change-password');
        });

        test('mitra bisa akses /change-password', async ({
            page,
            asMitra,
        }) => {
            await asMitra();
            await page.goto('/change-password');
            await expect(page).toHaveURL('/change-password');
        });
    });

    // ==========================================
    // CONTRACT — ELEMEN HALAMAN
    // ==========================================

    test.describe('Contract', () => {
        test.beforeEach(async ({ page, asClient }) => {
            await asClient();
            await page.goto('/change-password');
        });

        test('menampilkan heading "Ganti Password"', async ({ page }) => {
            await expect(
                page.getByRole('heading', { name: /Ganti Password/i }),
            ).toBeVisible();
        });

        test('menampilkan field old_password (mode normal)', async ({
            page,
        }) => {
            await expect(
                page.locator('input#old_password'),
            ).toBeVisible();
        });

        test('menampilkan field password & password_confirmation', async ({
            page,
        }) => {
            await expect(page.locator('input#password')).toBeVisible();
            await expect(
                page.locator('input#password_confirmation'),
            ).toBeVisible();
        });

        test('menampilkan tombol submit "Simpan Password Baru"', async ({
            page,
        }) => {
            await expect(
                page.getByRole('button', { name: /Simpan Password Baru/i }),
            ).toBeVisible();
        });

        test('menampilkan link/tombol untuk logout', async ({ page }) => {
            await expect(
                page.getByRole('button', { name: /Keluar/i }),
            ).toBeVisible();
        });
    });

    // ==========================================
    // CONTRACT — TOGGLE PASSWORD
    // ==========================================

    test.describe('Contract — Toggle Password', () => {
        test.beforeEach(async ({ page, asClient }) => {
            await asClient();
            await page.goto('/change-password');
        });

        test('toggle old password mengubah type', async ({ page }) => {
            const input = page.locator('input#old_password');
            await expect(input).toHaveAttribute('type', 'password');

            await page
                .getByRole('button', { name: /Tampilkan password lama/i })
                .click();
            await expect(input).toHaveAttribute('type', 'text');
        });

        test('toggle password baru mengubah type', async ({ page }) => {
            const input = page.locator('input#password');
            await expect(input).toHaveAttribute('type', 'password');

            await page
                .getByRole('button', { name: /Tampilkan password baru/i })
                .click();
            await expect(input).toHaveAttribute('type', 'text');
        });
    });

    // ==========================================
    // VALIDASI BROWSER (Client-Side)
    // ==========================================

    test.describe('Validasi Browser', () => {
        test.beforeEach(async ({ page, asClient }) => {
            await asClient();
            await page.goto('/change-password');
        });

        test('password mismatch → tombol submit disabled', async ({ page }) => {
            await page.fill('input#old_password', SEED_PASSWORD);
            await page.fill('input#password', 'Password123!');
            await page.fill('input#password_confirmation', 'Password456!');

            // Pesan mismatch muncul
            await expect(
                page.getByText(/Konfirmasi password tidak cocok/i),
            ).toBeVisible();

            // Tombol disabled
            await expect(
                page.getByRole('button', { name: /Simpan Password Baru/i }),
            ).toBeDisabled();
        });

        test('submit tanpa old_password → HTML5 validation', async ({
            page,
        }) => {
            await page.fill('input#password', NEW_PASSWORD);
            await page.fill('input#password_confirmation', NEW_PASSWORD);

            await page.getByRole('button', { name: /Simpan Password Baru/i }).click();

            // Tetap di halaman
            await expect(page).toHaveURL('/change-password');
        });
    });

    // ==========================================
    // VALIDASI SERVER (Backend)
    // ==========================================

    test.describe('Validasi Server', () => {
        test.beforeEach(async ({ page, asClient }) => {
            await asClient();
            await page.goto('/change-password');
        });

        test('old_password salah → error', async ({ page }) => {
            await page.fill('input#old_password', 'password-salah-banget');
            await page.fill('input#password', NEW_PASSWORD);
            await page.fill('input#password_confirmation', NEW_PASSWORD);

            await Promise.all([
                page.waitForResponse(
                    (resp) =>
                        resp.url().includes('/change-password') &&
                        resp.request().method() === 'PUT', 
                    { timeout: 15_000 },
                ),
                page.getByRole('button', { name: /Simpan Password Baru/i }).click(),
            ]);

            await expect(page).toHaveURL('/change-password');
            await expect(
                page.getByText(/Password lama tidak sesuai/i),
            ).toBeVisible({ timeout: 10_000 });
        });

        test('password baru kurang dari 8 karakter → error', async ({
            page,
        }) => {
            await page.fill('input#old_password', SEED_PASSWORD);
            await page.fill('input#password', '123');
            await page.fill('input#password_confirmation', '123');

            await Promise.all([
                page.waitForResponse(
                    (resp) =>
                        resp.url().includes('/change-password') &&
                        resp.request().method() === 'PUT', 
                    { timeout: 15_000 },
                ),
                page.getByRole('button', { name: /Simpan Password Baru/i }).click(),
            ]);

            await expect(page).toHaveURL('/change-password');
            await expect(
                page.getByText(/minimal 8 karakter/i).first(),
            ).toBeVisible({ timeout: 10_000 });
        });
    });

    // ==========================================
    // MUTATION — NORMAL CHANGE PASSWORD (serial)
    // ==========================================

    test.describe.serial('Mutation — Normal Change Password', () => {
        test('ganti password berhasil (mode normal)', async ({
        page,
            asClient,
        }) => {
            await asClient();
            await page.goto('/change-password');

            await page.fill('input#old_password', SEED_PASSWORD);
            await page.fill('input#password', NEW_PASSWORD);
            await page.fill('input#password_confirmation', NEW_PASSWORD);

            await Promise.all([
                page.waitForURL('/client/dashboard', { timeout: 15_000 }),
                page.getByRole('button', { name: /Simpan Password Baru/i }).click(),
            ]);

            await expect(page).toHaveURL('/client/dashboard');
        });

        test('password baru bisa dipakai login ulang', async ({
            page,
            asGuest,
        }) => {
            await asGuest();
            await page.goto('/login');

            await SEL.AUTH.login.email(page).fill(SEED_USERS.client.email);
            await SEL.AUTH.login.password(page).fill(NEW_PASSWORD);

            await Promise.all([
                page.waitForURL('/client/dashboard', { timeout: 15_000 }),
                SEL.AUTH.login.submit(page).click(),
            ]);

            await expect(page).toHaveURL('/client/dashboard');
        });

        test('password lama tidak bisa dipakai lagi', async ({
            page,
            asGuest,
        }) => {
            await asGuest();
            await page.goto('/login');

            await SEL.AUTH.login.email(page).fill(SEED_USERS.client.email);
            await SEL.AUTH.login.password(page).fill(SEED_PASSWORD);

            await SEL.AUTH.login.submit(page).click();

            await expect(page).toHaveURL('/login');
            await expect(
                page.getByText(/Kredensial yang diberikan tidak cocok/i),
            ).toBeVisible();
        });

        test('cleanup: reset password client kembali ke seed', async ({
            page,
            seed,
        }) => {
            await seed.resetUser(page, SEED_USERS.client.email, {
                password: SEED_PASSWORD,
                must_change_password: false,
            });
        });
    });

    // ==========================================
    // MUTATION — FORCE CHANGE PASSWORD (serial)
    // ==========================================

    test.describe.serial('Mutation — Force Change Password', () => {
        test('setup: set must_change_password = true', async ({
            page,
            seed,
        }) => {
            await seed.resetUser(page, SEED_USERS.client.email, {
                password: SEED_PASSWORD,
                must_change_password: true,
            });
        });

        test('login dengan must_change_password → diarahkan ke /change-password', async ({
            page,
            asGuest,
        }) => {
            await asGuest();
            await page.goto('/login');

            await SEL.AUTH.login.email(page).fill(SEED_USERS.client.email);
            await SEL.AUTH.login.password(page).fill(SEED_PASSWORD);

            await Promise.all([
                page.waitForURL('/change-password', { timeout: 15_000 }),
                SEL.AUTH.login.submit(page).click(),
            ]);

            await expect(page).toHaveURL('/change-password');
        });

        test('mode force: field old_password disembunyikan (tidak wajib)', async ({
            page,
        }) => {
            // Login manual setelah setup must_change_password=true.
            await page.goto('/login');

            await SEL.AUTH.login.email(page).fill(SEED_USERS.client.email);
            await SEL.AUTH.login.password(page).fill(SEED_PASSWORD);

            await Promise.all([
                page.waitForURL('/change-password', { timeout: 15_000 }),
                SEL.AUTH.login.submit(page).click(),
            ]);

            // Peringatan wajib ganti password muncul
            await expect(
                page.getByRole('note').filter({ hasText: /Wajib Ganti Password/i }),
            ).toBeVisible();
        });

        test('ganti password mode force berhasil → logout paksa', async ({
            page,
        }) => {
            // Login manual sebagai user force change
            await page.goto('/login');
            await SEL.AUTH.login.email(page).fill(SEED_USERS.client.email);
            await SEL.AUTH.login.password(page).fill(SEED_PASSWORD);

            await Promise.all([
                page.waitForURL('/change-password', { timeout: 15_000 }),
                SEL.AUTH.login.submit(page).click(),
            ]);

            const forceNewPassword = 'ForceBaru123!';

            // Tidak isi old_password (karena force)
            await page.fill('input#password', forceNewPassword);
            await page.fill('input#password_confirmation', forceNewPassword);

            await Promise.all([
                // Mode force → redirect ke /login
                page.waitForURL('/login', { timeout: 15_000 }),
                page.getByRole('button', { name: /Simpan Password Baru/i }).click(),
            ]);

            await expect(page).toHaveURL('/login');
            await expect(
                page.getByText(/Password berhasil diubah/i),
            ).toBeVisible({ timeout: 10_000 });
        });

        test('password baru mode force bisa dipakai login', async ({
            page,
            asGuest,
        }) => {
            await asGuest();
            await page.goto('/login');

            await SEL.AUTH.login.email(page).fill(SEED_USERS.client.email);
            await SEL.AUTH.login.password(page).fill('ForceBaru123!');

            await Promise.all([
                page.waitForURL('/client/dashboard', { timeout: 15_000 }),
                SEL.AUTH.login.submit(page).click(),
            ]);

            await expect(page).toHaveURL('/client/dashboard');
        });

        test('cleanup: reset password & must_change_password', async ({
            page,
            seed,
        }) => {
            await seed.resetUser(page, SEED_USERS.client.email, {
                password: SEED_PASSWORD,
                must_change_password: false,
            });
        });
    });
});