import { test, expect, SEED_USERS } from '../fixtures/base';
import { SEL } from '../fixtures/selectors';

test.describe('Client Profile', () => {
    test.beforeEach(async ({ page, asClient }) => {
        await asClient();
        await page.goto('/client/profile', { waitUntil: 'domcontentloaded' });
        await expect(SEL.CLIENT.profile.name(page)).toBeVisible();
    });

    test('menampilkan form profil dengan tombol submit & reset disabled saat belum ada perubahan', async ({
        page,
    }) => {
        const nameInput = SEL.CLIENT.profile.name(page);
        const usernameInput = SEL.CLIENT.profile.username(page);
        const emailInput = page.locator('#email');

        await expect(nameInput).toHaveValue(SEED_USERS.client.name);
        await expect(usernameInput).toHaveValue(SEED_USERS.client.username);
        await expect(emailInput).toBeDisabled();

        await expect(SEL.CLIENT.profile.reset(page)).toBeDisabled();
        await expect(SEL.CLIENT.profile.submit(page)).toBeDisabled();
    });

    test('tombol reset mengembalikan nilai input ke data awal', async ({ page }) => {
        const nameInput = SEL.CLIENT.profile.name(page);
        const originalName = await nameInput.inputValue();

        await nameInput.fill(`${originalName} Sementara`);
        await expect(SEL.CLIENT.profile.reset(page)).toBeEnabled();
        await expect(SEL.CLIENT.profile.submit(page)).toBeEnabled();

        await SEL.CLIENT.profile.reset(page).click();

        await expect(nameInput).toHaveValue(originalName);
        await expect(SEL.CLIENT.profile.reset(page)).toBeDisabled();
        await expect(SEL.CLIENT.profile.submit(page)).toBeDisabled();
    });

    test('client dapat memperbarui nama lengkap dan username lalu mengembalikannya', async ({
        page,
    }) => {
        const nameInput = SEL.CLIENT.profile.name(page);
        const usernameInput = SEL.CLIENT.profile.username(page);

        const originalName = SEED_USERS.client.name;
        const originalUsername = SEED_USERS.client.username;

        const updatedName = `${originalName} Updated`;
        const updatedUsername = `${originalUsername}_e2e`;

        // 1. Update ke nilai baru
        await nameInput.fill(updatedName);
        await usernameInput.fill(updatedUsername);
        await SEL.CLIENT.profile.submit(page).click();

        await expect(
            SEL.LAYOUT.toastRegion(page).getByText(/Profil berhasil diperbarui/i),
        ).toBeVisible();

        // Reload untuk memastikan tersimpan di database
        await page.reload({ waitUntil: 'domcontentloaded' });
        await expect(nameInput).toHaveValue(updatedName);
        await expect(usernameInput).toHaveValue(updatedUsername);

        // 2. Restore ke nilai semula agar akun seed tetap bersih
        await nameInput.fill(originalName);
        await usernameInput.fill(originalUsername);
        await SEL.CLIENT.profile.submit(page).click();

        await expect(
            SEL.LAYOUT.toastRegion(page).getByText(/Profil berhasil diperbarui/i),
        ).toBeVisible();
    });

    test('menampilkan error validasi jika nama dikosongkan', async ({ page }) => {
        const nameInput = SEL.CLIENT.profile.name(page);
        await nameInput.fill('   ');

        await page
            .locator('form')
            .filter({ has: nameInput })
            .evaluate((form: HTMLFormElement) => form.requestSubmit());

        await expect(page.getByText(/Terdapat 1 kesalahan pada form\./i)).toBeVisible();
        await expect(page.getByText(/Nama lengkap wajib diisi\./i)).toBeVisible();
    });
});