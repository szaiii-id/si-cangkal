// e2e/specs/auth/login.spec.ts
import { test, expect, SEED_USERS, type SeedRole } from '../../fixtures/auth';
import { LoginPage } from '../../fixtures/page-objects/LoginPage';

// ─────────────────────────────────────────────
// DATA-DRIVEN SCENARIOS
// ─────────────────────────────────────────────
const LOGIN_SCENARIOS: Array<{ role: SeedRole; expectedUrl: string }> = [
    { role: 'admin',  expectedUrl: '/admin/dashboard'  },
    { role: 'mitra',  expectedUrl: '/mitra/dashboard'  },
    { role: 'client', expectedUrl: '/client/dashboard' },
];

test.describe('Auth — Login', () => {
    // ─────────────────────────────────────────
    // 1. ACCESS CONTROL
    // ─────────────────────────────────────────
    test.describe('Access Control', () => {
        test('guest bisa akses /login', async ({ page, asGuest }) => {
            await asGuest();
            await page.goto('/login');
            await expect(page).toHaveURL('/login');
        });

        test('user sudah login tidak bisa akses /login', async ({ page, asAdmin }) => {
            await asAdmin();
            await page.goto('/login');
            await expect(page).not.toHaveURL('/login');
        });
    });

    // ─────────────────────────────────────────
    // 2. CONTRACT — FORM ELEMENTS
    // ─────────────────────────────────────────
    test.describe('Form Contract', () => {
        test.beforeEach(async ({ page }) => {
            const loginPage = new LoginPage(page);
            await loginPage.goto();
        });

        test('menampilkan form login lengkap', async ({ page }) => {
            await expect(page.getByLabel('Username atau Email')).toBeVisible();
            await expect(page.getByLabel('Password')).toBeVisible();
            await expect(page.getByRole('checkbox', { name: /ingat saya/i })).toBeVisible();
            await expect(page.getByRole('button', { name: /masuk/i })).toBeVisible();
        });

        test('menampilkan link "Daftar"', async ({ page }) => {
            await expect(page.getByRole('link', { name: /daftar/i })).toBeVisible();
        });

        test('menampilkan link "Lupa password"', async ({ page }) => {
            await expect(page.getByRole('link', { name: /lupa password/i })).toBeVisible();
        });
    });

    // ─────────────────────────────────────────
    // 3. HAPPY PATH — DATA-DRIVEN (6 test)
    // ─────────────────────────────────────────
    test.describe('Happy Path', () => {
        for (const { role, expectedUrl } of LOGIN_SCENARIOS) {
            test(`${role} login via email → ${expectedUrl}`, async ({ page }) => {
                const loginPage = new LoginPage(page);
                await loginPage.goto();
                await loginPage.login(SEED_USERS[role].email, SEED_USERS[role].password);
                await expect(page).toHaveURL(expectedUrl);
            });

            test(`${role} login via username → ${expectedUrl}`, async ({ page }) => {
                const loginPage = new LoginPage(page);
                await loginPage.goto();
                await loginPage.login(SEED_USERS[role].username, SEED_USERS[role].password);
                await expect(page).toHaveURL(expectedUrl);
            });
        }
    });

    // ─────────────────────────────────────────
    // 4. NEGATIVE PATH
    // ─────────────────────────────────────────
    test.describe('Negative Path', () => {
        test('password salah → error', async ({ page }) => {
            const loginPage = new LoginPage(page);
            await loginPage.goto();
            await loginPage.login(SEED_USERS.admin.email, 'wrong-password');
            await loginPage.expectError(/kredensial/i);
            await expect(page).toHaveURL('/login');
        });

        test('email tidak terdaftar → error', async ({ page }) => {
            const loginPage = new LoginPage(page);
            await loginPage.goto();
            await loginPage.login('tidak-ada@test.com', 'password123');
            await loginPage.expectError(/kredensial/i);
        });

        test('identitas kosong → tidak kirim POST', async ({ page }) => {
            const loginPage = new LoginPage(page);
            await loginPage.goto();
            await page.getByLabel('Password').fill('password123');
            await page.getByRole('button', { name: /masuk/i }).click();
            await expect(page).toHaveURL('/login');
        });
    });

    // ─────────────────────────────────────────
    // 5. REMEMBER ME
    // ─────────────────────────────────────────
    test.describe('Remember Me', () => {
        test('login dengan remember → cookie remember_*', async ({ page }) => {
            const loginPage = new LoginPage(page);
            await loginPage.goto();
            await loginPage.login(SEED_USERS.admin.email, SEED_USERS.admin.password, true);
            await expect(page).toHaveURL('/admin/dashboard');

            const cookies = await page.context().cookies();
            const rememberCookie = cookies.find((c) => c.name.startsWith('remember_'));
            expect(rememberCookie).toBeDefined();
        });

        test('login tanpa remember → no cookie', async ({ page }) => {
            const loginPage = new LoginPage(page);
            await loginPage.goto();
            await loginPage.login(SEED_USERS.admin.email, SEED_USERS.admin.password, false);
            await expect(page).toHaveURL('/admin/dashboard');

            const cookies = await page.context().cookies();
            const rememberCookie = cookies.find((c) => c.name.startsWith('remember_'));
            expect(rememberCookie).toBeUndefined();
        });
    });

    // ─────────────────────────────────────────
    // 6. UI INTERACTION — TOGGLE PASSWORD
    // ─────────────────────────────────────────
    test.describe('Toggle Password', () => {
        test('toggle show/hide password', async ({ page }) => {
            const loginPage = new LoginPage(page);
            await loginPage.goto();

            const passwordInput = page.getByLabel('Password');
            await expect(passwordInput).toHaveAttribute('type', 'password');

            await page.getByRole('button', { name: /tampilkan password/i }).click();
            await expect(passwordInput).toHaveAttribute('type', 'text');

            await page.getByRole('button', { name: /sembunyikan password/i }).click();
            await expect(passwordInput).toHaveAttribute('type', 'password');
        });
    });

    // ─────────────────────────────────────────
    // 7. A11Y
    // ─────────────────────────────────────────
    test.describe('Accessibility', () => {
        test('semua label terasosiasi dengan input', async ({ page }) => {
            const loginPage = new LoginPage(page);
            await loginPage.goto();

            await expect(page.locator('label[for]')).toHaveCount(3); // identitas, password, remember
        });

        test('toggle password punya aria-label', async ({ page }) => {
            const loginPage = new LoginPage(page);
            await loginPage.goto();

            await expect(page.getByRole('button', { name: /tampilkan password/i })).toBeVisible();
        });
    });
});