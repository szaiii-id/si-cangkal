import { test, expect, type Page } from '@playwright/test';
import { SEL } from '../fixtures/selectors';
import { loginAs, SEED_USERS } from '../fixtures/auth';

async function unlockBankAccountModal(page: Page, password: string) {
    const triggerBtn = SEL.MITRA.bank
        .editButton(page)
        .or(SEL.MITRA.bank.addFirstButton(page));
    await triggerBtn.click();

    await expect(
        page.getByRole('heading', { name: 'Konfirmasi Keamanan' }),
    ).toBeVisible();

    await SEL.MITRA.bank.confirmPasswordInput(page).fill(password);
    await SEL.MITRA.bank.confirmPasswordSubmit(page).click();

    await expect(
        page.getByRole('heading', { name: 'Edit Rekening Pembayaran' }),
    ).toBeVisible();
}

test.describe('Mitra Bank Accounts', () => {
    test.beforeEach(async ({ page }) => {
        await loginAs(page, 'mitra');
        await page.goto('/mitra/profile');
        await expect(
            page.getByRole('heading', { name: /Rekening Pembayaran/i }),
        ).toBeVisible();
    });

    test('validasi modal konfirmasi keamanan jika password kurang dari 5 karakter', async ({
        page,
    }) => {
        const triggerBtn = SEL.MITRA.bank
            .editButton(page)
            .or(SEL.MITRA.bank.addFirstButton(page));
        await triggerBtn.click();

        await expect(
            page.getByRole('heading', { name: 'Konfirmasi Keamanan' }),
        ).toBeVisible();

        await SEL.MITRA.bank.confirmPasswordInput(page).fill('123');
        await SEL.MITRA.bank.confirmPasswordSubmit(page).click();

        await expect(page.locator('#password-error')).toContainText(
            /Password wajib diisi dan minimal 5 karakter/i,
        );
    });

    test('gagal memperbarui rekening jika password konfirmasi salah', async ({
        page,
    }) => {
        await unlockBankAccountModal(page, 'PasswordSalah999!');

        await SEL.MITRA.bank.saveButton(page).click();

        await expect(
            SEL.LAYOUT.toastRegion(page).getByText(
                /Gagal memperbarui rekening/i,
            ),
        ).toBeVisible();
        await expect(
            page.getByText(/Password yang Anda masukkan salah/i),
        ).toBeVisible();
    });

    test('mitra dapat menambah rekening e-wallet baru dan menghapusnya kembali', async ({
        page,
    }) => {
        await unlockBankAccountModal(page, SEED_USERS.mitra.password);

        const initialCount = await page.locator('[id^="tipe_"]').count();

        await SEL.MITRA.bank.addMoreButton(page).click();

        const newIdx = initialCount;
        await SEL.MITRA.bank.tipe(page, newIdx).selectOption('ewallet');
        await SEL.MITRA.bank.penyedia(page, newIdx).selectOption('GOPAY');
        await SEL.MITRA.bank.nomorRekening(page, newIdx).fill('081299887766');
        await SEL.MITRA.bank
            .namaPemilik(page, newIdx)
            .fill('Mitra E2E Gopay');

        await SEL.MITRA.bank.saveButton(page).click();

        await expect(
            SEL.LAYOUT.toastRegion(page).getByText(
                /Data rekening berhasil diperbarui/i,
            ),
        ).toBeVisible();

        // Reload & verifikasi rekening baru tampil di card Rekening Pembayaran
        await page.reload();
        await expect(
            page.getByText('GOPAY', { exact: true }).first(),
        ).toBeVisible();
        await expect(page.getByText(/7766/).first()).toBeVisible();

        // Restore: buka kembali modal & hapus rekening terakhir yang ditambahkan
        await unlockBankAccountModal(page, SEED_USERS.mitra.password);
        const totalAfter = await page.locator('[id^="tipe_"]').count();
        await SEL.MITRA.bank.removeButton(page, totalAfter - 1).click();
        await SEL.MITRA.bank.saveButton(page).click();

        await expect(
            SEL.LAYOUT.toastRegion(page).getByText(
                /Data rekening berhasil diperbarui/i,
            ),
        ).toBeVisible();
    });
});