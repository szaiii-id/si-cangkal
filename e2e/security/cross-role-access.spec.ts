// e2e/security/cross-role-access.spec.ts
import { test, expect } from '../fixtures/base';

test.describe('Security — Cross-Role & Guest Route Access Control', () => {
    const adminPaths = [
        '/admin/dashboard',
        '/admin/users',
        '/admin/clients',
        '/admin/bookings',
        '/admin/trips',
        '/admin/refunds',
        '/admin/reports',
    ];

    const mitraPaths = [
        '/mitra/dashboard',
        '/mitra/packages',
        '/mitra/bookings',
        '/mitra/trips',
        '/mitra/refunds',
        '/mitra/profile',
    ];

    const clientPaths = [
        '/client/dashboard',
        '/client/catalog',
        '/client/bookings',
        '/client/profile',
        '/client/notifications',
    ];

    // ==========================================
    // 1. GUEST ACCESS (Belum Login)
    // ==========================================
    test('Guest mengakses rute /admin/*, /mitra/*, dan /client/* → redirect ke /login', async ({
        page,
        asGuest,
    }) => {
        await asGuest();

        const protectedPaths = [
            '/admin/dashboard',
            '/admin/users',
            '/mitra/dashboard',
            '/mitra/packages',
            '/client/dashboard',
            '/client/bookings',
        ];

        for (const path of protectedPaths) {
            await page.goto(path);
            await expect(page).toHaveURL(/\/login$/);
        }
    });

    // ==========================================
    // 2. CLIENT ROLE ISOLATION
    // ==========================================
    test('Client mengakses rute /admin/* → redirect ke /client/dashboard', async ({
        page,
        asClient,
    }) => {
        await asClient();

        for (const path of adminPaths) {
            await page.goto(path);
            await expect(page).toHaveURL('/client/dashboard');
        }
    });

    test('Client mengakses rute /mitra/* → redirect ke /client/dashboard', async ({
        page,
        asClient,
    }) => {
        await asClient();

        for (const path of mitraPaths) {
            await page.goto(path);
            await expect(page).toHaveURL('/client/dashboard');
        }
    });

    // ==========================================
    // 3. MITRA ROLE ISOLATION
    // ==========================================
    test('Mitra mengakses rute /admin/* → redirect ke /mitra/dashboard dengan pesan error', async ({
        page,
        asMitra,
    }) => {
        await asMitra();

        for (const path of adminPaths) {
            await page.goto(path);
            await expect(page).toHaveURL('/mitra/dashboard');
            await expect(
                page
                    .getByText(
                        'Anda tidak memiliki hak akses ke halaman tersebut.',
                    )
                    .first(),
            ).toBeVisible();
        }
    });

    test('Mitra mengakses rute /client/* → redirect ke /mitra/dashboard dengan pesan error', async ({
        page,
        asMitra,
    }) => {
        await asMitra();

        for (const path of clientPaths) {
            await page.goto(path);
            await expect(page).toHaveURL('/mitra/dashboard');
            await expect(
                page
                    .getByText(
                        'Anda tidak memiliki hak akses ke halaman tersebut.',
                    )
                    .first(),
            ).toBeVisible();
        }
    });

    // ==========================================
    // 4. ADMIN ROLE ISOLATION
    // ==========================================
    test('Admin mengakses rute /client/* → redirect ke /admin/dashboard dengan pesan error', async ({
        page,
        asAdmin,
    }) => {
        await asAdmin();

        for (const path of clientPaths) {
            await page.goto(path);
            await expect(page).toHaveURL('/admin/dashboard');
            await expect(
                page
                    .getByText(
                        'Anda tidak memiliki hak akses ke halaman tersebut.',
                    )
                    .first(),
            ).toBeVisible();
        }
    });

    test('Admin mengakses rute /mitra/* → redirect ke /admin/dashboard dengan pesan error', async ({
        page,
        asAdmin,
    }) => {
        await asAdmin();

        for (const path of mitraPaths) {
            await page.goto(path);
            await expect(page).toHaveURL('/admin/dashboard');
            await expect(
                page
                    .getByText(
                        'Anda tidak memiliki hak akses ke halaman tersebut.',
                    )
                    .first(),
            ).toBeVisible();
        }
    });
});
