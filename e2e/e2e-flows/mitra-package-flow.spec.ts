import { test, expect } from '../fixtures/base';
import { SEL } from '../fixtures/selectors';
import { MitraPackagePage } from '../pages/MitraPackagePage';

test.describe
    .serial('E2E Cross-Role — Mitra Onboarding, Publikasi Paket & Katalog Client', () => {
    const runTimestamp = Date.now();

    const stateByProject: Record<
        string,
        {
            mitraEmail: string;
            mitraUsername: string;
            mitraPassword: string;
            namaUsaha: string;
            packageName: string;
            updatedPackageName: string;
            packageId: string;
            packageSlug: string;
        }
    > = {};

    function getState(projectName: string) {
        if (!stateByProject[projectName]) {
            const suffix = `${projectName}-${runTimestamp}`;
            const packageName = `Eksplorasi Loksado & Rakit Bambu ${suffix}`;
            stateByProject[projectName] = {
                mitraEmail: `mitra-flow-${suffix}@test.com`,
                mitraUsername: `mitraflow${projectName}${runTimestamp}`,
                mitraPassword: 'password123',
                namaUsaha: `Borneo Adventure ${suffix}`,
                packageName,
                updatedPackageName: `${packageName} (Edisi Spesial)`,
                packageId: '',
                packageSlug: '',
            };
        }
        return stateByProject[projectName];
    }

    test('Flow 1: Mitra terverifikasi → Lengkapi Onboarding → Buat Paket (Fase 1 & 2) → Terbit', async ({
        page,
        asGuest,
        seed,
    }, testInfo) => {
        const state = getState(testInfo.project.name);
        const pkgPage = new MitraPackagePage(page);

        // --------------------------------------------------
        // 1. Mitra baru (setup via fixture, alur register+verifikasi
        //    sudah diuji di e2e/auth/register-mitra.spec.ts)
        // --------------------------------------------------
        await asGuest();
        await seed.createUser(page, {
            email: state.mitraEmail,
            name: `Owner ${state.namaUsaha}`,
            username: state.mitraUsername,
            role: 'mitra',
            password: state.mitraPassword,
            email_verified_at: true,
            with_bank_account: false,
        });
        await seed.login(page, state.mitraEmail, state.mitraPassword);
        await page.goto('/mitra/dashboard');
        await expect(page).toHaveURL('/mitra/onboarding', { timeout: 20_000 });

        // --------------------------------------------------
        // 2. Onboarding Mitra (/mitra/onboarding)
        // --------------------------------------------------
        await expect(
            page.getByRole('heading', { name: /Data Usaha/i }),
        ).toBeVisible();

        await page.locator('#nama_usaha').fill(state.namaUsaha);
        await page.locator('#no_hp').fill('081234567890');
        await page
            .locator('#deskripsi')
            .fill('Operator wisata alam dan petualangan Kalimantan Selatan.');

        await page.locator('#tipe_0').selectOption('bank');
        await page.locator('#nama_penyedia_0').selectOption('BCA');
        await page.locator('#nomor_rekening_0').fill('1234567890');
        await page.locator('#nama_pemilik_0').fill(`Owner ${state.namaUsaha}`);

        await page
            .getByRole('button', { name: /Simpan & Masuk Dashboard/i })
            .click();
        await expect(page).toHaveURL('/mitra/dashboard', { timeout: 20_000 });

        // --------------------------------------------------
        // 3. Menyelesaikan Fase 1 (Create.svelte - 3 Step) & diarahkan ke /complete-phase2
        // --------------------------------------------------
        await pkgPage.gotoCreate();

        // Step 1
        await pkgPage.fillStep1({
            nama: state.packageName,
            deskripsi:
                'Paket bamboo rafting menyusuri Sungai Amandit dan wisata budaya Dayak Meratus.',
            kategori: 'petualangan',
            timezone: 'Asia/Makassar',
        });
        await pkgPage.nextStepButton.click();

        // Step 2
        await pkgPage.fillStep2({
            durasiHari: 2,
            durasiMalam: 1,
            hargaRaw: '650000',
            expectedFormattedHarga: '650.000',
        });
        await pkgPage.nextStepButton.click();

        // Step 3 (Review) -> Submit Fase 1
        await pkgPage.submitCreateButton.click();
        await expect(page).toHaveURL(
            /\/mitra\/packages\/\d+\/complete-phase2$/,
            { timeout: 20_000 },
        );

        const phase2Match = page
            .url()
            .match(/\/mitra\/packages\/(\d+)\/complete-phase2$/);
        expect(phase2Match).not.toBeNull();
        state.packageId = phase2Match![1];

        // --------------------------------------------------
        // 4. CompletePhase2 (3 Step: Rencana Kegiatan -> Fasilitas -> Review & Terbit)
        // --------------------------------------------------
        // Step 1: Rencana Kegiatan (2 hari sesuai durasi_hari = 2)
        await pkgPage.addItineraryDay({
            judul: 'Bamboo Rafting Sungai Amandit',
            jamMulai: '08:30',
            jamSelesai: '12:00',
            lokasi: 'Dermaga Loksado, Hulu Sungai Selatan',
            deskripsi:
                'Menyusuri jeram sungai menggunakan rakit bambu tradisional bersama joki lokal.',
        });
        await pkgPage.addItineraryDay({
            judul: 'Wisata Budaya Dayak Meratus & Kepulangan',
            jamMulai: '09:00',
            jamSelesai: '14:00',
            lokasi: 'Balai Adat Malaris, Loksado',
            deskripsi:
                'Kunjungan balai adat dan pengantaran kembali ke titik kumpul.',
        });

        await expect(pkgPage.nextStepButton).toBeEnabled();
        await pkgPage.nextStepButton.click();

        // Step 2: Fasilitas (minimal 1 Termasuk & 1 Tidak Termasuk)
        await expect(pkgPage.facilityNamaInput).toBeVisible();
        await pkgPage.addFacility({
            tipe: 'include',
            nama: 'Rakit Bambu & Pemandu Lokal',
        });
        await pkgPage.addFacility({
            tipe: 'exclude',
            nama: 'Pengeluaran Pribadi & Oleh-oleh',
        });

        // Klik tombol "Lanjut" dari Step 2 (Fasilitas) menuju Step 3 (Review & Terbit)
        await expect(pkgPage.nextStepButton).toBeEnabled();
        await pkgPage.nextStepButton.click();

        // Step 3: Review & Terbit -> Klik tombol hijau "Simpan & Terbit"
        await expect(page.getByText('Paket Siap Terbit!')).toBeVisible();
        const simpanDanTerbitBtn = page.getByRole('button', {
            name: 'Simpan & Terbit',
        });
        await expect(simpanDanTerbitBtn).toBeVisible();
        await expect(simpanDanTerbitBtn).toBeEnabled();
        await simpanDanTerbitBtn.click();

        await expect(page).toHaveURL(
            new RegExp(`/mitra/packages/${state.packageId}$`),
            {
                timeout: 20_000,
            },
        );
        await expect(
            page.getByRole('heading', { name: state.packageName }),
        ).toBeVisible();
        await expect(pkgPage.archivePackageButton).toBeVisible();
    });

    test('Flow 2: Paket yang baru diterbitkan Mitra muncul & dapat diakses di Katalog Client', async ({
        page,
        asClient,
    }, testInfo) => {
        const state = getState(testInfo.project.name);

        await asClient();
        await page.goto('/client/catalog', { waitUntil: 'domcontentloaded' });
        await expect(
            page.getByRole('heading', { name: 'Jelajahi Paket', level: 1 }),
        ).toBeVisible();

        // Tunggu response pencarian debounce selesai sebelum klik Lihat Detail
        await Promise.all([
            page.waitForResponse(
                (res) =>
                    res.url().includes('/client/catalog') &&
                    res.url().includes('search=') &&
                    res.request().method() === 'GET',
            ),
            page
                .getByPlaceholder('Cari destinasi wisata…')
                .fill(state.packageName),
        ]);

        // Pastikan kartu spesifik milik project ini tampil dan hanya kartu tersebut yang diklik
        const targetCard = page
            .locator('article, div')
            .filter({
                has: page.getByRole('heading', { name: state.packageName }),
            })
            .filter({ has: page.getByRole('link', { name: /Lihat Detail/i }) })
            .last();

        await expect(targetCard).toBeVisible({ timeout: 10_000 });
        await expect(targetCard.getByText(state.namaUsaha)).toBeVisible();

        const detailLink = targetCard.getByRole('link', {
            name: /Lihat Detail/i,
        });
        await Promise.all([
            page.waitForURL(/\/client\/catalog\/.+/, {
                timeout: 20_000,
                waitUntil: 'commit',
            }),
            detailLink.click(),
        ]);

        const slugMatch = page.url().match(/\/client\/catalog\/([^/?#]+)/);
        expect(slugMatch).not.toBeNull();
        state.packageSlug = slugMatch![1];

        await expect(
            page.getByRole('heading', { name: state.packageName }),
        ).toBeVisible({
            timeout: 10_000,
        });
        await expect(
            page.getByText('Bamboo Rafting Sungai Amandit'),
        ).toBeVisible();
        await expect(
            page.getByText('Rakit Bambu & Pemandu Lokal'),
        ).toBeVisible();
        await expect(
            page.getByText('Pengeluaran Pribadi & Oleh-oleh'),
        ).toBeVisible();
        await expect(page.getByText(state.namaUsaha)).toBeVisible();
        await expect(
            page.getByRole('link', { name: /Booking Sekarang/i }),
        ).toBeVisible();
    });

    test('Flow 3: Mitra mengarsipkan paket → Paket hilang dari Katalog Client', async ({
        page,
        asGuest,
        asClient,
    }, testInfo) => {
        const state = getState(testInfo.project.name);
        const pkgPage = new MitraPackagePage(page);

        await asGuest();
        await page.goto('/login', { waitUntil: 'domcontentloaded' });
        await SEL.AUTH.login.email(page).fill(state.mitraEmail);
        await SEL.AUTH.login.password(page).fill(state.mitraPassword);
        await Promise.all([
            page.waitForURL((url) => !url.pathname.includes('/login'), {
                timeout: 20_000,
                waitUntil: 'commit',
            }),
            SEL.AUTH.login.submit(page).click(),
        ]);

        await page.goto(`/mitra/packages/${state.packageId}`, {
            waitUntil: 'domcontentloaded',
        });
        await expect(pkgPage.archivePackageButton).toBeVisible();

        await pkgPage.archivePackageButton.click();
        await pkgPage.confirmModal({
            expectedTitle: /Arsipkan/i,
        });

        await expect(pkgPage.archivePackageButton).toBeHidden();
        await expect(pkgPage.restorePackageButton).toBeVisible();

        // Verifikasi di sisi Client
        await asClient();
        await page.goto('/client/catalog', { waitUntil: 'domcontentloaded' });
        await Promise.all([
            page.waitForResponse(
                (res) =>
                    res.url().includes('/client/catalog') &&
                    res.url().includes('search=') &&
                    res.request().method() === 'GET',
            ),
            page
                .getByPlaceholder('Cari destinasi wisata…')
                .fill(state.packageName),
        ]);

        await expect(
            page.getByText(
                'Tidak ada paket yang sesuai dengan pencarian Anda.',
            ),
        ).toBeVisible({
            timeout: 10_000,
        });

        if (state.packageSlug) {
            const response = await page.goto(
                `/client/catalog/${state.packageSlug}`,
                {
                    waitUntil: 'domcontentloaded',
                },
            );
            expect(response?.status()).toBe(404);
        }
    });

    test('Flow 4: Mitra memulihkan arsip (Draft) lalu menerbitkan ulang → Paket kembali tampil di Katalog Client', async ({
        page,
        asGuest,
        asClient,
    }, testInfo) => {
        const state = getState(testInfo.project.name);
        const pkgPage = new MitraPackagePage(page);

        await asGuest();
        await page.goto('/login', { waitUntil: 'domcontentloaded' });
        await SEL.AUTH.login.email(page).fill(state.mitraEmail);
        await SEL.AUTH.login.password(page).fill(state.mitraPassword);
        await Promise.all([
            page.waitForURL((url) => !url.pathname.includes('/login'), {
                timeout: 20_000,
                waitUntil: 'commit',
            }),
            SEL.AUTH.login.submit(page).click(),
        ]);

        // Restore paket (Diarsipkan → Draft)
        await page.goto(`/mitra/packages/${state.packageId}`, {
            waitUntil: 'domcontentloaded',
        });
        await expect(pkgPage.restorePackageButton).toBeVisible();

        await pkgPage.restorePackageButton.click();
        await pkgPage.confirmModal({
            expectedTitle: /Aktifkan/i,
        });

        await expect(pkgPage.restorePackageButton).toBeHidden();
        await expect(pkgPage.continueDraftLink).toBeVisible();

        // Edit nama paket & simpan untuk menerbitkan kembali (Draft → Aktif)
        await Promise.all([
            page.waitForURL(`/mitra/packages/${state.packageId}/edit`, {
                timeout: 20_000,
                waitUntil: 'commit',
            }),
            pkgPage.editPackageLink.click(),
        ]);

        await pkgPage.namaInput.fill(state.updatedPackageName);
        await page.getByRole('button', { name: /Simpan Perubahan/i }).click();

        await expect(page).toHaveURL(
            new RegExp(`/mitra/packages/${state.packageId}$`),
            {
                timeout: 20_000,
            },
        );
        await expect(
            page.getByRole('heading', { name: state.updatedPackageName }),
        ).toBeVisible();
        await expect(pkgPage.archivePackageButton).toBeVisible();

        // Verifikasi paket dengan nama baru kembali tampil di Katalog Client
        await asClient();
        await page.goto('/client/catalog', { waitUntil: 'domcontentloaded' });
        await Promise.all([
            page.waitForResponse(
                (res) =>
                    res.url().includes('/client/catalog') &&
                    res.url().includes('search=') &&
                    res.request().method() === 'GET',
            ),
            page
                .getByPlaceholder('Cari destinasi wisata…')
                .fill(state.updatedPackageName),
        ]);

        await expect(
            page.getByRole('heading', { name: state.updatedPackageName }),
        ).toBeVisible({
            timeout: 10_000,
        });
    });
});
