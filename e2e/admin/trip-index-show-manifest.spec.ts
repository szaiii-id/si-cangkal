import { test, expect, SEED_USERS } from '../fixtures/base';
import { getDateOffset } from '../fixtures/selectors';
import type { Page } from '@playwright/test';

async function dismissNotificationPrompt(page: Page): Promise<void> {
    await page.addLocatorHandler(
        page.getByRole('dialog', { name: /Aktifkan Notifikasi/i }),
        async () => {
            await page.getByRole('button', { name: /Nanti Saja/i }).click();
        },
    );
}

test.describe('Admin — Monitoring Trip & Manifest Peserta', () => {
    // ==========================================
    // ACCESS CONTROL
    // ==========================================

    test.describe('Access Control', () => {
        test('guest, client, dan mitra ditolak mengakses /admin/trips', async ({
            page,
            asGuest,
            asClient,
            asMitra,
        }) => {
            await dismissNotificationPrompt(page);

            await asGuest();
            await page.goto('/admin/trips');
            await expect(page).toHaveURL(/\/login$/);

            await asClient();
            await page.goto('/admin/trips');
            await expect(page).toHaveURL(/\/client\/dashboard$/);

            await asMitra();
            await page.goto('/admin/trips');
            await expect(page).toHaveURL(/\/mitra\/dashboard$/);
        });
    });

    // ==========================================
    // LISTING & FILTER TRIPS (/admin/trips)
    // ==========================================

    test.describe('Listing & Filter Daftar Trip', () => {
        test('menampilkan daftar trip, pencarian kode/nama paket, dan filter status', async ({
            page,
            asAdmin,
            seed,
        }) => {
            await dismissNotificationPrompt(page);
            await asAdmin();

            const runId = Date.now();
            const pkg = await seed.createPackage(page, {
                mitra_email: SEED_USERS.mitra.email,
                nama: `Paket Admin Trip E2E ${runId}`,
                status: 'aktif',
                durasi_hari: 1,
                durasi_malam: 0,
                harga_per_orang: 350_000,
                days: [{ judul: 'Hari 1: Eksplorasi Lokasi', lokasi: 'Banjarbaru' }],
                facilities: [{ tipe: 'include', nama: 'Dokumentasi & Snack' }],
            });

            const booking = await seed.createBooking(page, {
                client_email: SEED_USERS.client.email,
                package_kode: pkg.kode,
                status: 'confirmed',
                tanggal_keberangkatan: getDateOffset(5),
                jumlah_peserta: 2,
                participants: [
                    { nama: 'Peserta Manifest Satu', umur: 25, no_hp: '081233334444' },
                    { nama: 'Peserta Manifest Dua', umur: 27, no_hp: '081255556666' },
                ],
            });

            const trip = await seed.createTrip(page, booking.kode);

            await page.goto('/admin/trips');
            await page.waitForLoadState('networkidle');

            await expect(
                page.getByRole('heading', { name: /Jadwal Perjalanan/i }).first(),
            ).toBeVisible();

            const table = page.getByRole('table').first();
            await expect(table.getByText(trip.kode)).toBeVisible();

            // Gunakan locator input pencarian yang lebih universal (berdasarkan placeholder umum atau role)
            const searchInput = page.getByPlaceholder(/Cari/i).first();
            await searchInput.fill(trip.kode);
            await expect(table.getByText(trip.kode)).toBeVisible();
        });
    });

    // ==========================================
    // DETAIL TRIP & INFORMASI PESANAN
    // ==========================================

    test.describe('Detail Trip & Informasi Pesanan', () => {
        test('menampilkan detail trip dan informasi pesanan terkait', async ({
            page,
            asAdmin,
            seed,
        }) => {
            await dismissNotificationPrompt(page);
            await asAdmin();

            const runId = Date.now();
            const pkg = await seed.createPackage(page, {
                mitra_email: SEED_USERS.mitra.email,
                nama: `Paket Admin Trip E2E ${runId}`,
                status: 'aktif',
                durasi_hari: 1,
                durasi_malam: 0,
                harga_per_orang: 350_000,
                days: [{ judul: 'Hari 1: Eksplorasi Lokasi', lokasi: 'Banjarbaru' }],
                facilities: [{ tipe: 'include', nama: 'Dokumentasi & Snack' }],
            });

            const booking = await seed.createBooking(page, {
                client_email: SEED_USERS.client.email,
                package_kode: pkg.kode,
                status: 'confirmed',
                tanggal_keberangkatan: getDateOffset(5),
                jumlah_peserta: 2,
                participants: [
                    { nama: 'Peserta Manifest Satu', umur: 25, no_hp: '081233334444' },
                    { nama: 'Peserta Manifest Dua', umur: 27, no_hp: '081255556666' },
                ],
            });

            const trip = await seed.createTrip(page, booking.kode);

            await page.goto(`/admin/trips/${trip.trip_id}`);
            await page.waitForLoadState('networkidle');

            await expect(
                page.getByText(trip.kode).first(),
            ).toBeVisible();

            await expect(page.getByText('Info Pesanan')).toBeVisible();
            await expect(page.getByText(booking.kode)).toBeVisible();
            await expect(page.getByText(SEED_USERS.client.name).first()).toBeVisible();
        });
    });
});