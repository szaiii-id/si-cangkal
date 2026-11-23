// e2e/auth/register.spec.ts
/**
 * Test: Auth — Register (Client)
 *
 * Menguji:
 * - Access control (guest only, logged-in redirect)
 * - Contract (form register lengkap)
 * - Validasi browser (required, email format, password mismatch, terms)
 * - Validasi server (email/username duplikat)
 * - Mutation (register valid → auto-login → /verify-email)
 * - Password strength indicator
 * - Toggle password & toggle konfirmasi
 *
 * Backend: AuthController@register → AuthService::registerClient
 * - Role otomatis = client
 * - must_change_password = false (password dipilih sendiri)
 * - Auto-login setelah register
 * - Redirect ke /verify-email (PRD-AUTH-002)
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
// KONSTANTA
// ==========================================

const VALID_PASSWORD = 'password123';

// ==========================================
// TEST SUITE
// ==========================================

test.describe('Auth — Register (Client)', () => {
    // ==========================================
    // ACCESS CONTROL
    // ==========================================

    test.describe('Access Control', () => {
        test('guest bisa akses form register', async ({ page, asGuest }) => {
            await asGuest();
            await page.goto('/register');

            await expect(page).toHaveURL('/register');
            await expect(
                page.getByRole('heading', { name: /^Daftar Akun/i }),
            ).toBeVisible();
        });

        test('user sudah login akses /register → redirect dashboard', async ({
            page,
            asClient,
        }) => {
            await asClient();
            await page.goto('/register');

            // Laravel `guest` middleware redirect ke dashboard
            await expect(page).toHaveURL('/client/dashboard');
        });

        test('admin sudah login akses /register → redirect admin dashboard', async ({
            page,
            asAdmin,
        }) => {
            await asAdmin();
            await page.goto('/register');

            await expect(page).toHaveURL('/admin/dashboard');
        });
    });

    // ==========================================
    // CONTRACT — ELEMEN HALAMAN
    // ==========================================

    test.describe('Contract', () => {
        test.beforeEach(async ({ page, asGuest }) => {
            await asGuest();
            await page.goto('/register');
        });

        test('menampilkan heading "Daftar Akun"', async ({ page }) => {
            await expect(
                page.getByRole('heading', { name: /^Daftar Akun/i }),
            ).toBeVisible();
        });

        test('menampilkan field: name, username, email, password, konfirmasi', async ({
            page,
        }) => {
            await expect(SEL.AUTH.register.name(page)).toBeVisible();
            await expect(SEL.AUTH.register.username(page)).toBeVisible();
            await expect(SEL.AUTH.register.email(page)).toBeVisible();
            await expect(SEL.AUTH.register.password(page)).toBeVisible();
            await expect(SEL.AUTH.register.passwordConfirmation(page)).toBeVisible();
        });

        test('menampilkan checkbox terms', async ({ page }) => {
            await expect(SEL.AUTH.register.termsCheckbox(page)).toBeVisible();
        });

        test('menampilkan tombol "Buat Akun"', async ({ page }) => {
            await expect(SEL.AUTH.register.submit(page)).toBeVisible();
        });

        test('menampilkan link "Masuk sekarang"', async ({ page }) => {
            await expect(SEL.AUTH.register.loginLink(page)).toBeVisible();
        });

        test('menampilkan link "Login with Google"', async ({ page }) => {
            await expect(
                page.getByRole('link', { name: /Google/i }),
            ).toBeVisible();
        });
    });

    // ==========================================
    // CONTRACT — ATRIBUT INPUT
    // ==========================================

    test.describe('Contract — Atribut Input', () => {
        test.beforeEach(async ({ page, asGuest }) => {
            await asGuest();
            await page.goto('/register');
        });

        test('field name bertipe text', async ({ page }) => {
            await expect(SEL.AUTH.register.name(page)).toHaveAttribute(
                'type',
                'text',
            );
        });

        test('field email bertipe email', async ({ page }) => {
            await expect(SEL.AUTH.register.email(page)).toHaveAttribute(
                'type',
                'email',
            );
        });

        test('field password bertipe password', async ({ page }) => {
            await expect(SEL.AUTH.register.password(page)).toHaveAttribute(
                'type',
                'password',
            );
        });

        test('field name required', async ({ page }) => {
            await expect(SEL.AUTH.register.name(page)).toHaveAttribute(
                'required',
                '',
            );
        });

        test('field username required', async ({ page }) => {
            await expect(SEL.AUTH.register.username(page)).toHaveAttribute(
                'required',
                '',
            );
        });

        test('field email required', async ({ page }) => {
            await expect(SEL.AUTH.register.email(page)).toHaveAttribute(
                'required',
                '',
            );
        });

        test('field password required', async ({ page }) => {
            await expect(SEL.AUTH.register.password(page)).toHaveAttribute(
                'required',
                '',
            );
        });

        test('field password_confirmation required', async ({ page }) => {
            await expect(
                SEL.AUTH.register.passwordConfirmation(page),
            ).toHaveAttribute('required', '');
        });

        test('semua input punya autocomplete yang sesuai', async ({ page }) => {
            await expect(SEL.AUTH.register.name(page)).toHaveAttribute(
                'autocomplete',
                'name',
            );
            await expect(SEL.AUTH.register.email(page)).toHaveAttribute(
                'autocomplete',
                'email',
            );
            await expect(SEL.AUTH.register.username(page)).toHaveAttribute(
                'autocomplete',
                'username',
            );
            await expect(SEL.AUTH.register.password(page)).toHaveAttribute(
                'autocomplete',
                'new-password',
            );
            await expect(
                SEL.AUTH.register.passwordConfirmation(page),
            ).toHaveAttribute('autocomplete', 'new-password');
        });
    });

    // ==========================================
    // CONTRACT — PASSWORD STRENGTH
    // ==========================================

    test.describe('Contract — Password Strength Indicator', () => {
        test.beforeEach(async ({ page, asGuest }) => {
            await asGuest();
            await page.goto('/register');
        });

        test('menampilkan checklist password saat password diisi', async ({ page }) => {
            // Belum ada checklist saat password kosong
            await expect(
                page.getByText(/Minimal 8 karakter/i),
            ).not.toBeVisible();

            await SEL.AUTH.register.password(page).fill('abc');

            // FIX: teks FE = "Minimal 8 karakter"
            await expect(
                page.getByText(/Minimal 8 karakter/i).first(),
            ).toBeVisible();
        });

        test('label "Lemah" muncul untuk password pendek', async ({ page }) => {
            await SEL.AUTH.register.password(page).fill('abc');

            await expect(
                page.getByText(/^Lemah$/i).first(),
            ).toBeVisible();
        });

        test('label "Kuat" muncul untuk password kompleks', async ({ page }) => {
            await SEL.AUTH.register.password(page).fill('Password123!');

            await expect(
                page.getByText(/Kuat|Sangat Kuat/i).first(),
            ).toBeVisible();
        });
    });


    // ==========================================
    // CONTRACT — TOGGLE PASSWORD
    // ==========================================

    test.describe('Contract — Toggle Password', () => {
        test.beforeEach(async ({ page, asGuest }) => {
            await asGuest();
            await page.goto('/register');
        });

        test('toggle password utama mengubah type', async ({ page }) => {
            const input = SEL.AUTH.register.password(page);
            await expect(input).toHaveAttribute('type', 'password');

            // FIX: aria-label spesifik untuk field password
            await page.getByRole('button', { name: /^Tampilkan password$/i }).click();
            await expect(input).toHaveAttribute('type', 'text');
        });

        test('toggle konfirmasi password mengubah type', async ({ page }) => {
            const input = SEL.AUTH.register.passwordConfirmation(page);
            await expect(input).toHaveAttribute('type', 'password');

            // FIX: aria-label spesifik untuk field konfirmasi
            await page.getByRole('button', {
                name: /Tampilkan konfirmasi password/i,
            }).click();
            await expect(input).toHaveAttribute('type', 'text');
        });
    });

    // ==========================================
    // VALIDASI BROWSER (submit diblokir HTML5 / JS)
    // ==========================================

   test.describe('Validasi Browser', () => {
        test.beforeEach(async ({ page, asGuest }) => {
            await asGuest();
            await page.goto('/register');
        });

        test('submit tanpa name → tetap di /register', async ({ page }) => {
            await SEL.AUTH.register.email(page).fill('test@example.com');
            await SEL.AUTH.register.username(page).fill('testuser');
            await SEL.AUTH.register.password(page).fill(VALID_PASSWORD);
            await SEL.AUTH.register.passwordConfirmation(page).fill(VALID_PASSWORD);
            await SEL.AUTH.register.termsCheckbox(page).check();  // FIX: centang terms dulu

            await SEL.AUTH.register.submit(page).click();

            await expect(page).toHaveURL('/register');
        });

        test('tombol submit disabled saat terms belum dicentang', async ({ page }) => {
            await SEL.AUTH.register.name(page).fill('Test User');
            await SEL.AUTH.register.username(page).fill('testuser');
            await SEL.AUTH.register.email(page).fill('test@example.com');
            await SEL.AUTH.register.password(page).fill(VALID_PASSWORD);
            await SEL.AUTH.register.passwordConfirmation(page).fill(VALID_PASSWORD);

            // FIX: tombol disabled by design saat terms kosong
            await expect(SEL.AUTH.register.submit(page)).toBeDisabled();

            // Setelah centang terms, tombol enabled
            await SEL.AUTH.register.termsCheckbox(page).check();
            await expect(SEL.AUTH.register.submit(page)).not.toBeDisabled();
        });

        test('password mismatch → tombol submit disabled', async ({ page }) => {
            await SEL.AUTH.register.name(page).fill('Test User');
            await SEL.AUTH.register.username(page).fill('testuser');
            await SEL.AUTH.register.email(page).fill('test@example.com');
            await SEL.AUTH.register.password(page).fill('password123');
            await SEL.AUTH.register.passwordConfirmation(page).fill('password456');
            await SEL.AUTH.register.termsCheckbox(page).check();

            await expect(
                page.getByText(/Konfirmasi password tidak cocok/i),
            ).toBeVisible();

            await expect(SEL.AUTH.register.submit(page)).toBeDisabled();
        });

        test('email format invalid → submit diblokir browser', async ({ page }) => {
            await SEL.AUTH.register.name(page).fill('Test User');
            await SEL.AUTH.register.username(page).fill('testuser');
            await SEL.AUTH.register.email(page).fill('bukan-email');
            await SEL.AUTH.register.password(page).fill(VALID_PASSWORD);
            await SEL.AUTH.register.passwordConfirmation(page).fill(VALID_PASSWORD);
            await SEL.AUTH.register.termsCheckbox(page).check();

            await SEL.AUTH.register.submit(page).click();

            await expect(page).toHaveURL('/register');
        });
    });

    // ==========================================
    // VALIDASI SERVER (submit terkirim, ditolak server)
    // ==========================================

    test.describe('Validasi Server', () => {
        test.beforeEach(async ({ page, asGuest }) => {
            await asGuest();
            await page.goto('/register');
        });

        test('email duplikat dengan seed admin → error', async ({ page }) => {
            await SEL.AUTH.register.name(page).fill('Test User');
            await SEL.AUTH.register.username(page).fill(
                `dup${Date.now()}`.toLowerCase(),
            );
            await SEL.AUTH.register.email(page).fill(SEED_USERS.admin.email);
            await SEL.AUTH.register.password(page).fill(VALID_PASSWORD);
            await SEL.AUTH.register
                .passwordConfirmation(page)
                .fill(VALID_PASSWORD);
            await SEL.AUTH.register.termsCheckbox(page).check();

            await Promise.all([
                page.waitForResponse(
                    (resp) =>
                        resp.url().includes('/register') &&
                        resp.request().method() === 'POST',
                    { timeout: 15_000 },
                ),
                SEL.AUTH.register.submit(page).click(),
            ]);

            await expect(page).toHaveURL('/register');
            await expect(
                page.getByText(/Email ini sudah terdaftar/i),
            ).toBeVisible({ timeout: 10_000 });
        });

        test('username duplikat dengan seed admin → error', async ({ page }) => {
            const email = `dup-u-${Date.now()}@test.com`;

            await SEL.AUTH.register.name(page).fill('Test User');
            await SEL.AUTH.register.username(page).fill(
                SEED_USERS.admin.username,
            );
            await SEL.AUTH.register.email(page).fill(email);
            await SEL.AUTH.register.password(page).fill(VALID_PASSWORD);
            await SEL.AUTH.register
                .passwordConfirmation(page)
                .fill(VALID_PASSWORD);
            await SEL.AUTH.register.termsCheckbox(page).check();

            await Promise.all([
                page.waitForResponse(
                    (resp) =>
                        resp.url().includes('/register') &&
                        resp.request().method() === 'POST',
                    { timeout: 15_000 },
                ),
                SEL.AUTH.register.submit(page).click(),
            ]);

            await expect(page).toHaveURL('/register');
            await expect(
                page.getByText(/Username ini sudah digunakan/i),
            ).toBeVisible({ timeout: 10_000 });
        });
    });

    // ==========================================
    // MUTATION — HAPPY PATH (serial)
    // ==========================================

    test.describe.serial('Mutation — Register Valid', () => {
        test('register valid → auto-login → redirect /verify-email', async ({
            page,
            asGuest,
        }) => {
            const suffix = Date.now();
            const email = `newclient-${suffix}@test.com`;
            const username = `newclient${suffix}`.toLowerCase();

            await asGuest();
            await page.goto('/register');

            await SEL.AUTH.register.name(page).fill('New Client E2E');
            await SEL.AUTH.register.username(page).fill(username);
            await SEL.AUTH.register.email(page).fill(email);
            await SEL.AUTH.register.password(page).fill(VALID_PASSWORD);
            await SEL.AUTH.register
                .passwordConfirmation(page)
                .fill(VALID_PASSWORD);
            await SEL.AUTH.register.termsCheckbox(page).check();

            await Promise.all([
                page.waitForURL('/verify-email', { timeout: 15_000 }),
                SEL.AUTH.register.submit(page).click(),
            ]);

            await expect(page).toHaveURL('/verify-email');
            await expect(page.getByText(email)).toBeVisible();
        });

        test('register client → verifikasi → dashboard client', async ({
            page,
            asGuest,
            seed,
        }) => {
            const suffix = Date.now();
            const email = `newclient2-${suffix}@test.com`;
            const username = `newclient2${suffix}`.toLowerCase();

            await asGuest();
            await page.goto('/register');

            await SEL.AUTH.register.name(page).fill('New Client 2 E2E');
            await SEL.AUTH.register.username(page).fill(username);
            await SEL.AUTH.register.email(page).fill(email);
            await SEL.AUTH.register.password(page).fill(VALID_PASSWORD);
            await SEL.AUTH.register
                .passwordConfirmation(page)
                .fill(VALID_PASSWORD);
            await SEL.AUTH.register.termsCheckbox(page).check();

            await Promise.all([
                page.waitForURL('/verify-email', { timeout: 15_000 }),
                SEL.AUTH.register.submit(page).click(),
            ]);

            // User baru tidak kena force change password.
            await expect(page).not.toHaveURL('/change-password');

            // Belum terverifikasi → dashboard diblokir.
            await page.goto('/client/dashboard');
            await expect(page).toHaveURL('/verify-email');

            // Verifikasi via signed URL, lalu dashboard boleh diakses.
            const { url } = await seed.verificationUrl(page, email);
            await page.goto(url);
            await expect(page).toHaveURL('/client/dashboard');
        });

        test('user baru bisa verifikasi, logout, dan login ulang', async ({
            page,
            asGuest,
            seed,
        }) => {
            const suffix = Date.now();
            const email = `newclient3-${suffix}@test.com`;
            const username = `newclient3${suffix}`.toLowerCase();

            await asGuest();
            await page.goto('/register');

            await SEL.AUTH.register.name(page).fill('New Client 3 E2E');
            await SEL.AUTH.register.username(page).fill(username);
            await SEL.AUTH.register.email(page).fill(email);
            await SEL.AUTH.register.password(page).fill(VALID_PASSWORD);
            await SEL.AUTH.register
                .passwordConfirmation(page)
                .fill(VALID_PASSWORD);
            await SEL.AUTH.register.termsCheckbox(page).check();

            await Promise.all([
                page.waitForURL('/verify-email', { timeout: 15_000 }),
                SEL.AUTH.register.submit(page).click(),
            ]);

            const { url } = await seed.verificationUrl(page, email);
            await page.goto(url);
            await expect(page).toHaveURL('/client/dashboard');

            // Logout
            await asGuest();

            // Login ulang dengan kredensial baru
            await page.goto('/login');
            await SEL.AUTH.login.email(page).fill(email);
            await SEL.AUTH.login.password(page).fill(VALID_PASSWORD);

            await Promise.all([
                page.waitForURL('/client/dashboard', { timeout: 15_000 }),
                SEL.AUTH.login.submit(page).click(),
            ]);

            await expect(page).toHaveURL('/client/dashboard');
        });
    });
});