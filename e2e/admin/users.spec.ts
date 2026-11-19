// e2e/admin/users.spec.ts
import { test, expect, SEED_USERS } from '../fixtures/base';
import { SEL } from '../fixtures/selectors';

test.describe.serial('Admin — Manajemen User (CRUD, Reset Password & Proteksi Self-Delete)', () => {
    const baseRunId = Date.now();
    const initialPassword = 'AdminPass123!@#';
    let resetGeneratedPassword = '';

    const getUserData = (browserName: string) => {
        const tag = `${baseRunId}${browserName.slice(0, 3)}`;
        return {
            name: `Admin Test ${tag}`,
            username: `admintest${tag}`,
            email: `admin-test-${tag}@sicangkal.test`,
        };
    };

    test('1. Menampilkan daftar user, tab filter, pencarian, dan menyembunyikan tombol Hapus untuk diri sendiri', async ({
        page,
        asAdmin,
    }) => {
        await asAdmin();

        await page.goto('/admin/users', { waitUntil: 'domcontentloaded' });

        await expect(
            page.getByRole('heading', { name: 'Manajemen User', exact: true }).last(),
        ).toBeVisible();

        // Pastikan baris akun admin yang sedang login tidak memiliki tombol "Hapus"
        const selfRow = page
            .locator('table tbody tr')
            .filter({ hasText: SEED_USERS.admin.email })
            .first();
        await expect(selfRow).toBeVisible();
        await expect(selfRow.getByRole('button', { name: /^Hapus$/i })).toHaveCount(0);

        // Uji pencarian berdasarkan email mitra seed
        const searchInput = page.getByPlaceholder(/Cari nama atau email/i);
        await Promise.all([
            page.waitForResponse(
                (res) =>
                    res.url().includes('/admin/users') &&
                    res.url().includes('search=') &&
                    res.request().method() === 'GET',
            ),
            searchInput.fill(SEED_USERS.mitra.email),
        ]);

        const mitraRow = page
            .locator('table tbody tr')
            .filter({ hasText: SEED_USERS.mitra.email })
            .first();
        await expect(mitraRow).toBeVisible({ timeout: 10_000 });
        await expect(mitraRow.getByRole('link', { name: /^Detail$/i })).toBeVisible();
    });

    test('2. Membuat akun Admin baru menampilkan CredentialsModal dengan kredensial lengkap', async ({
        page,
        asAdmin,
        browserName,
    }) => {
        const userData = getUserData(browserName);
        await asAdmin();

        await page.goto('/admin/users/create', { waitUntil: 'domcontentloaded' });
        await expect(page.locator('#name')).toBeVisible();

        await page.locator('#name').fill(userData.name);
        await page.locator('#email').fill(userData.email);
        await page.locator('#username').fill(userData.username);
        await page.getByPlaceholder('Minimal 8 karakter').fill(initialPassword);

        await Promise.all([
            page.waitForResponse(
                (res) =>
                    res.url().includes('/admin/users') &&
                    res.request().method() === 'POST',
            ),
            page.getByRole('button', { name: /Buat Admin/i }).click(),
        ]);

        // CredentialsModal harus otomatis terbuka dengan judul "Kredensial User"
        const credDialog = page.getByRole('dialog', {
            name: /Kredensial User/i,
        });
        await expect(credDialog).toBeVisible({ timeout: 10_000 });
        await expect(credDialog.getByText(userData.email)).toBeVisible();
        await expect(credDialog.getByText(initialPassword)).toBeVisible();

        await credDialog.getByRole('button', { name: /^Tutup$/i }).click();
        await expect(credDialog).toBeHidden();
    });

    test('3. Edit data akun admin baru & detail profil mitra (reveal rekening)', async ({
        page,
        asAdmin,
        browserName,
    }) => {
        const userData = getUserData(browserName);
        await asAdmin();

        // --- A. Buka Detail Mitra & test tombol reveal rekening ---
        await page.goto('/admin/users', { waitUntil: 'domcontentloaded' });
        await expect(
            page.getByRole('heading', { name: 'Manajemen User', exact: true }).last(),
        ).toBeVisible();

        const searchInput = page.getByPlaceholder(/Cari nama atau email/i);
        await Promise.all([
            page.waitForResponse(
                (res) =>
                    res.url().includes('/admin/users') &&
                    res.url().includes('search=') &&
                    res.request().method() === 'GET',
            ),
            searchInput.fill(SEED_USERS.mitra.email),
        ]);

        const mitraRow = page
            .locator('table tbody tr')
            .filter({ hasText: SEED_USERS.mitra.email })
            .first();
        await expect(mitraRow).toBeVisible({ timeout: 10_000 });

        await Promise.all([
            page.waitForURL(/\/admin\/users\/\d+$/, {
                timeout: 20_000,
                waitUntil: 'commit',
            }),
            mitraRow.getByRole('link', { name: /^Detail$/i }).click(),
        ]);

        await expect(page.getByText('Rekening Bank')).toBeVisible({ timeout: 10_000 });
        const revealBtn = page.locator('button[title="Tampilkan"]').first();
        if (await revealBtn.isVisible()) {
            await revealBtn.click();
            await expect(
                page.locator('button[title="Sembunyikan"]').first(),
            ).toBeVisible();
        }

        // --- B. Edit nama akun admin yang baru dibuat ---
        await page.goto('/admin/users', { waitUntil: 'domcontentloaded' });
        await expect(
            page.getByRole('heading', { name: 'Manajemen User', exact: true }).last(),
        ).toBeVisible();

        await Promise.all([
            page.waitForResponse(
                (res) =>
                    res.url().includes('/admin/users') &&
                    res.url().includes('search=') &&
                    res.request().method() === 'GET',
            ),
            page.getByPlaceholder(/Cari nama atau email/i).fill(userData.email),
        ]);

        const createdRow = page
            .locator('table tbody tr')
            .filter({ hasText: userData.email })
            .first();
        await expect(createdRow).toBeVisible({ timeout: 10_000 });

        await Promise.all([
            page.waitForURL(/\/admin\/users\/\d+\/edit$/, {
                timeout: 20_000,
                waitUntil: 'commit',
            }),
            createdRow.getByRole('link', { name: /^Edit$/i }).click(),
        ]);

        const nameInput = page.locator('#name');
        await expect(nameInput).toBeVisible({ timeout: 10_000 });

        const updatedName = `${userData.name} Updated`;
        await nameInput.fill(updatedName);

        await Promise.all([
            page.waitForResponse(
                (res) =>
                    /\/admin\/users\/\d+/.test(res.url()) &&
                    (res.request().method() === 'PUT' ||
                        res.request().method() === 'PATCH' ||
                        res.request().method() === 'POST'),
            ),
            page.getByRole('button', { name: /Simpan Perubahan/i }).click(),
        ]);

        await page.goto('/admin/users', { waitUntil: 'domcontentloaded' });
        await Promise.all([
            page.waitForResponse(
                (res) =>
                    res.url().includes('/admin/users') &&
                    res.url().includes('search=') &&
                    res.request().method() === 'GET',
            ),
            page.getByPlaceholder(/Cari nama atau email/i).fill(userData.email),
        ]);

        await expect(
            page.locator('table tbody tr').filter({ hasText: updatedName }).first(),
        ).toBeVisible({ timeout: 10_000 });
    });

    test('4. Reset password user menghasilkan password baru & memicu force change password saat login', async ({
        page,
        asAdmin,
        browserName,
    }) => {
        const userData = getUserData(browserName);
        await asAdmin();

        await page.goto('/admin/users', { waitUntil: 'domcontentloaded' });
        await expect(
            page.getByRole('heading', { name: 'Manajemen User', exact: true }).last(),
        ).toBeVisible();

        // Tunggu hasil pencarian debounce selesai agar tidak menimpa flash.credentials saat reset
        await Promise.all([
            page.waitForResponse(
                (res) =>
                    res.url().includes('/admin/users') &&
                    res.url().includes('search=') &&
                    res.request().method() === 'GET',
            ),
            page.getByPlaceholder(/Cari nama atau email/i).fill(userData.email),
        ]);

        const targetRow = page
            .locator('table tbody tr')
            .filter({ hasText: userData.email })
            .first();
        await expect(targetRow).toBeVisible({ timeout: 10_000 });

        await targetRow.getByRole('button', { name: /^Reset$/i }).click();

        const resetDialog = page.getByRole('dialog', {
            name: /Reset Password\?/i,
        });
        await expect(resetDialog).toBeVisible({ timeout: 5_000 });

        await Promise.all([
            page.waitForResponse(
                (res) =>
                    /\/admin\/users\/\d+\/reset-password/.test(res.url()) &&
                    res.request().method() === 'POST',
            ),
            resetDialog
                .getByRole('button', { name: /Ya, Reset Password/i })
                .click(),
        ]);

        // CredentialsModal muncul berisi password baru hasil reset
        const credDialog = page.getByRole('dialog', {
            name: /Kredensial User/i,
        });
        await expect(credDialog).toBeVisible({ timeout: 10_000 });

        const passwordText = await credDialog
            .locator('p.font-mono')
            .innerText();
        resetGeneratedPassword = passwordText.trim();
        expect(resetGeneratedPassword.length).toBeGreaterThanOrEqual(8);

        await credDialog.getByRole('button', { name: /^Tutup$/i }).click();

        // Verifikasi login menggunakan password hasil reset pada context bersih -> redirect /change-password
        const verifyContext = await page.context().browser()!.newContext();
        const verifyPage = await verifyContext.newPage();
        await verifyPage.addLocatorHandler(
            verifyPage.getByRole('button', { name: /Nanti Saja/i }),
            async (btn) => {
                await btn.click({ force: true }).catch(() => {});
            },
            { noWaitAfter: true },
        );

        await verifyPage.goto('/login', { waitUntil: 'domcontentloaded' });
        await SEL.AUTH.login.email(verifyPage).fill(userData.email);
        await SEL.AUTH.login.password(verifyPage).fill(resetGeneratedPassword);
        await Promise.all([
            verifyPage.waitForURL('/change-password', {
                timeout: 20_000,
                waitUntil: 'commit',
            }),
            SEL.AUTH.login.submit(verifyPage).click(),
        ]);
        await expect(verifyPage).toHaveURL('/change-password');

        // Selesaikan force change password -> logout paksa ke /login -> login ulang masuk /admin/dashboard
        const finalPassword = 'AdminFinalPass123!';
        await verifyPage.fill('input#password', finalPassword);
        await verifyPage.fill('input#password_confirmation', finalPassword);
        await Promise.all([
            verifyPage.waitForURL('/login', {
                timeout: 20_000,
                waitUntil: 'commit',
            }),
            verifyPage.getByRole('button', { name: /Simpan Password Baru/i }).click(),
        ]);

        await SEL.AUTH.login.email(verifyPage).fill(userData.email);
        await SEL.AUTH.login.password(verifyPage).fill(finalPassword);
        await Promise.all([
            verifyPage.waitForURL('/admin/dashboard', {
                timeout: 20_000,
                waitUntil: 'commit',
            }),
            SEL.AUTH.login.submit(verifyPage).click(),
        ]);
        await verifyContext.close();
    });

    test('5. Hapus user dummy yang dibuat selama test', async ({
        page,
        asAdmin,
        browserName,
    }) => {
        const userData = getUserData(browserName);
        await asAdmin();

        await page.goto('/admin/users', { waitUntil: 'domcontentloaded' });
        await expect(
            page.getByRole('heading', { name: 'Manajemen User', exact: true }).last(),
        ).toBeVisible();

        // Tunggu hasil pencarian debounce selesai agar tabel tidak re-render di tengah klik Hapus
        await Promise.all([
            page.waitForResponse(
                (res) =>
                    res.url().includes('/admin/users') &&
                    res.url().includes('search=') &&
                    res.request().method() === 'GET',
            ),
            page.getByPlaceholder(/Cari nama atau email/i).fill(userData.email),
        ]);

        const targetRow = page
            .locator('table tbody tr')
            .filter({ hasText: userData.email })
            .first();
        await expect(targetRow).toBeVisible({ timeout: 10_000 });

        await targetRow.getByRole('button', { name: /^Hapus$/i }).click();

        const deleteDialog = page.getByRole('dialog', {
            name: /Hapus User\?/i,
        });
        await expect(deleteDialog).toBeVisible({ timeout: 5_000 });

        const confirmBtn = deleteDialog.getByRole('button', { name: /Ya, Hapus/i });
        await expect(confirmBtn).toBeEnabled();

        await Promise.all([
            page.waitForResponse(
                (res) =>
                    /\/admin\/users\/\d+/.test(res.url()) &&
                    (res.request().method() === 'DELETE' ||
                        res.request().method() === 'POST'),
                { timeout: 20_000 },
            ),
            confirmBtn.click(),
        ]);

        await expect(deleteDialog).toBeHidden({ timeout: 10_000 });
        await expect(
            page.locator('table tbody tr').filter({ hasText: userData.email }),
        ).toHaveCount(0);
    });
});