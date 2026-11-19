// e2e/auth/register-mitra.spec.ts
/**
 * Test: Auth — Register Mitra
 *
 * Menguji:
 * - Access control (guest only, logged-in redirect)
 * - Contract (form register mitra lengkap)
 * - Validasi browser (required, email format, password mismatch, terms)
 * - Validasi server (email/username duplikat)
 * - Mutation (register mitra valid → auto-login → /mitra/onboarding karena
 *   middleware EnsureMitraProfileCompleted redirect mitra tanpa rekening)
 *
 * Backend: AuthController@registerMitra → AuthService::registerMitra
 * - Role otomatis = mitra
 * - Profile kosong dibuat (tipe='mitra')
 * - must_change_password = false
 * - Auto-login setelah register
 * - Redirect ke /mitra/dashboard → ditangkap middleware
 *   EnsureMitraProfileCompleted → redirect /mitra/onboarding
 *
 * CATATAN PENTING:
 * - Mitra baru BELUM punya rekening, jadi PASTI redirect ke /mitra/onboarding.
 * - Seed user `mitra@test.com` SUDAH punya rekening (dari MitraBankAccountSeeder),
 *   jadi tidak kena redirect onboarding.
 * - Test mutation harus `describe.serial` karena menambah user baru ke DB.
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

test.describe('Auth — Register Mitra', () => {
    // ==========================================
    // ACCESS CONTROL
    // ==========================================

    test.describe('Access Control', () => {
        test('guest bisa akses form register mitra', async ({
            page,
            asGuest,
        }) => {
            await asGuest();
            await page.goto('/register/mitra');

            await expect(page).toHaveURL('/register/mitra');
            await expect(
                page.getByRole('heading', { name: /^Daftar Mitra/i }),
            ).toBeVisible();
        });

        test('user sudah login akses /register/mitra → redirect dashboard', async ({
            page,
            asClient,
        }) => {
            await asClient();
            await page.goto('/register/mitra');

            // Middleware `guest` redirect ke dashboard
            await expect(page).toHaveURL('/client/dashboard');
        });

        test('admin sudah login akses /register/mitra → redirect admin dashboard', async ({
            page,
            asAdmin,
        }) => {
            await asAdmin();
            await page.goto('/register/mitra');

            await expect(page).toHaveURL('/admin/dashboard');
        });
    });

    // ==========================================
    // CONTRACT — ELEMEN HALAMAN
    // ==========================================

    test.describe('Contract', () => {
        test.beforeEach(async ({ page, asGuest }) => {
            await asGuest();
            await page.goto('/register/mitra');
        });

        test('menampilkan heading "Daftar Mitra"', async ({ page }) => {
            await expect(
                page.getByRole('heading', { name: /^Daftar Mitra/i }),
            ).toBeVisible();
        });

        test('menampilkan subheading "Mulai jual paket wisata"', async ({
            page,
        }) => {
            await expect(
                page.getByText(/Mulai jual paket wisata/i),
            ).toBeVisible();
        });

        test('menampilkan semua field yang sama dengan register client', async ({
            page,
        }) => {
            await expect(SEL.AUTH.registerMitra.name(page)).toBeVisible();
            await expect(SEL.AUTH.registerMitra.username(page)).toBeVisible();
            await expect(SEL.AUTH.registerMitra.email(page)).toBeVisible();
            await expect(SEL.AUTH.registerMitra.password(page)).toBeVisible();
            await expect(
                SEL.AUTH.registerMitra.passwordConfirmation(page),
            ).toBeVisible();
        });

        test('menampilkan checkbox terms', async ({ page }) => {
            await expect(
                SEL.AUTH.registerMitra.termsCheckbox(page),
            ).toBeVisible();
        });

        test('menampilkan tombol "Buat Akun Mitra"', async ({ page }) => {
            await expect(
                SEL.AUTH.registerMitra.submit(page),
            ).toBeVisible();
        });

        test('menampilkan link "Masuk sekarang"', async ({ page }) => {
            await expect(
                SEL.AUTH.registerMitra.loginLink(page),
            ).toBeVisible();
        });

        test('menampilkan link "Daftar sebagai Client"', async ({ page }) => {
            await expect(
                SEL.AUTH.registerMitra.registerClientLink(page),
            ).toBeVisible();
        });

        test('link "Daftar sebagai Client" mengarah ke /register', async ({
            page,
        }) => {
            await expect(
                SEL.AUTH.registerMitra.registerClientLink(page),
            ).toHaveAttribute('href', /\/register$/);
        });

        test('label submit = "Buat Akun Mitra" (bukan "Buat Akun")', async ({
            page,
        }) => {
            const btn = SEL.AUTH.registerMitra.submit(page);
            await expect(btn).toBeVisible();
            await expect(btn).toContainText(/Buat Akun Mitra/i);
        });
    });

    // ==========================================
    // CONTRACT — ATRIBUT INPUT
    // ==========================================

    test.describe('Contract — Atribut Input', () => {
        test.beforeEach(async ({ page, asGuest }) => {
            await asGuest();
            await page.goto('/register/mitra');
        });

        test('field name bertipe text', async ({ page }) => {
            await expect(
                SEL.AUTH.registerMitra.name(page),
            ).toHaveAttribute('type', 'text');
        });

        test('field email bertipe email', async ({ page }) => {
            await expect(
                SEL.AUTH.registerMitra.email(page),
            ).toHaveAttribute('type', 'email');
        });

        test('semua field required', async ({ page }) => {
            await expect(
                SEL.AUTH.registerMitra.name(page),
            ).toHaveAttribute('required', '');
            await expect(
                SEL.AUTH.registerMitra.username(page),
            ).toHaveAttribute('required', '');
            await expect(
                SEL.AUTH.registerMitra.email(page),
            ).toHaveAttribute('required', '');
            await expect(
                SEL.AUTH.registerMitra.password(page),
            ).toHaveAttribute('required', '');
            await expect(
                SEL.AUTH.registerMitra.passwordConfirmation(page),
            ).toHaveAttribute('required', '');
        });
    });

    // ==========================================
    // CONTRACT — PASSWORD STRENGTH (FIXED)
    // ==========================================

    test.describe('Contract — Password Strength Indicator', () => {
        test.beforeEach(async ({ page, asGuest }) => {
            await asGuest();
            await page.goto('/register/mitra');
        });

        test('menampilkan checklist password saat password diisi', async ({
            page,
        }) => {
            // Belum ada checklist saat kosong
            await expect(
                page.getByText(/Minimal 8 karakter/i),
            ).not.toBeVisible();

            await SEL.AUTH.registerMitra.password(page).fill('abc');

            // Teks FE = "Minimal 8 karakter"
            await expect(
                page.getByText(/Minimal 8 karakter/i).first(),
            ).toBeVisible();
        });

        test('label "Lemah" muncul untuk password pendek', async ({
            page,
        }) => {
            await SEL.AUTH.registerMitra.password(page).fill('abc');

            await expect(
                page.getByText(/^Lemah$/i).first(),
            ).toBeVisible();
        });
    });

    // ==========================================
    // CONTRACT — TOGGLE PASSWORD (FIXED)
    // ==========================================

    test.describe('Contract — Toggle Password', () => {
        test.beforeEach(async ({ page, asGuest }) => {
            await asGuest();
            await page.goto('/register/mitra');
        });

        test('toggle password utama mengubah type', async ({ page }) => {
            const input = SEL.AUTH.registerMitra.password(page);
            await expect(input).toHaveAttribute('type', 'password');

            await page
                .getByRole('button', { name: /^Tampilkan password$/i })
                .click();
            await expect(input).toHaveAttribute('type', 'text');
        });

        test('toggle konfirmasi password mengubah type', async ({ page }) => {
            const input = SEL.AUTH.registerMitra.passwordConfirmation(page);
            await expect(input).toHaveAttribute('type', 'password');

            await page
                .getByRole('button', {
                    name: /Tampilkan konfirmasi password/i,
                })
                .click();
            await expect(input).toHaveAttribute('type', 'text');
        });
    });

    // ==========================================
    // VALIDASI BROWSER (submit diblokir / disabled)
    // ==========================================

    test.describe('Validasi Browser', () => {
        test.beforeEach(async ({ page, asGuest }) => {
            await asGuest();
            await page.goto('/register/mitra');
        });

        test('tombol submit disabled saat terms belum dicentang', async ({
            page,
        }) => {
            await SEL.AUTH.registerMitra.name(page).fill('Test Mitra');
            await SEL.AUTH.registerMitra.username(page).fill('testmitra');
            await SEL.AUTH.registerMitra.email(page).fill('testmitra@test.com');
            await SEL.AUTH.registerMitra.password(page).fill(VALID_PASSWORD);
            await SEL.AUTH.registerMitra
                .passwordConfirmation(page)
                .fill(VALID_PASSWORD);

            // Terms belum dicentang → tombol disabled
            await expect(
                SEL.AUTH.registerMitra.submit(page),
            ).toBeDisabled();

            // Centang terms → tombol enabled
            await SEL.AUTH.registerMitra.termsCheckbox(page).check();
            await expect(
                SEL.AUTH.registerMitra.submit(page),
            ).not.toBeDisabled();
        });

        test('password mismatch → tombol submit disabled', async ({ page }) => {
            await SEL.AUTH.registerMitra.name(page).fill('Test Mitra');
            await SEL.AUTH.registerMitra.username(page).fill('testmitra');
            await SEL.AUTH.registerMitra.email(page).fill('testmitra@test.com');
            await SEL.AUTH.registerMitra.password(page).fill('password123');
            await SEL.AUTH.registerMitra
                .passwordConfirmation(page)
                .fill('password456');
            await SEL.AUTH.registerMitra.termsCheckbox(page).check();

            await expect(
                page.getByText(/Konfirmasi password tidak cocok/i),
            ).toBeVisible();

            await expect(
                SEL.AUTH.registerMitra.submit(page),
            ).toBeDisabled();
        });

        test('submit tanpa name → tetap di /register/mitra', async ({
            page,
        }) => {
            await SEL.AUTH.registerMitra.username(page).fill('testmitra');
            await SEL.AUTH.registerMitra.email(page).fill('testmitra@test.com');
            await SEL.AUTH.registerMitra.password(page).fill(VALID_PASSWORD);
            await SEL.AUTH.registerMitra
                .passwordConfirmation(page)
                .fill(VALID_PASSWORD);
            await SEL.AUTH.registerMitra.termsCheckbox(page).check();

            await SEL.AUTH.registerMitra.submit(page).click();

            await expect(page).toHaveURL('/register/mitra');
        });

        test('email format invalid → submit diblokir browser', async ({
            page,
        }) => {
            await SEL.AUTH.registerMitra.name(page).fill('Test Mitra');
            await SEL.AUTH.registerMitra.username(page).fill('testmitra');
            await SEL.AUTH.registerMitra.email(page).fill('bukan-email');
            await SEL.AUTH.registerMitra.password(page).fill(VALID_PASSWORD);
            await SEL.AUTH.registerMitra
                .passwordConfirmation(page)
                .fill(VALID_PASSWORD);
            await SEL.AUTH.registerMitra.termsCheckbox(page).check();

            await SEL.AUTH.registerMitra.submit(page).click();

            await expect(page).toHaveURL('/register/mitra');
        });
    });

    // ==========================================
    // VALIDASI SERVER (submit terkirim, ditolak server)
    // ==========================================

    test.describe('Validasi Server', () => {
        test.beforeEach(async ({ page, asGuest }) => {
            await asGuest();
            await page.goto('/register/mitra');
        });

        test('email duplikat dengan seed admin → error', async ({ page }) => {
            await SEL.AUTH.registerMitra.name(page).fill('Test Mitra');
            await SEL.AUTH.registerMitra.username(page).fill(
                `dupmitra${Date.now()}`.toLowerCase(),
            );
            await SEL.AUTH.registerMitra.email(page).fill(SEED_USERS.admin.email);
            await SEL.AUTH.registerMitra.password(page).fill(VALID_PASSWORD);
            await SEL.AUTH.registerMitra
                .passwordConfirmation(page)
                .fill(VALID_PASSWORD);
            await SEL.AUTH.registerMitra.termsCheckbox(page).check();

            await Promise.all([
                page.waitForResponse(
                    (resp) =>
                        resp.url().includes('/register/mitra') &&
                        resp.request().method() === 'POST',
                    { timeout: 15_000 },
                ),
                SEL.AUTH.registerMitra.submit(page).click(),
            ]);

            await expect(page).toHaveURL('/register/mitra');
            await expect(
                page.getByText(/Email ini sudah terdaftar/i),
            ).toBeVisible({ timeout: 10_000 });
        });

        test('username duplikat dengan seed admin → error', async ({
            page,
        }) => {
            const email = `dup-u-mitra-${Date.now()}@test.com`;

            await SEL.AUTH.registerMitra.name(page).fill('Test Mitra');
            await SEL.AUTH.registerMitra
                .username(page)
                .fill(SEED_USERS.admin.username);
            await SEL.AUTH.registerMitra.email(page).fill(email);
            await SEL.AUTH.registerMitra.password(page).fill(VALID_PASSWORD);
            await SEL.AUTH.registerMitra
                .passwordConfirmation(page)
                .fill(VALID_PASSWORD);
            await SEL.AUTH.registerMitra.termsCheckbox(page).check();

            await Promise.all([
                page.waitForResponse(
                    (resp) =>
                        resp.url().includes('/register/mitra') &&
                        resp.request().method() === 'POST',
                    { timeout: 15_000 },
                ),
                SEL.AUTH.registerMitra.submit(page).click(),
            ]);

            await expect(page).toHaveURL('/register/mitra');
            await expect(
                page.getByText(/Username ini sudah digunakan/i),
            ).toBeVisible({ timeout: 10_000 });
        });
    });

    // ==========================================
    // MUTATION — HAPPY PATH (serial)
    // ==========================================

    test.describe.serial('Mutation — Register Mitra Valid', () => {
        test('register mitra valid → redirect /mitra/onboarding', async ({
            page,
            asGuest,
        }) => {
            const suffix = Date.now();
            const email = `newmitra-${suffix}@test.com`;
            const username = `newmitra${suffix}`.toLowerCase();

            await asGuest();
            await page.goto('/register/mitra');

            await SEL.AUTH.registerMitra.name(page).fill('New Mitra E2E');
            await SEL.AUTH.registerMitra.username(page).fill(username);
            await SEL.AUTH.registerMitra.email(page).fill(email);
            await SEL.AUTH.registerMitra.password(page).fill(VALID_PASSWORD);
            await SEL.AUTH.registerMitra
                .passwordConfirmation(page)
                .fill(VALID_PASSWORD);
            await SEL.AUTH.registerMitra.termsCheckbox(page).check();

            // Mitra baru → /mitra/dashboard → middleware EnsureMitraProfileCompleted
            // redirect ke /mitra/onboarding (belum punya rekening)
            await Promise.all([
                page.waitForURL('/mitra/onboarding', { timeout: 15_000 }),
                SEL.AUTH.registerMitra.submit(page).click(),
            ]);

            await expect(page).toHaveURL('/mitra/onboarding');
        });

        test('register mitra → user role=mitra (akses /mitra/* tidak redirect role)', async ({
            page,
            asGuest,
        }) => {
            const suffix = Date.now();
            const email = `newmitra2-${suffix}@test.com`;
            const username = `newmitra2${suffix}`.toLowerCase();

            await asGuest();
            await page.goto('/register/mitra');

            await SEL.AUTH.registerMitra.name(page).fill('New Mitra 2 E2E');
            await SEL.AUTH.registerMitra.username(page).fill(username);
            await SEL.AUTH.registerMitra.email(page).fill(email);
            await SEL.AUTH.registerMitra.password(page).fill(VALID_PASSWORD);
            await SEL.AUTH.registerMitra
                .passwordConfirmation(page)
                .fill(VALID_PASSWORD);
            await SEL.AUTH.registerMitra.termsCheckbox(page).check();

            await Promise.all([
                page.waitForURL('/mitra/onboarding', { timeout: 15_000 }),
                SEL.AUTH.registerMitra.submit(page).click(),
            ]);

            // Sudah login sebagai mitra → akses /client/dashboard → redirect ke
            // /mitra/onboarding (karena role mitra & belum punya rekening)
            await page.goto('/client/dashboard');
            await expect(page).toHaveURL(/\/mitra\/(dashboard|onboarding)/);
        });

        test('mitra baru tidak kena force change password', async ({
            page,
            asGuest,
        }) => {
            const suffix = Date.now();
            const email = `newmitra3-${suffix}@test.com`;
            const username = `newmitra3${suffix}`.toLowerCase();

            await asGuest();
            await page.goto('/register/mitra');

            await SEL.AUTH.registerMitra.name(page).fill('New Mitra 3 E2E');
            await SEL.AUTH.registerMitra.username(page).fill(username);
            await SEL.AUTH.registerMitra.email(page).fill(email);
            await SEL.AUTH.registerMitra.password(page).fill(VALID_PASSWORD);
            await SEL.AUTH.registerMitra
                .passwordConfirmation(page)
                .fill(VALID_PASSWORD);
            await SEL.AUTH.registerMitra.termsCheckbox(page).check();

            await Promise.all([
                page.waitForURL('/mitra/onboarding', { timeout: 15_000 }),
                SEL.AUTH.registerMitra.submit(page).click(),
            ]);

            // Tidak redirect ke /change-password
            await expect(page).not.toHaveURL('/change-password');
        });
    });
});