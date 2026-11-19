import { test, expect, SEED_USERS } from '../fixtures/base';
import { SEL } from '../fixtures/selectors';
import { loginAs } from '../fixtures/auth';
import { seed } from '../fixtures/seed';

test.describe('Client Catalog', () => {
    test.beforeEach(async ({ page }) => {
        await loginAs(page, 'client');
    });

    test('katalog hanya menampilkan paket aktif dan menyembunyikan paket draft/arsip', async ({
        page,
    }) => {
        const suffix = Date.now();
        const activeName = `Paket Aktif E2E ${suffix}-1`;
        const draftName = `Paket Draft E2E ${suffix}-2`;
        const arsipName = `Paket Arsip E2E ${suffix}-3`;

        // Membuat paket aktif, draft, dan arsip menggunakan helper seed
        // Gunakan suffix berbeda (-1, -2, -3) agar tidak bentrok kode paket saat dieksekusi di milidetik yang sama
        await seed.createPackage(page, {
            mitra_email: SEED_USERS.mitra.email,
            nama: activeName,
            kategori: 'wisata_alam',
            status: 'aktif',
            days: [{ judul: 'Hari 1' }],
            facilities: [
                { tipe: 'include', nama: 'Makan' },
                { tipe: 'exclude', nama: 'Tiket Pesawat' },
            ],
        });
        await seed.createPackage(page, {
            mitra_email: SEED_USERS.mitra.email,
            nama: draftName,
            kategori: 'wisata_alam',
            status: 'draft',
            days: [{ judul: 'Hari 1' }],
            facilities: [
                { tipe: 'include', nama: 'Makan' },
                { tipe: 'exclude', nama: 'Tiket Pesawat' },
            ],
        });
        await seed.createPackage(page, {
            mitra_email: SEED_USERS.mitra.email,
            nama: arsipName,
            kategori: 'wisata_alam',
            status: 'arsip',
            days: [{ judul: 'Hari 1' }],
            facilities: [
                { tipe: 'include', nama: 'Makan' },
                { tipe: 'exclude', nama: 'Tiket Pesawat' },
            ],
        });

        await page.goto('/client/catalog', { waitUntil: 'domcontentloaded' });
        await expect(
            page.getByRole('heading', { name: 'Jelajahi Paket', level: 1 }),
        ).toBeVisible();

        // Cari dengan suffix unik dan tunggu response pencarian selesai
        await Promise.all([
            page.waitForResponse(
                (res) =>
                    res.url().includes('/client/catalog') &&
                    res.url().includes(`search=${suffix}`) &&
                    res.request().method() === 'GET',
            ),
            SEL.CLIENT.catalog.searchInput(page).fill(String(suffix)),
        ]);

        await expect(page.getByText(activeName)).toBeVisible();
        await expect(page.getByText(draftName)).toHaveCount(0);
        await expect(page.getByText(arsipName)).toHaveCount(0);
    });

    test('client dapat memfilter kategori, mencari paket, dan mereset filter', async ({
        page,
    }) => {
        const suffix = Date.now();
        const kulinerName = `Wisata Kuliner Soto ${suffix}-1`;
        const budayaName = `Wisata Budaya Keraton ${suffix}-2`;

        await seed.createPackage(page, {
            mitra_email: SEED_USERS.mitra.email,
            nama: kulinerName,
            kategori: 'kuliner',
            status: 'aktif',
            days: [{ judul: 'Hari 1' }],
            facilities: [
                { tipe: 'include', nama: 'Makan' },
                { tipe: 'exclude', nama: 'Tiket Pesawat' },
            ],
        });
        await seed.createPackage(page, {
            mitra_email: SEED_USERS.mitra.email,
            nama: budayaName,
            kategori: 'budaya',
            status: 'aktif',
            days: [{ judul: 'Hari 1' }],
            facilities: [
                { tipe: 'include', nama: 'Makan' },
                { tipe: 'exclude', nama: 'Tiket Pesawat' },
            ],
        });

        await page.goto('/client/catalog', { waitUntil: 'domcontentloaded' });
        await expect(
            page.getByRole('heading', { name: 'Jelajahi Paket', level: 1 }),
        ).toBeVisible();

        // Filter kategori Kuliner
        await page.getByRole('button', { name: 'Kuliner', exact: true }).click();
        await Promise.all([
            page.waitForResponse(
                (res) =>
                    res.url().includes('/client/catalog') &&
                    res.url().includes(`search=${suffix}`) &&
                    res.request().method() === 'GET',
            ),
            SEL.CLIENT.catalog.searchInput(page).fill(String(suffix)),
        ]);

        await expect(page.getByText(kulinerName)).toBeVisible();
        await expect(page.getByText(budayaName)).toHaveCount(0);

        // Pencarian yang tidak cocok -> empty state + tombol Hapus Filter
        await SEL.CLIENT.catalog
            .searchInput(page)
            .fill(`TidakAdaPaket_${suffix}`);
        await expect(
            page.getByText(/Tidak ada paket yang sesuai dengan pencarian Anda/i),
        ).toBeVisible();

        await page.getByRole('button', { name: 'Hapus Filter' }).click();
        await expect(SEL.CLIENT.catalog.searchInput(page)).toHaveValue('');
    });

    test('halaman detail paket menampilkan itinerary, fasilitas, info mitra, dan tombol booking', async ({
        page,
    }) => {
        const suffix = Date.now();
        const pkgName = `Paket Detail E2E ${suffix}`;
        const pkgData = await seed.createPackage(page, {
            mitra_email: SEED_USERS.mitra.email,
            nama: pkgName,
            kategori: 'petualangan',
            status: 'aktif',
            days: [{ judul: 'Eksplorasi Alam' }],
            facilities: [
                { tipe: 'include', nama: 'Guide Lokal' },
                { tipe: 'exclude', nama: 'Pengeluaran Pribadi' },
            ],
        } as unknown as Parameters<typeof seed.createPackage>[1]);

        await page.goto('/client/catalog', { waitUntil: 'domcontentloaded' });
        await expect(
            page.getByRole('heading', { name: 'Jelajahi Paket', level: 1 }),
        ).toBeVisible();

        // Tunggu response pencarian selesai agar tidak menimpa navigasi klik kartu paket
        await Promise.all([
            page.waitForResponse(
                (res) =>
                    res.url().includes('/client/catalog') &&
                    res.url().includes(`search=${suffix}`) &&
                    res.request().method() === 'GET',
            ),
            SEL.CLIENT.catalog.searchInput(page).fill(String(suffix)),
        ]);

        // Klik card atau tombol detail pada paket tersebut dan tunggu navigasi SPA selesai
        const pkgLink = page.getByRole('link', { name: pkgName }).first();
        await expect(pkgLink).toBeVisible({ timeout: 10_000 });

        await Promise.all([
            page.waitForURL(/\/client\/catalog\/.+/, {
                timeout: 20_000,
                waitUntil: 'commit',
            }),
            pkgLink.click(),
        ]);

        // Verifikasi elemen halaman detail
        await expect(page.locator('h1', { hasText: pkgName })).toBeVisible({
            timeout: 10_000,
        });
        await expect(
            page.getByRole('heading', { name: 'Rencana Perjalanan' }),
        ).toBeVisible();
        await expect(
            page.getByRole('heading', { name: 'Fasilitas' }),
        ).toBeVisible();
        await expect(page.getByText('Diselenggarakan oleh')).toBeVisible();

        const bookingBtn = page.getByRole('link', {
            name: /Booking Sekarang/i,
        });
        await expect(bookingBtn).toBeVisible();
        await expect(bookingBtn).toHaveAttribute(
            'href',
            new RegExp(`/client/bookings/create/${pkgData.package_id}`),
        );
    });

    test('akses detail paket draft atau arsip mengembalikan 404', async ({
        page,
    }) => {
        const suffix = Date.now();
        const draftPkg = await seed.createPackage(page, {
            mitra_email: SEED_USERS.mitra.email,
            nama: `Paket Draft 404 ${suffix}-1`,
            status: 'draft',
        });
        const arsipPkg = await seed.createPackage(page, {
            mitra_email: SEED_USERS.mitra.email,
            nama: `Paket Arsip 404 ${suffix}-2`,
            status: 'arsip',
        });

        const draftRes = await page.goto(`/client/catalog/${draftPkg.package_id}`, {
            waitUntil: 'domcontentloaded',
        });
        expect(draftRes?.status()).toBe(404);

        const arsipRes = await page.goto(`/client/catalog/${arsipPkg.package_id}`, {
            waitUntil: 'domcontentloaded',
        });
        expect(arsipRes?.status()).toBe(404);
    });
});