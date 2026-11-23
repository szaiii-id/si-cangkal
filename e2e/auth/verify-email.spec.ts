// e2e/auth/verify-email.spec.ts
/**
 * Test: Auth — Verifikasi Email (PRD-AUTH-002)
 *
 * Menguji:
 * - User unverified diblokir dari dashboard & transaksi
 * - Halaman /verify-email menampilkan email + tombol kirim ulang + logout
 * - Kirim ulang memicu cooldown 60 detik
 * - Signed URL valid → verifikasi → dashboard
 * - Signature dirusak → 403, status tetap unverified
 * - Mitra unverified tidak bisa mengakses onboarding
 *
 * Semua setup via `/_test/*` (fixture seed), bukan login lewat UI.
 */

import { test, expect } from '../fixtures/base';

const PASSWORD = 'password';

test.describe('Auth — Verifikasi Email', () => {
    async function makeUnverified(
        page: import('@playwright/test').Page,
        seed: import('../fixtures/seed').SeedHelpers,
        role: 'client' | 'mitra',
    ) {
        const suffix = Date.now() + Math.floor(Math.random() * 1000);
        const email = `unverified-${role}-${suffix}@test.com`;

        await seed.createUser(page, {
            email,
            name: `Unverified ${role}`,
            role,
            password: PASSWORD,
            username: `unverified${role}${suffix}`.toLowerCase(),
            email_verified_at: false,
        });
        await seed.login(page, email, PASSWORD);

        return email;
    }

    test('user unverified diarahkan ke /verify-email saat akses dashboard', async ({
        page,
        asGuest,
        seed,
    }) => {
        await asGuest();
        const email = await makeUnverified(page, seed, 'client');

        await page.goto('/client/dashboard');

        await expect(page).toHaveURL('/verify-email');
        await expect(page.getByText(email)).toBeVisible();
        await expect(
            page.getByRole('button', {
                name: /Kirim Ulang Email Verifikasi/i,
            }),
        ).toBeVisible();
        await expect(
            page.getByRole('button', { name: /Keluar \(Logout\)/i }),
        ).toBeVisible();
    });

    test('kirim ulang menampilkan alert sukses dan cooldown', async ({
        page,
        asGuest,
        seed,
    }) => {
        await asGuest();
        await makeUnverified(page, seed, 'client');
        await page.goto('/verify-email');

        await page
            .getByRole('button', { name: /Kirim Ulang Email Verifikasi/i })
            .click();

        await expect(
            page.getByText(/Tautan verifikasi baru telah dikirim/i),
        ).toBeVisible({ timeout: 10_000 });

        const resendButton = page.getByRole('button', {
            name: /Tunggu \d+ detik/i,
        });
        await expect(resendButton).toBeDisabled();
    });

    test('signed URL valid → terverifikasi → dashboard', async ({
        page,
        asGuest,
        seed,
    }) => {
        await asGuest();
        const email = await makeUnverified(page, seed, 'client');

        await page.goto('/client/dashboard');
        await expect(page).toHaveURL('/verify-email');

        const { url } = await seed.verificationUrl(page, email);
        await page.goto(url);

        await expect(page).toHaveURL('/client/dashboard');
        await expect(page.getByText(/Email berhasil diverifikasi/i)).toBeVisible({
            timeout: 10_000,
        });
    });

    test('signature dimodifikasi → 403', async ({ page, asGuest, seed }) => {
        await asGuest();
        const email = await makeUnverified(page, seed, 'client');

        const { url } = await seed.verificationUrl(page, email);
        const tampered = url.replace(/signature=[^&]+/, 'signature=invalid');

        const response = await page.goto(tampered);

        expect(response?.status()).toBe(403);
        await page.goto('/client/dashboard');
        await expect(page).toHaveURL('/verify-email');
    });

    test('mitra unverified tidak bisa membuka onboarding', async ({
        page,
        asGuest,
        seed,
    }) => {
        await asGuest();
        await makeUnverified(page, seed, 'mitra');

        await page.goto('/mitra/onboarding');

        await expect(page).toHaveURL('/verify-email');
    });

    test('user unverified masih bisa membuka profil', async ({
        page,
        asGuest,
        seed,
    }) => {
        await asGuest();
        await makeUnverified(page, seed, 'client');

        await page.goto('/client/profile');

        await expect(page).toHaveURL('/client/profile');
    });

    test('halaman verifikasi menampilkan tombol logout yang bekerja', async ({
        page,
        asGuest,
        seed,
    }) => {
        await asGuest();
        await makeUnverified(page, seed, 'client');
        await page.goto('/verify-email');

        await page
            .getByRole('button', { name: /Keluar \(Logout\)/i })
            .click();

        await expect(page).toHaveURL('/');
        await page.goto('/verify-email');
        await expect(page).toHaveURL('/login');
    });
});
