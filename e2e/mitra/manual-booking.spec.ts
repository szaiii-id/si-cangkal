import type { Page } from '@playwright/test';
import { test, expect, SEED_USERS } from '../fixtures/base';
import type { SeedHelpers } from '../fixtures/seed';
import { MitraBookingPage } from '../pages/MitraBookingPage';
import { getDateOffset } from '../fixtures/selectors';

// ==========================================
// HELPER
// ==========================================

async function dismissNotificationPrompt(page: Page): Promise<void> {
    await page.addLocatorHandler(
        page.getByRole('dialog', { name: /Aktifkan Notifikasi/i }),
        async () => {
            await page.getByRole('button', { name: /Nanti Saja/i }).click();
        },
    );
}

async function seedMitraPackage(
    page: Page,
    seed: SeedHelpers,
    overrides: Partial<Parameters<SeedHelpers['createPackage']>[1]> = {},
) {
    const unique = `${Date.now()}_${Math.floor(Math.random() * 100_000)}`;
    const nama = overrides.nama ?? `Paket Manual E2E ${unique}`;

    const pkg = await seed.createPackage(page, {
        mitra_email: SEED_USERS.mitra.email,
        nama,
        kategori: 'wisata_alam',
        status: 'aktif',
        durasi_hari: 2,
        durasi_malam: 1,
        harga_per_orang: 400_000,
        timezone: 'Asia/Makassar',
        days: [
            { judul: 'Hari 1: Eksplorasi', lokasi: 'Banjarmasin' },
            { judul: 'Hari 2: Kepulangan', lokasi: 'Banjarbaru' },
        ],
        facilities: [
            { tipe: 'include', nama: 'Transportasi AC' },
            { tipe: 'exclude', nama: 'Belanja Pribadi' },
        ],
        ...overrides,
    });

    return { ...pkg, nama };
}

// ==========================================
// TEST SUITE
// ==========================================

test.describe('Mitra — Pesanan Manual (Manual Booking)', () => {
    test.beforeEach(async ({ page }) => {
        await dismissNotificationPrompt(page);
    });

    // ==========================================
    // ACCESS CONTROL & AUTHORIZATION
    // ==========================================

    test.describe('Access Control', () => {
        test('guest, client, dan admin ditolak mengakses halaman buat pesanan manual', async ({
            page,
            asGuest,
            asClient,
            asAdmin,
            seed,
        }) => {
            const pkg = await seedMitraPackage(page, seed);
            const targetUrl = `/mitra/packages/${pkg.package_id}/manual-booking`;

            // Guest -> /login
            await asGuest();
            await page.goto(targetUrl);
            await expect(page).toHaveURL(/\/login$/);

            // Client -> /client/dashboard
            await asClient();
            await page.goto(targetUrl);
            await expect(page).toHaveURL(/\/client\/dashboard$/);

            // Admin -> /admin/dashboard
            await asAdmin();
            await page.goto(targetUrl);
            await expect(page).toHaveURL(/\/admin\/dashboard$/);
        });

        test('mitra lain ditolak (403) membuka form pesanan manual paket bukan miliknya', async ({
            page,
            seed,
        }) => {
            const pkg = await seedMitraPackage(page, seed);

            const otherMitraEmail = `mitra-other-manual-${Date.now()}@test.com`;
            await seed.createUser(page, {
                email: otherMitraEmail,
                name: 'Mitra Lain Manual',
                role: 'mitra',
                password: 'password123',
                with_bank_account: true,
            });

            await seed.login(page, otherMitraEmail, 'password123');
            const response = await page.goto(
                `/mitra/packages/${pkg.package_id}/manual-booking`,
            );

            expect(response?.status()).toBe(403);
        });
    });

    // ==========================================
    // VALIDASI FORM & ATURAN BISNIS (422)
    // ==========================================

    test.describe('Validasi Form & Backend (422)', () => {
        test('menampilkan error validasi jika tanggal keberangkatan sebelum hari ini (H-0)', async ({
            page,
            asMitra,
            seed,
        }) => {
            await asMitra();
            const pkg = await seedMitraPackage(page, seed);

            await page.goto(`/mitra/packages/${pkg.package_id}/manual-booking`);
            await page.waitForLoadState('networkidle');

            await page.locator('#nama_pelanggan').fill('Pelanggan Offline');
            await page
                .locator('#email_pelanggan')
                .fill(`offline-past-${Date.now()}@test.com`);
            await page
                .locator('#tanggal_keberangkatan')
                .fill(getDateOffset(-1));
            await page.locator('#jumlah_peserta').fill('1');
            await page.locator('#participant-nama-0').fill('Peserta Kemarin');

            const [response] = await Promise.all([
                page.waitForResponse(
                    (res) =>
                        res
                            .url()
                            .includes(
                                `/mitra/packages/${pkg.package_id}/manual-booking`,
                            ) && res.request().method() === 'POST',
                    { timeout: 20_000 },
                ),
                page
                    .getByRole('button', { name: /Buat Pesanan Manual/i })
                    .click(),
            ]);

            expect([422, 302]).toContain(response.status());
            await expect(
                page.getByText(
                    'Tanggal keberangkatan tidak boleh sebelum hari ini (timezone paket).',
                ),
            ).toBeVisible();
        });

        test('menampilkan error validasi jika jumlah peserta melebihi max_peserta paket rombongan', async ({
            page,
            asMitra,
            seed,
        }) => {
            await asMitra();
            const pkg = await seedMitraPackage(page, seed, {
                harga_per_orang: undefined,
                harga_per_rombongan: 2_500_000,
                max_peserta: 3,
            });

            await page.goto(`/mitra/packages/${pkg.package_id}/manual-booking`);
            await page.waitForLoadState('networkidle');

            await page.locator('#nama_pelanggan').fill('Ketua Rombongan');
            await page
                .locator('#email_pelanggan')
                .fill(`rombongan-over-${Date.now()}@test.com`);
            await page.locator('#tanggal_keberangkatan').fill(getDateOffset(5));

            // Isi 4 peserta (> max_peserta = 3)
            await page.locator('#jumlah_peserta').fill('4');
            for (let i = 0; i < 4; i++) {
                await page
                    .locator(`#participant-nama-${i}`)
                    .fill(`Peserta Rombongan ${i + 1}`);
            }

            await Promise.all([
                page.waitForResponse(
                    (res) =>
                        res
                            .url()
                            .includes(
                                `/mitra/packages/${pkg.package_id}/manual-booking`,
                            ) && res.request().method() === 'POST',
                    { timeout: 20_000 },
                ),
                page
                    .getByRole('button', { name: /Buat Pesanan Manual/i })
                    .click(),
            ]);

            await expect(
                page.getByText(
                    'Jumlah peserta melebihi batas maksimal untuk paket ini.',
                ),
            ).toBeVisible();
        });

        test('tidak dapat membuat pesanan manual pada paket berstatus draft', async ({
            page,
            asMitra,
            seed,
        }) => {
            await asMitra();
            const draftPkg = await seedMitraPackage(page, seed, {
                status: 'draft',
            });

            await page.goto(
                `/mitra/packages/${draftPkg.package_id}/manual-booking`,
            );
            await page.waitForLoadState('networkidle');

            await page.locator('#nama_pelanggan').fill('Pelanggan Draft');
            await page
                .locator('#email_pelanggan')
                .fill(`draft-manual-${Date.now()}@test.com`);
            await page.locator('#tanggal_keberangkatan').fill(getDateOffset(5));
            await page.locator('#jumlah_peserta').fill('1');
            await page.locator('#participant-nama-0').fill('Peserta Draft');

            await Promise.all([
                page.waitForResponse(
                    (res) =>
                        res
                            .url()
                            .includes(
                                `/mitra/packages/${draftPkg.package_id}/manual-booking`,
                            ) && res.request().method() === 'POST',
                    { timeout: 20_000 },
                ),
                page
                    .getByRole('button', { name: /Buat Pesanan Manual/i })
                    .click(),
            ]);

            // Tetap berada di form manual booking (tidak redirect ke detail booking)
            await expect(page).toHaveURL(
                new RegExp(
                    `/mitra/packages/${draftPkg.package_id}/manual-booking$`,
                ),
            );
        });
    });

    // ==========================================
    // MUTATION — HAPPY PATH (NEW & EXISTING CLIENT)
    // ==========================================

    test.describe('Mutation — Pembuatan Pesanan Manual', () => {
        test('mitra membuat pesanan manual untuk pelanggan baru → akun client otomatis dibuat & redirect ke detail booking', async ({
            page,
            asMitra,
            seed,
        }) => {
            await asMitra();
            const pkg = await seedMitraPackage(page, seed, {
                harga_per_orang: 450_000,
            });

            const unique = `${Date.now()}_${Math.floor(Math.random() * 1000)}`;
            const newCustomerName = `Budi Offline ${unique}`;
            const newCustomerEmail = `budi.offline.${unique}@test.com`;

            await page.goto(`/mitra/packages/${pkg.package_id}/manual-booking`);
            await page.waitForLoadState('networkidle');

            await expect(
                page.getByRole('heading', { name: pkg.nama }),
            ).toBeVisible();

            // Isi data pemesan
            await page.locator('#nama_pelanggan').fill(newCustomerName);
            await page.locator('#email_pelanggan').fill(newCustomerEmail);
            await page.locator('#no_hp_pelanggan').fill('081298765432');
            await page.locator('#tanggal_keberangkatan').fill(getDateOffset(6));
            await page.locator('#jumlah_peserta').fill('2');
            await page
                .locator('#catatan')
                .fill('Pemesanan via WhatsApp, bayar transfer langsung');

            // Verifikasi kalkulasi ringkasan (2 x Rp 450.000 = Rp 900.000)
            await expect(page.getByText(/Rp\s*900\.000/)).toBeVisible();

            // Isi data 2 peserta yang otomatis di-render oleh $effect
            await page.locator('#participant-nama-0').fill(newCustomerName);
            await page.locator('#participant-umur-0').fill('30');
            await page.locator('#participant-hp-0').fill('081298765432');

            await page.locator('#participant-nama-1').fill('Ani Offline');
            await page.locator('#participant-umur-1').fill('28');
            await page.locator('#participant-catatan-1').fill('Vegetarian');

            await Promise.all([
                page.waitForURL(
                    /\/mitra\/bookings\/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/,
                    {
                        timeout: 20_000,
                    },
                ),
                page
                    .getByRole('button', { name: /Buat Pesanan Manual/i })
                    .click(),
            ]);

            const mitraBookingPage = new MitraBookingPage(page);
            await mitraBookingPage.expectFlashStatus(
                /Pesanan manual berhasil dibuat/i,
            );
            await mitraBookingPage.expectStatusBadge(
                /Menunggu Pembayaran|Pending/i,
            );

            // Pastikan data pemesan & kedua peserta tampil di halaman detail booking mitra
            const main = page.getByRole('main');
            await expect(main.getByText(newCustomerName).first()).toBeVisible();
            await expect(main.getByText(newCustomerEmail)).toBeVisible();
            await expect(main.getByText('Ani Offline')).toBeVisible();
        });

        test('mitra membuat pesanan manual untuk client yang sudah terdaftar (existing email) tanpa duplikasi akun', async ({
            page,
            asMitra,
            asClient,
            seed,
        }) => {
            await asMitra();
            const pkg = await seedMitraPackage(page, seed, {
                harga_per_orang: 350_000,
            });

            await page.goto(`/mitra/packages/${pkg.package_id}/manual-booking`);
            await page.waitForLoadState('networkidle');

            // Gunakan email SEED_USERS.client yang sudah ada di database
            await page.locator('#nama_pelanggan').fill(SEED_USERS.client.name);
            await page
                .locator('#email_pelanggan')
                .fill(SEED_USERS.client.email);
            await page.locator('#tanggal_keberangkatan').fill(getDateOffset(7));
            await page.locator('#jumlah_peserta').fill('1');
            await page
                .locator('#participant-nama-0')
                .fill(SEED_USERS.client.name);

            await Promise.all([
                page.waitForURL(
                    /\/mitra\/bookings\/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/,
                    {
                        timeout: 20_000,
                    },
                ),
                page
                    .getByRole('button', { name: /Buat Pesanan Manual/i })
                    .click(),
            ]);

            const match = page
                .url()
                .match(
                    /\/mitra\/bookings\/([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})$/,
                );
            expect(match).not.toBeNull();
            const createdBookingId = match![1];

            // Verifikasi booking tersebut muncul di akun client existing
            await asClient();
            await page.goto(`/client/bookings/${createdBookingId}`);
            await expect(page.getByText(pkg.nama)).toBeVisible();
            await expect(page.getByText('Menunggu Pembayaran')).toBeVisible();
        });
    });
});
