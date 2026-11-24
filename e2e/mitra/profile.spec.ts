import { test, expect, type Page } from '@playwright/test';
import { SEL } from '../fixtures/selectors';
import { loginAs } from '../fixtures/auth';

async function dismissNotificationPrompt(page: Page): Promise<void> {
    await page.addLocatorHandler(
        page.getByRole('dialog', { name: /Aktifkan Notifikasi/i }),
        async () => {
            await page.getByRole('button', { name: /Nanti Saja/i }).click();
        },
    );
}

test.describe('Mitra Profile', () => {
    test.beforeEach(async ({ page }) => {
        await dismissNotificationPrompt(page);
        await loginAs(page, 'mitra');
        await page.goto('/mitra/profile');
        await page.waitForLoadState('networkidle');
        await expect(
            page.getByRole('heading', { name: 'Informasi Usaha' }),
        ).toBeVisible();
    });

    test('mitra dapat memperbarui informasi usaha dan menyimpannya', async ({
        page,
    }) => {
        const namaUsahaInput = SEL.MITRA.profile.namaPenyediaUsaha(page);
        const nomorIdentitasInput = SEL.MITRA.profile.nomorIdentitas(page);
        const teleponInput = SEL.MITRA.profile.telepon(page);
        const kotaInput = SEL.MITRA.profile.kota(page);
        const provinsiInput = SEL.MITRA.profile.provinsi(page);
        const alamatInput = SEL.MITRA.profile.alamat(page);
        const kodePosInput = SEL.MITRA.profile.kodePos(page);
        const deskripsiInput = SEL.MITRA.profile.deskripsi(page);

        const originalNama = await namaUsahaInput.inputValue();
        const originalNomorIdentitas = await nomorIdentitasInput.inputValue();
        const originalDeskripsi = await deskripsiInput.inputValue();

        const newNama = `${originalNama || 'Wisata Borneo'} E2E`;
        const newNomorIdentitas = '6371012345670001';
        const newDeskripsi =
            'Deskripsi paket wisata terpercaya di Kalimantan Selatan (E2E).';

        await namaUsahaInput.fill(newNama);
        await nomorIdentitasInput.fill(newNomorIdentitas);
        await teleponInput.fill('081234567890');
        await provinsiInput.fill('Kalimantan Selatan');
        await kotaInput.fill('Banjarmasin');
        await kodePosInput.fill('70111');
        await alamatInput.fill('Jl. Jend. Ahmad Yani KM 7,5 Kertak Hanyar');
        await deskripsiInput.fill(newDeskripsi);

        await page.getByRole('button', { name: /Simpan Profil/i }).click();

        await expect(
            SEL.LAYOUT.toastRegion(page)
                .getByText(/Profil berhasil diperbarui/i)
                .last(),
        ).toBeVisible();

        // Reload & verifikasi persistensi
        await page.reload();
        await page.waitForLoadState('networkidle');
        await expect(namaUsahaInput).toHaveValue(newNama);
        await expect(nomorIdentitasInput).toHaveValue(newNomorIdentitas);
        await expect(deskripsiInput).toHaveValue(newDeskripsi);

        // Restore data semula agar akun seed tetap bersih
        await namaUsahaInput.fill(originalNama);
        await nomorIdentitasInput.fill(originalNomorIdentitas);
        await deskripsiInput.fill(originalDeskripsi);
        await page.getByRole('button', { name: /Simpan Profil/i }).click();

        await expect(
            SEL.LAYOUT.toastRegion(page)
                .getByText(/Profil berhasil diperbarui/i)
                .last(),
        ).toBeVisible();
    });

    test('tetap berhasil menyimpan jika nama usaha dikosongkan (fallback ke nama akun)', async ({
        page,
    }) => {
        const namaUsahaInput = SEL.MITRA.profile.namaPenyediaUsaha(page);
        const originalNama = await namaUsahaInput.inputValue();

        await namaUsahaInput.fill('');
        await page.getByRole('button', { name: /Simpan Profil/i }).click();

        await expect(
            SEL.LAYOUT.toastRegion(page)
                .getByText(/Profil berhasil diperbarui/i)
                .first(),
        ).toBeVisible();

        // Kembalikan nama usaha semula
        await namaUsahaInput.fill(originalNama);
        await page.getByRole('button', { name: /Simpan Profil/i }).click();
        await expect(
            SEL.LAYOUT.toastRegion(page)
                .getByText(/Profil berhasil diperbarui/i)
                .last(),
        ).toBeVisible();
    });
});
