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

function uniqueEmail(prefix: string): string {
    const rand = Math.random().toString(36).slice(2, 8);
    return `${prefix}-${Date.now()}-${rand}@titiktemu.test`;
}

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

        test('mitra bisa akses /change-password', async ({ page, asMitra }) => {
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
            await expect(page.locator('input#old_password')).toBeVisible();
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

            await expect(
                page.getByText(/Konfirmasi password tidak cocok/i),
            ).toBeVisible();

            await expect(
                page.getByRole('button', { name: /Simpan Password Baru/i }),
            ).toBeDisabled();
        });

        test('submit tanpa old_password → HTML5 validation', async ({
            page,
        }) => {
            await page.fill('input#password', NEW_PASSWORD);
            await page.fill('input#password_confirmation', NEW_PASSWORD);

            await page
                .getByRole('button', { name: /Simpan Password Baru/i })
                .click();

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
                page
                    .getByRole('button', { name: /Simpan Password Baru/i })
                    .click(),
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
                page
                    .getByRole('button', { name: /Simpan Password Baru/i })
                    .click(),
            ]);

            await expect(page).toHaveURL('/change-password');
            await expect(
                page.getByText(/minimal 8 karakter/i).first(),
            ).toBeVisible({ timeout: 10_000 });
        });
    });

    // ==========================================
    // MUTATION — NORMAL CHANGE PASSWORD (serial, isolated user)
    // ==========================================

    test.describe.serial('Mutation — Normal Change Password', () => {
        const normalEmail = uniqueEmail('normal-pwd');

        test('setup: buat user client terisolasi', async ({ page, seed }) => {
            await seed.createUser(page, {
                email: normalEmail,
                name: 'Normal Password User',
                role: 'client',
                password: SEED_PASSWORD,
                must_change_password: false,
            });
        });

        test('ganti password berhasil (mode normal)', async ({
            page,
            seed,
        }) => {
            await seed.login(page, normalEmail, SEED_PASSWORD);
            await page.goto('/change-password');

            await page.fill('input#old_password', SEED_PASSWORD);
            await page.fill('input#password', NEW_PASSWORD);
            await page.fill('input#password_confirmation', NEW_PASSWORD);

            await Promise.all([
                page.waitForURL('/client/dashboard', { timeout: 15_000 }),
                page
                    .getByRole('button', { name: /Simpan Password Baru/i })
                    .click(),
            ]);

            await expect(page).toHaveURL('/client/dashboard');
        });

        test('password baru bisa dipakai login ulang', async ({
            page,
            asGuest,
        }) => {
            await asGuest();
            await page.goto('/login');

            await SEL.AUTH.login.email(page).fill(normalEmail);
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

            await SEL.AUTH.login.email(page).fill(normalEmail);
            await SEL.AUTH.login.password(page).fill(SEED_PASSWORD);

            await SEL.AUTH.login.submit(page).click();

            await expect(page).toHaveURL('/login');
            await expect(
                page.getByText(/Kredensial yang diberikan tidak cocok/i),
            ).toBeVisible();
        });
    });

    // ==========================================
    // MUTATION — FORCE CHANGE PASSWORD (serial, isolated user)
    // ==========================================

    test.describe.serial('Mutation — Force Change Password', () => {
        const forceEmail = uniqueEmail('force-pwd');
        const forceNewPassword = 'ForceBaru123!';

        test('setup: buat user dengan must_change_password = true', async ({
            page,
            seed,
        }) => {
            await seed.createUser(page, {
                email: forceEmail,
                name: 'Force Password User',
                role: 'client',
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

            await SEL.AUTH.login.email(page).fill(forceEmail);
            await SEL.AUTH.login.password(page).fill(SEED_PASSWORD);

            await Promise.all([
                page.waitForURL('/change-password', { timeout: 15_000 }),
                SEL.AUTH.login.submit(page).click(),
            ]);

            await expect(page).toHaveURL('/change-password');
        });

        test('mode force: field old_password disembunyikan (tidak wajib)', async ({
            page,
            asGuest,
        }) => {
            await asGuest();
            await page.goto('/login');

            await SEL.AUTH.login.email(page).fill(forceEmail);
            await SEL.AUTH.login.password(page).fill(SEED_PASSWORD);

            await Promise.all([
                page.waitForURL('/change-password', { timeout: 15_000 }),
                SEL.AUTH.login.submit(page).click(),
            ]);

            await expect(
                page
                    .getByRole('note')
                    .filter({ hasText: /Wajib Ganti Password/i }),
            ).toBeVisible();
        });

        test('ganti password mode force berhasil → logout paksa', async ({
            page,
            asGuest,
        }) => {
            await asGuest();
            await page.goto('/login');

            await SEL.AUTH.login.email(page).fill(forceEmail);
            await SEL.AUTH.login.password(page).fill(SEED_PASSWORD);

            await Promise.all([
                page.waitForURL('/change-password', { timeout: 15_000 }),
                SEL.AUTH.login.submit(page).click(),
            ]);

            await page.fill('input#password', forceNewPassword);
            await page.fill('input#password_confirmation', forceNewPassword);

            await Promise.all([
                page.waitForURL('/login', { timeout: 15_000 }),
                page
                    .getByRole('button', { name: /Simpan Password Baru/i })
                    .click(),
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

            await SEL.AUTH.login.email(page).fill(forceEmail);
            await SEL.AUTH.login.password(page).fill(forceNewPassword);

            await Promise.all([
                page.waitForURL('/client/dashboard', { timeout: 15_000 }),
                SEL.AUTH.login.submit(page).click(),
            ]);

            await expect(page).toHaveURL('/client/dashboard');
        });
    });
});
