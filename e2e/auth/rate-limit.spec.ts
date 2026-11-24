// e2e/auth/rate-limit.spec.ts
/**
 * Test: Auth — Rate Limiting
 *
 * Menguji:
 * - Login rate limit (`auth.throttle:login`) → redirect 302 + pesan error throttle
 * - Register rate limit (`auth.throttle:register`) → redirect 302 + pesan error throttle
 * - Forgot password rate limit (`auth.throttle:forgot-password`) → redirect 302 + pesan error throttle
 * - Reset password rate limit (`auth.throttle:reset-password`) → redirect 302 + pesan error throttle
 * - Isolasi key rate limiter antar akun (akun A terblokir tidak memblokir akun B)
 *
 * Backend: App\Http\Middleware\AuthThrottleResponse
 * - Melempar `ValidationException` saat melebihi batas percobaan.
 * - Pada request browser Inertia (`Accept: text/html`), Laravel merespons `302`
 *   redirect back dengan flash error pada field terkait.
 *
 * CATATAN PENTING:
 * - Membutuhkan `CACHE_STORE=database` (atau `file`) di `.env.testing` agar counter
 *   rate limiter bertahan lintas HTTP request pada server `php -S`.
 * - Test otomatis di-skip jika env `AUTH_THROTTLE_*_TEST_OVERRIDE` bernilai 0 atau >= 100.
 *   Jalankan dengan: `bun run test:e2e:rate-limit`
 */

import { expect, type Page, type Response } from '@playwright/test';
import { test } from '../fixtures/base';
import { SEL } from '../fixtures/selectors';
import { SEED_USERS } from '../fixtures/auth';

// ==========================================
// KONFIGURASI & SKIP LOGIC
// ==========================================

const LOGIN_LIMIT = Number(process.env.AUTH_THROTTLE_LOGIN_TEST_OVERRIDE ?? 0);
const REGISTER_LIMIT = Number(
    process.env.AUTH_THROTTLE_REGISTER_TEST_OVERRIDE ?? 0,
);
const FORGOT_LIMIT = Number(
    process.env.AUTH_THROTTLE_FORGOT_PASSWORD_TEST_OVERRIDE ?? 0,
);
const RESET_LIMIT = Number(
    process.env.AUTH_THROTTLE_RESET_PASSWORD_TEST_OVERRIDE ?? 0,
);

const RATE_LIMIT_THRESHOLD = 100;
const SHOULD_SKIP =
    LOGIN_LIMIT === 0 ||
    LOGIN_LIMIT >= RATE_LIMIT_THRESHOLD ||
    REGISTER_LIMIT === 0 ||
    REGISTER_LIMIT >= RATE_LIMIT_THRESHOLD ||
    FORGOT_LIMIT === 0 ||
    FORGOT_LIMIT >= RATE_LIMIT_THRESHOLD ||
    RESET_LIMIT === 0 ||
    RESET_LIMIT >= RATE_LIMIT_THRESHOLD;

const FAKE_TOKEN = 'token-palsu-tidak-valid-e2e';
const VALID_PASSWORD = 'Password123!';

// ==========================================
// HELPER
// ==========================================

/**
 * Membersihkan counter rate limiter pada backend untuk identitas tertentu
 * (mendukung IPv6 `::1` maupun IPv4 `127.0.0.1`).
 */
async function resetRateLimiter(
    page: Page,
    limiter: 'login' | 'register' | 'forgot-password' | 'reset-password',
    identifier: string,
): Promise<void> {
    await page.goto('/');
    const csrfToken = await page.evaluate(() => {
        const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
        return match ? decodeURIComponent(match[1]) : '';
    });

    const normalized = identifier.toLowerCase();

    for (const ip of ['::1', '127.0.0.1']) {
        const response = await page.request.post('/_test/clear-throttle', {
            form: { limiter, key: `${normalized}|${ip}` },
            headers: { 'X-XSRF-TOKEN': csrfToken },
            failOnStatusCode: false,
        });

        if (!response.ok()) {
            throw new Error(
                `Gagal reset rate limiter: ${limiter} | ${normalized}|${ip}`,
            );
        }
    }
}

// ==========================================
// TEST SUITE
// ==========================================

test.describe.serial('Auth — Rate Limiting', () => {
    test.skip(
        SHOULD_SKIP,
        'Rate limit test memerlukan env override kecil. Jalankan dengan: bun run test:e2e:rate-limit',
    );

    // ==========================================
    // LOGIN RATE LIMIT
    // ==========================================

    test.describe('Login Rate Limit', () => {
        const TEST_EMAIL = SEED_USERS.client.email;

        test.beforeEach(async ({ page, asGuest }) => {
            await asGuest();
            await resetRateLimiter(page, 'login', TEST_EMAIL);
        });

        test('login diblokir setelah melebihi limit', async ({ page }) => {
            await page.goto('/login');

            let lastResponse: Response | null = null;

            for (let i = 0; i < LOGIN_LIMIT + 1; i++) {
                await SEL.AUTH.login.email(page).fill(TEST_EMAIL);
                await SEL.AUTH.login.password(page).fill('password-salah');

                const [response] = await Promise.all([
                    page.waitForResponse(
                        (resp) =>
                            resp.url().includes('/login') &&
                            resp.request().method() === 'POST',
                        { timeout: 15_000 },
                    ),
                    SEL.AUTH.login.submit(page).click(),
                ]);

                lastResponse = response;

                if (i < LOGIN_LIMIT) {
                    await expect(SEL.AUTH.login.submit(page)).toBeEnabled({
                        timeout: 10_000,
                    });
                }
            }

            expect(lastResponse?.status()).toBe(302);
            await expect(page).toHaveURL('/login');
            await expect(
                page.getByText(/Terlalu banyak percobaan login/i),
            ).toBeVisible({ timeout: 10_000 });
        });
    });

    // ==========================================
    // REGISTER RATE LIMIT
    // ==========================================

    test.describe('Register Rate Limit', () => {
        const TEST_EMAIL = SEED_USERS.admin.email;

        test.beforeEach(async ({ page, asGuest }) => {
            await asGuest();
            await resetRateLimiter(page, 'register', TEST_EMAIL);
        });

        test('register diblokir setelah melebihi limit', async ({ page }) => {
            await page.goto('/register');

            let lastResponse: Response | null = null;

            for (let i = 0; i < REGISTER_LIMIT + 1; i++) {
                await SEL.AUTH.register.name(page).fill('Rate Limit Test');
                await SEL.AUTH.register
                    .username(page)
                    .fill(`ratelimit${Date.now()}${i}`.toLowerCase());
                await SEL.AUTH.register.email(page).fill(TEST_EMAIL);
                await SEL.AUTH.register.password(page).fill(VALID_PASSWORD);
                await SEL.AUTH.register
                    .passwordConfirmation(page)
                    .fill(VALID_PASSWORD);
                await SEL.AUTH.register.termsCheckbox(page).check();

                const [response] = await Promise.all([
                    page.waitForResponse(
                        (resp) =>
                            resp.url().includes('/register') &&
                            resp.request().method() === 'POST',
                        { timeout: 15_000 },
                    ),
                    SEL.AUTH.register.submit(page).click(),
                ]);

                lastResponse = response;

                if (i < REGISTER_LIMIT) {
                    await expect(SEL.AUTH.register.submit(page)).toBeEnabled({
                        timeout: 10_000,
                    });
                }
            }

            expect(lastResponse?.status()).toBe(302);
            await expect(page).toHaveURL('/register');
            await expect(
                page.getByText(/Terlalu banyak percobaan pendaftaran/i),
            ).toBeVisible({ timeout: 10_000 });
        });
    });

    // ==========================================
    // FORGOT PASSWORD RATE LIMIT
    // ==========================================

    test.describe('Forgot Password Rate Limit', () => {
        const TEST_EMAIL = SEED_USERS.client.email;

        test.beforeEach(async ({ page, asGuest }) => {
            await asGuest();
            await resetRateLimiter(page, 'forgot-password', TEST_EMAIL);
        });

        test('forgot password diblokir setelah melebihi limit', async ({
            page,
        }) => {
            await page.goto('/forgot-password');

            let lastResponse: Response | null = null;

            for (let i = 0; i < FORGOT_LIMIT + 1; i++) {
                await SEL.AUTH.forgotPassword.email(page).fill(TEST_EMAIL);

                const [response] = await Promise.all([
                    page.waitForResponse(
                        (resp) =>
                            resp.url().includes('/forgot-password') &&
                            resp.request().method() === 'POST',
                        { timeout: 15_000 },
                    ),
                    SEL.AUTH.forgotPassword.submit(page).click(),
                ]);

                lastResponse = response;

                if (i < FORGOT_LIMIT) {
                    await expect(
                        SEL.AUTH.forgotPassword.submit(page),
                    ).toBeEnabled({ timeout: 10_000 });
                }
            }

            expect(lastResponse?.status()).toBe(302);
            await expect(page).toHaveURL('/forgot-password');
            await expect(
                page.getByText(/Terlalu banyak permintaan reset password/i),
            ).toBeVisible({ timeout: 10_000 });
        });
    });

    // ==========================================
    // RESET PASSWORD RATE LIMIT
    // ==========================================

    test.describe('Reset Password Rate Limit', () => {
        const TEST_EMAIL = SEED_USERS.client.email;
        const RESET_URL = `/reset-password/${FAKE_TOKEN}?email=${encodeURIComponent(TEST_EMAIL)}`;

        test.beforeEach(async ({ page, asGuest }) => {
            await asGuest();
            await resetRateLimiter(page, 'reset-password', TEST_EMAIL);
        });

        test('reset password diblokir setelah melebihi limit', async ({
            page,
        }) => {
            await page.goto(RESET_URL);

            let lastResponse: Response | null = null;

            for (let i = 0; i < RESET_LIMIT + 1; i++) {
                await SEL.AUTH.resetPassword
                    .password(page)
                    .fill(VALID_PASSWORD);
                await SEL.AUTH.resetPassword
                    .passwordConfirmation(page)
                    .fill(VALID_PASSWORD);

                const [response] = await Promise.all([
                    page.waitForResponse(
                        (resp) =>
                            resp.url().includes('/reset-password') &&
                            resp.request().method() === 'POST',
                        { timeout: 15_000 },
                    ),
                    SEL.AUTH.resetPassword.submit(page).click(),
                ]);

                lastResponse = response;

                if (i < RESET_LIMIT) {
                    await expect(
                        SEL.AUTH.resetPassword.submit(page),
                    ).toBeEnabled({ timeout: 10_000 });
                }
            }

            expect(lastResponse?.status()).toBe(302);
            await expect(page).toHaveURL(
                new RegExp(`/reset-password/${FAKE_TOKEN}`),
            );
            await expect(
                page.getByText(/Terlalu banyak percobaan reset password/i),
            ).toBeVisible({ timeout: 10_000 });
        });
    });

    // ==========================================
    // ISOLATION ANTAR AKUN
    // ==========================================

    test.describe('Isolation', () => {
        test('rate limit login akun A tidak mempengaruhi akun B', async ({
            page,
            asGuest,
        }) => {
            const emailA = SEED_USERS.client.email;
            const emailB = SEED_USERS.admin.email;

            await asGuest();
            await resetRateLimiter(page, 'login', emailA);
            await resetRateLimiter(page, 'login', emailB);

            await page.goto('/login');

            let lastResponseA: Response | null = null;

            for (let i = 0; i < LOGIN_LIMIT + 1; i++) {
                await SEL.AUTH.login.email(page).fill(emailA);
                await SEL.AUTH.login.password(page).fill('password-salah');

                const [response] = await Promise.all([
                    page.waitForResponse(
                        (resp) =>
                            resp.url().includes('/login') &&
                            resp.request().method() === 'POST',
                        { timeout: 15_000 },
                    ),
                    SEL.AUTH.login.submit(page).click(),
                ]);

                lastResponseA = response;

                if (i < LOGIN_LIMIT) {
                    await expect(SEL.AUTH.login.submit(page)).toBeEnabled({
                        timeout: 10_000,
                    });
                }
            }

            expect(lastResponseA?.status()).toBe(302);
            await expect(
                page.getByText(/Terlalu banyak percobaan login/i),
            ).toBeVisible({ timeout: 10_000 });

            // Akun B tetap dapat login normal di halaman yang sama
            await SEL.AUTH.login.email(page).fill(emailB);
            await SEL.AUTH.login.password(page).fill(SEED_USERS.admin.password);

            await Promise.all([
                page.waitForURL('/admin/dashboard', { timeout: 15_000 }),
                SEL.AUTH.login.submit(page).click(),
            ]);

            await expect(page).toHaveURL('/admin/dashboard');
        });
    });
});
