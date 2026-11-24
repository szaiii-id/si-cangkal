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

test.describe('Mitra — Dashboard', () => {
    // ==========================================
    // 1. ACCESS CONTROL
    // ==========================================

    test.describe('Access Control', () => {
        test('guest, client, dan admin ditolak mengakses /mitra/dashboard', async ({
            page,
            asGuest,
            asClient,
            asAdmin,
        }) => {
            await dismissNotificationPrompt(page);

            await asGuest();
            await page.goto('/mitra/dashboard');
            await expect(page).toHaveURL(/\/login$/);

            await asClient();
            await page.goto('/mitra/dashboard');
            await expect(page).toHaveURL(/\/client\/dashboard$/);

            await asAdmin();
            await page.goto('/mitra/dashboard');
            await expect(page).toHaveURL(/\/admin\/dashboard$/);
        });
    });

    // ==========================================
    // 2. STATISTIK & RECENT BOOKINGS MITRA
    // ==========================================

    test.describe('Statistik & Booking Terbaru Mitra', () => {
        test('menampilkan sapaan mitra, kartu statistik, menu navigasi, dan booking terbaru', async ({
            page,
            asMitra,
            seed,
        }) => {
            await dismissNotificationPrompt(page);
            await asMitra();

            const runId = Date.now();
            const packageNama = `Paket Dash Mitra ${runId}`;

            const pkg = await seed.createPackage(page, {
                mitra_email: SEED_USERS.mitra.email,
                nama: packageNama,
                status: 'aktif',
                durasi_hari: 1,
                durasi_malam: 0,
                harga_per_orang: 250_000,
                days: [{ judul: 'Hari 1: Wisata', lokasi: 'Banjarmasin' }],
                facilities: [{ tipe: 'include', nama: 'Snack' }],
            });

            await seed.createBooking(page, {
                client_email: SEED_USERS.client.email,
                package_kode: pkg.kode,
                status: 'confirmed',
                tanggal_keberangkatan: getDateOffset(6),
                jumlah_peserta: 2,
                participants: [
                    { nama: 'Peserta Dash Mitra 1', umur: 24 },
                    { nama: 'Peserta Dash Mitra 2', umur: 25 },
                ],
            });

            await page.goto('/mitra/dashboard');
            await page.waitForLoadState('networkidle');

            const mainArea = page.getByRole('main');

            // 1. Heading Sapaan & Deskripsi
            await expect(
                mainArea.getByRole('heading', {
                    name: new RegExp(`Halo, ${SEED_USERS.mitra.name}`),
                }),
            ).toBeVisible();
            await expect(
                mainArea.getByText(
                    'Ini ringkasan performa bisnis wisata Anda.',
                ),
            ).toBeVisible();

            // 2. Kartu Statistik Utama (StatsCards)
            await expect(
                mainArea.getByText(/Pendapatan/i).first(),
            ).toBeVisible();
            await expect(mainArea.getByText(/Paket/i).first()).toBeVisible();
            await expect(mainArea.getByText(/Trip/i).first()).toBeVisible();
            await expect(mainArea.getByText(/Booking/i).first()).toBeVisible();

            // 3. Booking Terbaru (RecentBookingsList)
            await expect(mainArea.getByText(packageNama).first()).toBeVisible();
            await expect(
                mainArea.getByText(SEED_USERS.client.name).first(),
            ).toBeVisible();
        });
    });
});
