// e2e/auth/login.spec.ts
/**
 * Test: Auth — Login
 *
 * Menguji:
 * - Access control (guest, logged-in)
 * - Contract (form login lengkap)
 * - Mutation (login valid/invalid, redirect, force change password)
 *
 * Seed user (dari TestSeeder):
 * - admin@test.com / password
 * - mitra@test.com / password
 * - client@test.com / password
 */

import { test, expect } from '../fixtures/base';
import { SEL } from '../fixtures/selectors';
import { SEED_USERS } from '../fixtures/auth';

// ==========================================
// TEST SUITE
// ==========================================

test.describe('Auth — Login', () => {
    // ==========================================
    // ACCESS CONTROL
    // ==========================================

    test.describe('Access Control', () => {
        test('guest bisa akses form login', async ({ page, asGuest }) => {
            await asGuest();
            await page.goto('/login');

            await expect(page).toHaveURL('/login');
            await expect(
                page.getByRole('heading', { name: /^Login/i }),
            ).toBeVisible();
        });

        test('user sudah login akses /login → redirect dashboard', async ({
            page,
            asClient,
        }) => {
            await asClient();
            await page.goto('/login');

            // Laravel redirect ke dashboard sesuai role
            await expect(page).toHaveURL('/client/dashboard');
        });
    });

    // ==========================================
    // CONTRACT — ELEMEN HALAMAN
    // ==========================================

    test.describe('Contract', () => {
        test.beforeEach(async ({ page, asGuest }) => {
            await asGuest();
            await page.goto('/login');
        });

        test('menampilkan form login (email, password, submit)', async ({
            page,
        }) => {
            await expect(SEL.AUTH.login.email(page)).toBeVisible();
            await expect(SEL.AUTH.login.password(page)).toBeVisible();
            await expect(SEL.AUTH.login.submit(page)).toBeVisible();
        });

        test('menampilkan link "Lupa password" & "Daftar"', async ({
            page,
        }) => {
            await expect(SEL.AUTH.login.forgotPassword(page)).toBeVisible();
            await expect(SEL.AUTH.login.registerLink(page)).toBeVisible();
        });
    });

    // ==========================================
    // MUTATION — LOGIN VALID
    // ==========================================

    test.describe('Mutation — Login Valid', () => {
        test('login valid sebagai client → redirect /client/dashboard', async ({
            page,
            asGuest,
        }) => {
            await asGuest();
            await page.goto('/login');

            await SEL.AUTH.login.email(page).fill(SEED_USERS.client.email);
            await SEL.AUTH.login.password(page).fill(SEED_USERS.client.password);

            await Promise.all([
                page.waitForURL('/client/dashboard'),
                SEL.AUTH.login.submit(page).click(),
            ]);

            await expect(page).toHaveURL('/client/dashboard');
        });

        test('login valid sebagai admin → redirect /admin/dashboard', async ({
            page,
            asGuest,
        }) => {
            await asGuest();
            await page.goto('/login');

            await SEL.AUTH.login.email(page).fill(SEED_USERS.admin.email);
            await SEL.AUTH.login.password(page).fill(SEED_USERS.admin.password);

            await Promise.all([
                page.waitForURL('/admin/dashboard'),
                SEL.AUTH.login.submit(page).click(),
            ]);

            await expect(page).toHaveURL('/admin/dashboard');
        });

        test('login valid sebagai mitra → redirect /mitra/dashboard', async ({
            page,
            asGuest,
        }) => {
            await asGuest();
            await page.goto('/login');

            await SEL.AUTH.login.email(page).fill(SEED_USERS.mitra.email);
            await SEL.AUTH.login.password(page).fill(SEED_USERS.mitra.password);

            await Promise.all([
                page.waitForURL('/mitra/dashboard'),
                SEL.AUTH.login.submit(page).click(),
            ]);

            await expect(page).toHaveURL('/mitra/dashboard');
        });

        test('login dengan username (bukan email) → berhasil', async ({
            page,
            asGuest,
        }) => {
            await asGuest();
            await page.goto('/login');

            await SEL.AUTH.login.email(page).fill(SEED_USERS.client.username);
            await SEL.AUTH.login.password(page).fill(SEED_USERS.client.password);

            await Promise.all([
                page.waitForURL('/client/dashboard'),
                SEL.AUTH.login.submit(page).click(),
            ]);

            await expect(page).toHaveURL('/client/dashboard');
        });
    });

    // ==========================================
    // MUTATION — LOGIN INVALID
    // ==========================================

    test.describe('Mutation — Login Invalid', () => {
        test('login invalid → error "Kredensial tidak cocok"', async ({
            page,
            asGuest,
        }) => {
            await asGuest();
            await page.goto('/login');

            await SEL.AUTH.login.email(page).fill(SEED_USERS.client.email);
            await SEL.AUTH.login.password(page).fill('password-salah');

            await SEL.AUTH.login.submit(page).click();

            // Tetap di halaman login
            await expect(page).toHaveURL('/login');

            // Error muncul di form (bukan toast — flash error)
            await expect(
                page.getByText(/Kredensial yang diberikan tidak cocok/i),
            ).toBeVisible();
        });

        test('login dengan email kosong → HTML5 validation (tidak submit)', async ({
            page,
            asGuest,
        }) => {
            await asGuest();
            await page.goto('/login');

            await SEL.AUTH.login.password(page).fill('password');
            await SEL.AUTH.login.submit(page).click();

            // Tetap di login (HTML5 required prevent submit)
            await expect(page).toHaveURL('/login');
        });
    });

    // ==========================================
    // MUTATION — FORCE CHANGE PASSWORD
    // ==========================================

    test.describe('Mutation — Force Change Password', () => {
        test('login user must_change_password=true → redirect /change-password', async ({
            page,
            seed,
            asGuest,
        }) => {
            // Setup: set must_change_password = true
            await seed.resetUser(page, SEED_USERS.client.email, {
                password: SEED_USERS.client.password,
                must_change_password: true,
            });

            await asGuest();
            await page.goto('/login');

            await SEL.AUTH.login.email(page).fill(SEED_USERS.client.email);
            await SEL.AUTH.login.password(page).fill(SEED_USERS.client.password);

            await Promise.all([
                page.waitForURL('/change-password'),
                SEL.AUTH.login.submit(page).click(),
            ]);

            await expect(page).toHaveURL('/change-password');
            await expect(SEL.AUTH.changePassword.forceWarning(page)).toBeVisible();
        });

        test.afterEach(async ({ page, seed }) => {
            // Cleanup: reset must_change_password = false
            await seed.resetUser(page, SEED_USERS.client.email, {
                password: SEED_USERS.client.password,
                must_change_password: false,
            });
        });
    });
});