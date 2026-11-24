import { expect, test, SEED_USERS } from '../fixtures/base';
import { MitraPackagePage } from '../pages/MitraPackagePage';

test.describe('Mitra - Manajemen Paket Wisata (E2E)', () => {
    test.beforeEach(async ({ page }) => {
        const pkgPage = new MitraPackagePage(page);
        await pkgPage.loginAsSeededMitra();
    });

    test('wizard pembuatan paket (Create.svelte) memvalidasi setiap step dan memformat RupiahInput', async ({
        page,
    }) => {
        const pkgPage = new MitraPackagePage(page);
        await pkgPage.gotoCreate();

        await test.step('Step 1: Tombol Lanjut disabled sampai Nama Paket terisi', async () => {
            await expect(pkgPage.nextStepButton).toBeDisabled();

            await pkgPage.fillStep1({
                nama: 'Paket Wisata Loksado 2D1N',
                deskripsi:
                    'Menyusuri Sungai Amandit menggunakan lanting bambu tradisional.',
                kategori: 'petualangan',
                timezone: 'Asia/Makassar',
            });

            await expect(pkgPage.nextStepButton).toBeEnabled();
            await pkgPage.nextStepButton.click();
        });

        await test.step('Step 2: RupiahInput memformat pemisah ribuan & mendukung mode harga rombongan', async () => {
            await expect(pkgPage.nextStepButton).toBeDisabled();

            // Mode Per Orang
            await pkgPage.hargaPerOrangInput.fill('750abc000');
            await expect(pkgPage.hargaPerOrangInput).toHaveValue('750.000');
            await expect(pkgPage.nextStepButton).toBeEnabled();

            // Beralih ke Mode Rombongan (Flat)
            await pkgPage.fillStep2({
                durasiHari: 2,
                durasiMalam: 1,
                useRombongan: true,
                hargaRaw: '4500000',
                expectedFormattedHarga: '4.500.000',
                maxPeserta: 10,
            });

            await expect(pkgPage.nextStepButton).toBeEnabled();
            await pkgPage.nextStepButton.click();
        });

        await test.step('Step 3: Ringkasan Review Awal menampilkan data sesuai input wizard', async () => {
            await expect(page.getByText('Review Paket Anda')).toBeVisible();
            await expect(
                page.getByText('Paket Wisata Loksado 2D1N'),
            ).toBeVisible();
            await expect(
                page.getByText(/petualangan · Makassar/i),
            ).toBeVisible();
            await expect(page.getByText('2 Hari 1 Malam')).toBeVisible();
            await expect(page.getByText(/4\.500\.000.*\/ grup/i)).toBeVisible();
        });
    });

    test('pembuatan paket baru -> DaysEditor & FacilitiesEditor -> hapus paket via ConfirmModal', async ({
        page,
    }) => {
        const pkgPage = new MitraPackagePage(page);
        const packageTitle = `Paket Eksplorasi Pasar Terapung ${Date.now()}`;

        await test.step('1. Menyelesaikan Fase 1 dan diarahkan ke /complete-phase2', async () => {
            await pkgPage.gotoCreate();

            await pkgPage.fillStep1({
                nama: packageTitle,
                deskripsi: 'Wisata susur sungai pagi hari di Lok Baintan.',
                kategori: 'wisata_alam',
                timezone: 'Asia/Makassar',
            });
            await pkgPage.nextStepButton.click();

            await pkgPage.fillStep2({
                durasiHari: 2,
                durasiMalam: 1,
                hargaRaw: '650000',
                expectedFormattedHarga: '650.000',
            });
            await pkgPage.nextStepButton.click();
            await expect(page.getByText('Review Paket Anda')).toBeVisible();

            await Promise.all([
                page.waitForURL(/\/mitra\/packages\/\d+\/complete-phase2$/, {
                    timeout: 20_000,
                    waitUntil: 'commit',
                }),
                pkgPage.submitCreateButton.click(),
            ]);
        });

        await test.step('2. Menguji DaysEditor (Step 1) & FacilitiesEditor (Step 2) di CompletePhase2', async () => {
            // --- STEP 1: RENCANA PERJALANAN (DaysEditor) ---
            await pkgPage.addDayButton.click();
            await expect(
                page
                    .getByRole('alert')
                    .filter({ hasText: 'Judul kegiatan wajib diisi.' }),
            ).toBeVisible();

            await pkgPage.addItineraryDay({
                judul: 'Penjemputan Bandara Syamsudin Noor',
                jamMulai: '08:00',
                jamSelesai: '10:00',
                lokasi: 'Banjarbaru',
                deskripsi: 'Pertemuan dengan pemandu lokal.',
            });
            await pkgPage.addItineraryDay({
                judul: 'Susur Sungai Pasar Terapung Lok Baintan',
                jamMulai: '05:30',
                jamSelesai: '09:00',
                lokasi: 'Sungai Martapura',
            });
            await expect(
                page.getByText('Tambahkan kegiatan per hari (2 hari)'),
            ).toBeVisible();

            // Hapus hari ke-1 untuk memastikan penomoran ulang otomatis berjalan
            await pkgPage.removeItineraryDay(1);
            await expect(
                page.getByText('Penjemputan Bandara Syamsudin Noor'),
            ).toBeHidden();
            await expect(page.getByText('Hari ke-1')).toBeVisible();
            await expect(
                page.getByText('Susur Sungai Pasar Terapung Lok Baintan'),
            ).toBeVisible();

            // Lengkapi kembali menjadi 2 hari sesuai durasi_hari = 2 agar tombol Lanjut aktif
            await pkgPage.addItineraryDay({
                judul: 'Wisata Kuliner & Pengantaran Kepulangan',
                jamMulai: '10:00',
                jamSelesai: '15:00',
                lokasi: 'Banjarmasin',
            });
            await expect(
                page.getByText('Tambahkan kegiatan per hari (2 hari)'),
            ).toBeVisible();

            // Pindah ke Step 2 CompletePhase2 (Fasilitas)
            await expect(pkgPage.nextStepButton).toBeEnabled();
            await pkgPage.nextStepButton.click();
            await expect(pkgPage.facilityNamaInput).toBeVisible();

            // --- STEP 2: FASILITAS PAKET (FacilitiesEditor) ---
            await pkgPage.addFacilityButton.click();
            await expect(
                page
                    .getByRole('alert')
                    .filter({ hasText: 'Nama fasilitas wajib diisi.' }),
            ).toBeVisible();

            await pkgPage.addFacility({
                tipe: 'include',
                nama: 'Klotok Wisata PP',
            });
            await pkgPage.addFacility({
                tipe: 'include',
                nama: 'Sarapan Soto Banjar',
            });
            await pkgPage.addFacility({
                tipe: 'exclude',
                nama: 'Asuransi Perjalanan',
            });

            await expect(
                page.getByRole('heading', {
                    name: 'Termasuk (2)',
                    exact: true,
                }),
            ).toBeVisible();
            await expect(
                page.getByRole('heading', {
                    name: 'Tidak Termasuk (1)',
                    exact: true,
                }),
            ).toBeVisible();

            await pkgPage.removeFacility('Sarapan Soto Banjar');
            await expect(
                page.getByRole('heading', {
                    name: 'Termasuk (1)',
                    exact: true,
                }),
            ).toBeVisible();
        });

        await test.step('3. Menghapus paket draft dari halaman detail melalui ConfirmModal', async () => {
            const match = page.url().match(/\/mitra\/packages\/(\d+)/);
            expect(match).not.toBeNull();
            await page.goto(`/mitra/packages/${match![1]}`, {
                waitUntil: 'domcontentloaded',
            });

            await expect(pkgPage.continueDraftLink).toBeVisible();
            await expect(pkgPage.deletePackageButton).toBeVisible();

            // Uji penutupan modal dengan tombol Escape
            await pkgPage.deletePackageButton.click();
            await expect(pkgPage.confirmDialog).toBeVisible();
            await page.keyboard.press('Escape');
            await expect(pkgPage.confirmDialog).toBeHidden();

            // Konfirmasi hapus permanen
            await pkgPage.deletePackageButton.click();
            await pkgPage.confirmModal({
                expectedTitle: /Hapus/i,
            });

            await expect(page).toHaveURL(/\/mitra\/packages$/);
            await expect(page.getByText(packageTitle)).toBeHidden();
        });
    });

    test('PackageActions mengelola siklus status Arsipkan & Aktifkan Kembali pada paket aktif', async ({
        page,
        seed,
    }) => {
        const pkgPage = new MitraPackagePage(page);
        const created = await seed.createPackage(page, {
            mitra_email: SEED_USERS.mitra.email,
            nama: `Paket Siklus Arsip E2E ${Date.now()}`,
            kategori: 'wisata_alam',
            status: 'aktif',
            days: [{ judul: 'Hari 1' }],
            facilities: [
                { tipe: 'include', nama: 'Transportasi' },
                { tipe: 'exclude', nama: 'Pengeluaran Pribadi' },
            ],
        });

        await page.goto(`/mitra/packages/${created.package_id}`, {
            waitUntil: 'domcontentloaded',
        });

        await test.step('Arsipkan paket aktif melalui ConfirmModal', async () => {
            await expect(pkgPage.archivePackageButton).toBeVisible({
                timeout: 10_000,
            });
            await expect(pkgPage.manualBookingLink).toBeVisible();

            await pkgPage.archivePackageButton.click();
            await pkgPage.confirmModal({
                expectedTitle: /Arsipkan/i,
            });

            await expect(pkgPage.archivePackageButton).toBeHidden();
            await expect(pkgPage.restorePackageButton).toBeVisible();
        });

        await test.step('Aktifkan kembali paket yang diarsipkan menjadi draft', async () => {
            await pkgPage.restorePackageButton.click();
            await pkgPage.confirmModal({
                expectedTitle: /Aktifkan/i,
            });

            await expect(pkgPage.restorePackageButton).toBeHidden();
            await expect(pkgPage.continueDraftLink).toBeVisible();
        });
    });

    test('Error Handling (Level UI): menangkap error validasi backend menjadi pesan ramah pengguna', async ({
        page,
    }) => {
        const pkgPage = new MitraPackagePage(page);
        await pkgPage.gotoCreate();

        // Kirim spasi kosong untuk lolos pengecekan truthy JS di client,
        // lalu biarkan middleware TrimStrings + FormRequest Laravel menolaknya (422)
        await pkgPage.fillStep1({
            nama: '   ',
            kategori: 'wisata_alam',
            timezone: 'Asia/Makassar',
        });
        await pkgPage.nextStepButton.click();

        await pkgPage.fillStep2({
            durasiHari: 1,
            durasiMalam: 0,
            hargaRaw: '250000',
            expectedFormattedHarga: '250.000',
        });
        await pkgPage.nextStepButton.click();
        await expect(page.getByText('Review Paket Anda')).toBeVisible();

        await pkgPage.submitCreateButton.click();

        // Create.svelte otomatis mengembalikan user ke Step 1 dan menampilkan pesan error dari backend
        await expect(pkgPage.namaInput).toBeVisible();
        await expect(
            page
                .locator('form p, form span, [role="alert"]')
                .filter({ hasText: /nama|wajib|diisi/i })
                .first(),
        ).toBeVisible();
    });

    test('Usability & Accessibility: navigasi keyboard (Tab) pada form pembuatan paket', async ({
        page,
    }) => {
        const pkgPage = new MitraPackagePage(page);
        await pkgPage.gotoCreate();
        await page.waitForLoadState('networkidle');

        // Beri waktu jika dialog notifikasi muncul tertunda agar ditutup handler sebelum uji fokus keyboard dimulai
        await page.waitForTimeout(2_000);

        await pkgPage.namaInput.focus();
        await expect(pkgPage.namaInput).toBeFocused();
        await page.keyboard.type('Paket Aksesibilitas Keyboard');

        await page.keyboard.press('Tab');
        await expect(pkgPage.deskripsiInput).toBeFocused();
        await page.keyboard.type('Deskripsi diisi penuh menggunakan keyboard.');

        await page.keyboard.press('Tab');
        await expect(pkgPage.kategoriSelect).toBeFocused();

        await page.keyboard.press('Tab');
        await expect(pkgPage.timezoneSelect).toBeFocused();

        await expect(pkgPage.nextStepButton).toBeEnabled();
    });

    test('Cross-Environment (Mobile Viewport): tampilan detail & aksi paket berfungsi normal di layar ponsel', async ({
        page,
        seed,
    }) => {
        const pkgPage = new MitraPackagePage(page);
        const created = await seed.createPackage(page, {
            mitra_email: SEED_USERS.mitra.email,
            nama: `Paket Mobile Viewport E2E ${Date.now()}`,
            kategori: 'wisata_alam',
            status: 'aktif',
            days: [{ judul: 'Hari 1' }],
            facilities: [
                { tipe: 'include', nama: 'Guide' },
                { tipe: 'exclude', nama: 'Tiket Pesawat' },
            ],
        });

        await page.setViewportSize({ width: 375, height: 812 });
        await page.goto(`/mitra/packages/${created.package_id}`, {
            waitUntil: 'domcontentloaded',
        });

        // Pastikan tombol aksi utama tampil utuh baik pada status 'aktif' maupun 'draft'
        await expect(pkgPage.editPackageLink).toBeVisible({ timeout: 10_000 });
        await expect(pkgPage.manualBookingLink).toBeVisible();
        await expect(
            pkgPage.archivePackageButton.or(pkgPage.continueDraftLink),
        ).toBeVisible();

        const hasHorizontalOverflow = await page.evaluate(() => {
            return (
                document.documentElement.scrollWidth >
                document.documentElement.clientWidth
            );
        });
        expect(hasHorizontalOverflow).toBe(false);
    });
});
