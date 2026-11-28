import AxeBuilder from '@axe-core/playwright';
import type { Page } from '@playwright/test';
import { test, expect, SEED_USERS } from '../fixtures/base';
import type { SeedHelpers } from '../fixtures/seed';
import { ClientBookingPage } from '../pages/ClientBookingPage';
import { SEL, getDateOffset } from '../fixtures/selectors';

// ==========================================
// HELPER
// ==========================================

async function seedActivePackage(
    page: Page,
    seed: SeedHelpers,
    overrides: Partial<Parameters<SeedHelpers['createPackage']>[1]> = {},
) {
    const unique = `${Date.now()}_${Math.floor(Math.random() * 100_000)}`;
    return seed.createPackage(page, {
        mitra_email: SEED_USERS.mitra.email,
        nama: `Paket Wisata E2E ${unique}`,
        kategori: 'wisata_alam',
        status: 'aktif',
        durasi_hari: 2,
        durasi_malam: 1,
        harga_per_orang: 500_000,
        timezone: 'Asia/Makassar',
        days: [
            { judul: 'Hari 1: Eksplorasi Destinasi', lokasi: 'Banjarmasin' },
            { judul: 'Hari 2: Kepulangan', lokasi: 'Banjarbaru' },
        ],
        facilities: [
            { tipe: 'include', nama: 'Transportasi AC' },
            { tipe: 'exclude', nama: 'Pengeluaran Pribadi' },
        ],
        ...overrides,
    });
}

// ==========================================
// TEST SUITE
// ==========================================

test.describe('Client Booking — Create & Listing Flow', () => {
    // ==========================================
    // ACCESS CONTROL
    // ==========================================

    test.describe('Access Control', () => {
        test('guest mengakses /client/bookings diarahkan ke /login', async ({
            page,
            asGuest,
        }) => {
            await asGuest();
            await page.goto('/client/bookings');

            await expect(page).toHaveURL(/\/login$/);
        });

        test('mitra dan admin ditolak mengakses /client/bookings', async ({
            page,
            asMitra,
            asAdmin,
        }) => {
            await asMitra();
            await page.goto('/client/bookings');
            await expect(page).toHaveURL(/\/mitra\/dashboard$/);

            await asAdmin();
            await page.goto('/client/bookings');
            await expect(page).toHaveURL(/\/admin\/dashboard$/);
        });

        test('client tidak dapat membuka form booking untuk paket berstatus draft', async ({
            page,
            asClient,
            seed,
        }) => {
            await asClient();
            const draftPkg = await seedActivePackage(page, seed, {
                status: 'draft',
            });

            const response = await page.goto(
                `/client/bookings/create/${draftPkg.package_id}`,
            );

            expect(response?.status()).toBe(404);
            await expect(
                page.getByRole('heading', { name: 'Info Pemesanan' }),
            ).toBeHidden();
        });
    });

    // ==========================================
    // VALIDASI CLIENT-SIDE & SERVER (422)
    // ==========================================

    test.describe('Validasi Wizard & Backend (422)', () => {
        test('validasi step 1: tanggal kosong & jumlah peserta melebihi max_peserta paket rombongan', async ({
            page,
            asClient,
            seed,
        }) => {
            await asClient();
            const pkg = await seedActivePackage(page, seed, {
                harga_per_orang: undefined,
                harga_per_rombongan: 3_500_000,
                max_peserta: 5,
            });

            const bookingPage = new ClientBookingPage(page);
            await bookingPage.gotoCreate(pkg.package_id);

            // Submit kosong di Step 1
            await bookingPage.proceedToStep2();
            await expect(
                page.getByText('Tanggal keberangkatan wajib diisi.'),
            ).toBeVisible();

            // Jumlah peserta melebihi kapasitas rombongan (6 > 5)
            await bookingPage.fillStep1({
                tanggal: getDateOffset(5),
                jumlahPeserta: 6,
            });
            await bookingPage.proceedToStep2();
            await expect(page.getByText('Maksimal 5 orang.')).toBeVisible();
        });

        test('validasi step 2: nama peserta kurang dari 2 karakter ditolak sebelum POST', async ({
            page,
            asClient,
            seed,
        }) => {
            await asClient();
            const pkg = await seedActivePackage(page, seed);

            const bookingPage = new ClientBookingPage(page);
            await bookingPage.gotoCreate(pkg.package_id);

            await bookingPage.fillStep1({
                tanggal: getDateOffset(4),
                jumlahPeserta: 1,
            });
            await bookingPage.proceedToStep2();

            await bookingPage.fillParticipants([{ nama: 'A' }]);
            await SEL.CLIENT.booking.submit(page).click();

            await expect(
                page.getByText('Nama peserta minimal 2 karakter.'),
            ).toBeVisible();
        });

        test('validasi backend H+1: memilih tanggal H-0 mengembalikan wizard ke Step 1 dengan error 422', async ({
            page,
            asClient,
            seed,
        }) => {
            await asClient();
            const pkg = await seedActivePackage(page, seed, {
                harga_per_orang: 250_000,
            });

            const bookingPage = new ClientBookingPage(page);
            await bookingPage.gotoCreate(pkg.package_id);

            await bookingPage.fillStep1({
                tanggal: getDateOffset(0),
                jumlahPeserta: 1,
            });
            await bookingPage.proceedToStep2();

            await bookingPage.fillParticipants([{ nama: 'Budi Santoso' }]);
            await bookingPage.submitBooking(pkg.package_id);

            await expect(
                page.getByRole('heading', { name: 'Info Pemesanan' }),
            ).toBeVisible();
            await expect(SEL.CLIENT.booking.errorAlert(page)).toBeVisible();
        });
    });

    // ==========================================
    // MUTATION — HAPPY PATH & LISTING FILTER
    // ==========================================

    test.describe('Mutation — Pembuatan & Pencarian Booking', () => {
        test('happy path: client membuat booking 2 peserta -> redirect detail pending_payment & notifikasi mitra', async ({
            page,
            asClient,
            seed,
        }) => {
            await asClient();
            const pkg = await seedActivePackage(page, seed, {
                harga_per_orang: 500_000,
            });

            const bookingPage = new ClientBookingPage(page);
            await bookingPage.gotoCreate(pkg.package_id);

            await bookingPage.fillStep1({
                tanggal: getDateOffset(5),
                jumlahPeserta: 2,
                catatan: 'Penjemputan di Bandara Syamsudin Noor',
            });
            await bookingPage.proceedToStep2();

            // Verifikasi kalkulasi total (2 x Rp 500.000 = Rp 1.000.000)
            await expect(
                page.getByRole('heading', { name: 'Data Peserta' }),
            ).toBeVisible();
            await expect(page.getByText(/Rp\s*1\.000\.000/)).toBeVisible();

            await bookingPage.fillParticipants([
                {
                    nama: SEED_USERS.client.name,
                    umur: 25,
                    no_hp: '081234567890',
                },
                { nama: 'Siti Aminah', umur: 24, catatan: 'Alergi udang' },
            ]);

            await bookingPage.submitBooking(pkg.package_id);

            await expect(page).toHaveURL(
                /\/client\/bookings\/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/,
            );
            await expect(
                SEL.LAYOUT.toastRegion(page).getByText(
                    /Booking berhasil dibuat/i,
                ),
            ).toBeVisible();
            await expect(page.getByText('Menunggu Pembayaran')).toBeVisible();
            await expect(
                page.getByRole('main').getByText(SEED_USERS.client.name),
            ).toBeVisible();
            await expect(
                page.getByRole('main').getByText('Siti Aminah'),
            ).toBeVisible();

            // Verifikasi notifikasi masuk ke Mitra
            const notif = await seed.latestNotification(
                page,
                SEED_USERS.mitra.email,
            );
            expect(notif.notification).not.toBeNull();
        });

        test('index pesanan: filter status tab dan pencarian berdasarkan kode booking', async ({
            page,
            asClient,
            seed,
        }) => {
            await asClient();
            const pkg = await seedActivePackage(page, seed);

            const bookingPending = await seed.createBooking(page, {
                client_email: SEED_USERS.client.email,
                package_kode: pkg.kode,
                status: 'pending_payment',
            });
            const bookingConfirmed = await seed.createBooking(page, {
                client_email: SEED_USERS.client.email,
                package_kode: pkg.kode,
                status: 'confirmed',
            });

            const bookingPage = new ClientBookingPage(page);
            await bookingPage.gotoIndex();

            await expect(
                SEL.CLIENT.booking.bookingCard(page, bookingPending.kode),
            ).toBeVisible();
            await expect(
                SEL.CLIENT.booking.bookingCard(page, bookingConfirmed.kode),
            ).toBeVisible();

            // Search berdasarkan kode booking
            await SEL.CLIENT.booking
                .searchInput(page)
                .fill(bookingPending.kode);

            await expect(
                SEL.CLIENT.booking.bookingCard(page, bookingPending.kode),
            ).toBeVisible();
            await expect(
                SEL.CLIENT.booking.bookingCard(page, bookingConfirmed.kode),
            ).toBeHidden();

            // Pencarian tidak cocok -> empty state
            await SEL.CLIENT.booking
                .searchInput(page)
                .fill('BKG-TIDAK-ADA-999');
            await expect(page.getByText(/Tidak ada pesanan/i)).toBeVisible();

            await SEL.CLIENT.booking.clearFilterBtn(page).click();
            await expect(
                SEL.CLIENT.booking.bookingCard(page, bookingConfirmed.kode),
            ).toBeVisible();
        });
    });

    // ==========================================
    // COMPATIBILITY & ACCESSIBILITY
    // ==========================================

    test.describe('Compatibility & Accessibility', () => {
        test('form booking lolos audit WCAG 2.0 AA', async ({
            page,
            asClient,
            seed,
        }) => {
            await asClient();
            const pkg = await seedActivePackage(page, seed);

            const bookingPage = new ClientBookingPage(page);
            await bookingPage.gotoCreate(pkg.package_id);

            const results = await new AxeBuilder({ page })
                .withTags(['wcag2a', 'wcag2aa'])
                .disableRules(['region', 'landmark-one-main'])
                .analyze();

            const blockingViolations = results.violations.filter(
                (v) => v.impact === 'critical' || v.impact === 'serious',
            );
            expect(blockingViolations).toEqual([]);
        });

        test('wizard booking berfungsi pada viewport mobile (375x812) tanpa horizontal overflow', async ({
            page,
            asClient,
            seed,
        }) => {
            await page.setViewportSize({ width: 375, height: 812 });
            await asClient();
            const pkg = await seedActivePackage(page, seed);

            const bookingPage = new ClientBookingPage(page);
            await bookingPage.gotoCreate(pkg.package_id);

            const hasHorizontalOverflow = await page.evaluate(
                () =>
                    document.documentElement.scrollWidth >
                    document.documentElement.clientWidth,
            );
            expect(hasHorizontalOverflow).toBe(false);
        });
    });
});
