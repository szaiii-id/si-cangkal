import type { Page } from '@playwright/test';
import { test, expect, SEED_USERS } from '../fixtures/base';
import type { SeedHelpers } from '../fixtures/seed';
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

async function seedMitraTrip(
    page: Page,
    seed: SeedHelpers,
    options: {
        packageNama?: string;
        tanggalKeberangkatan?: string;
        jumlahPeserta?: number;
        participants?: Array<{ nama: string; umur?: number; no_hp?: string }>;
    } = {},
) {
    const unique = `${Date.now()}_${Math.floor(Math.random() * 100_000)}`;
    const packageNama = options.packageNama ?? `Paket Trip E2E ${unique}`;

    const pkg = await seed.createPackage(page, {
        mitra_email: SEED_USERS.mitra.email,
        nama: packageNama,
        kategori: 'wisata_alam',
        status: 'aktif',
        durasi_hari: 2,
        durasi_malam: 1,
        harga_per_orang: 500_000,
        timezone: 'Asia/Makassar',
        days: [
            {
                judul: 'Hari 1: Susur Sungai Lok Baintan',
                lokasi: 'Banjarmasin',
            },
            {
                judul: 'Hari 2: Pendakian Bukit Matang Kaladan',
                lokasi: 'Banjarbaru',
            },
        ],
        facilities: [
            { tipe: 'include', nama: 'Perahu Klotok & Guide' },
            { tipe: 'exclude', nama: 'Pengeluaran Pribadi' },
        ],
    });

    const booking = await seed.createBooking(page, {
        client_email: SEED_USERS.client.email,
        package_kode: pkg.kode,
        status: 'confirmed',
        tanggal_keberangkatan: options.tanggalKeberangkatan ?? getDateOffset(7),
        jumlah_peserta: options.jumlahPeserta ?? 2,
        participants: options.participants ?? [
            { nama: 'Peserta Trip Satu', umur: 27 },
            { nama: 'Peserta Trip Dua', umur: 25 },
        ],
    });

    const trip = await seed.createTrip(page, booking.kode);

    return {
        packageNama,
        pkg,
        booking,
        trip,
    };
}

// ==========================================
// TEST SUITE
// ==========================================

test.describe('Mitra — Jadwal Perjalanan (Trip Index & Show)', () => {
    test.beforeEach(async ({ page }) => {
        await dismissNotificationPrompt(page);
    });

    // ==========================================
    // ACCESS CONTROL & ISOLASI ANTAR MITRA
    // ==========================================

    test.describe('Access Control', () => {
        test('guest, client, dan admin ditolak mengakses /mitra/trips', async ({
            page,
            asGuest,
            asClient,
            asAdmin,
        }) => {
            await asGuest();
            await page.goto('/mitra/trips');
            await expect(page).toHaveURL(/\/login$/);

            await asClient();
            await page.goto('/mitra/trips');
            await expect(page).toHaveURL(/\/client\/dashboard$/);

            await asAdmin();
            await page.goto('/mitra/trips');
            await expect(page).toHaveURL(/\/admin\/dashboard$/);
        });

        test('mitra lain ditolak (403) mengakses detail trip bukan miliknya', async ({
            page,
            seed,
        }) => {
            const { trip } = await seedMitraTrip(page, seed);

            const otherMitraEmail = `mitra-other-trip-${Date.now()}@test.com`;
            await seed.createUser(page, {
                email: otherMitraEmail,
                name: 'Mitra Lain Trip',
                role: 'mitra',
                password: 'password123',
                with_bank_account: true,
            });

            await seed.login(page, otherMitraEmail, 'password123');
            const response = await page.goto(`/mitra/trips/${trip.trip_id}`);

            expect(response?.status()).toBe(403);
        });
    });

    // ==========================================
    // LISTING, FILTER & PENCARIAN (/mitra/trips)
    // ==========================================

    test.describe('Listing & Filter Jadwal Perjalanan', () => {
        test('menampilkan daftar trip milik mitra, pencarian berdasarkan kode trip, dan reset filter', async ({
            page,
            asMitra,
            seed,
        }) => {
            await asMitra();

            const first = await seedMitraTrip(page, seed, {
                packageNama: `Wisata Loksado ${Date.now()}`,
                tanggalKeberangkatan: getDateOffset(60),
            });
            const second = await seedMitraTrip(page, seed, {
                packageNama: `Wisata Pulau Kembang ${Date.now()}`,
                tanggalKeberangkatan: getDateOffset(61),
            });

            await page.goto('/mitra/trips');
            await page.waitForLoadState('networkidle');

            await expect(
                page
                    .getByRole('main')
                    .getByRole('heading', { name: 'Jadwal Perjalanan' }),
            ).toBeVisible();
            await expect(page.getByText(first.trip.kode)).toBeVisible();
            await expect(page.getByText(second.trip.kode)).toBeVisible();

            // Cari berdasarkan kode trip pertama
            const searchInput = page.getByPlaceholder(
                'Cari kode trip atau nama paket...',
            );
            await searchInput.fill(first.trip.kode);
            await expect(page).toHaveURL(
                new RegExp(`search=${first.trip.kode}`),
            );

            await expect(page.getByText(first.trip.kode)).toBeVisible();
            await expect(page.getByText(second.trip.kode)).toBeHidden();

            // Cari kode yang tidak ada -> empty state + tombol clear filter
            await searchInput.fill('TRP-TIDAK-ADA-9999');
            await expect(
                page.getByText('Tidak ada jadwal ditemukan'),
            ).toBeVisible();

            await page
                .getByText('Tidak ada jadwal ditemukan')
                .locator('..')
                .getByRole('button')
                .click();
            await expect(page.getByText(second.trip.kode)).toBeVisible();
        });

        test('filter tab status Terjadwal menampilkan trip scheduled', async ({
            page,
            asMitra,
            seed,
        }) => {
            await asMitra();

            const { trip } = await seedMitraTrip(page, seed, {
                tanggalKeberangkatan: getDateOffset(62),
            });

            await page.goto('/mitra/trips');
            await page.waitForLoadState('networkidle');

            const scheduledTab = page.getByRole('button', {
                name: /Terjadwal/i,
            });
            await expect(scheduledTab).toBeVisible();
            await scheduledTab.click();

            await expect(page).toHaveURL(/status=scheduled/);
            await expect(page.getByText(trip.kode)).toBeVisible();
        });
    });

    // ==========================================
    // DETAIL PERJALANAN (/mitra/trips/{id})
    // ==========================================

    test.describe('Detail Perjalanan', () => {
        test('menampilkan header, snapshot itinerary hari, fasilitas, info pelanggan, dan tautan manifest', async ({
            page,
            asMitra,
            seed,
        }) => {
            await asMitra();

            const { packageNama, trip } = await seedMitraTrip(page, seed, {
                jumlahPeserta: 2,
                tanggalKeberangkatan: getDateOffset(63),
            });

            await page.goto('/mitra/trips');
            await page.waitForLoadState('networkidle');

            // Filter berdasarkan kode trip agar pasti tampil di halaman 1 meski data akumulatif banyak
            await page
                .getByPlaceholder('Cari kode trip atau nama paket...')
                .fill(trip.kode);
            await expect(page).toHaveURL(new RegExp(`search=${trip.kode}`));
            await page.waitForLoadState('networkidle');

            const tripCardLink = page
                .getByRole('link')
                .filter({ hasText: trip.kode })
                .first();
            await expect(tripCardLink).toBeVisible();

            await Promise.all([
                page.waitForURL(new RegExp(`/mitra/trips/${trip.trip_id}$`), {
                    timeout: 20_000,
                }),
                tripCardLink.click(),
            ]);

            // Header Card
            await expect(
                page.getByRole('heading', { name: packageNama, level: 1 }),
            ).toBeVisible();
            await expect(page.getByText(trip.kode).first()).toBeVisible();
            await expect(page.getByText('2 orang')).toBeVisible();

            // Info Pelanggan
            await expect(
                page.getByText(SEED_USERS.client.name).first(),
            ).toBeVisible();
            await expect(
                page.getByText(SEED_USERS.client.email).first(),
            ).toBeVisible();

            // Rencana Perjalanan (TripItineraryList)
            await expect(
                page.getByRole('heading', { name: 'Rencana Perjalanan' }),
            ).toBeVisible();
            await expect(
                page.getByText('Hari 1: Susur Sungai Lok Baintan'),
            ).toBeVisible();
            await expect(
                page.getByText('Hari 2: Pendakian Bukit Matang Kaladan'),
            ).toBeVisible();

            // Fasilitas (TripFacilitiesCard)
            await expect(
                page.getByRole('heading', { name: 'Fasilitas' }),
            ).toBeVisible();
            await expect(page.getByText('Perahu Klotok & Guide')).toBeVisible();
            await expect(page.getByText('Pengeluaran Pribadi')).toBeVisible();

            // Tombol Cetak Daftar Peserta (Manifest)
            const manifestLink = page.getByRole('link', {
                name: /Cetak daftar peserta/i,
            });
            await expect(manifestLink).toBeVisible();
            await expect(manifestLink).toHaveAttribute(
                'href',
                `/mitra/trips/${trip.trip_id}/manifest`,
            );
        });
    });
});
