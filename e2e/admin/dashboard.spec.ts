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

test.describe('Admin — Dashboard', () => {
    // ==========================================
    // 1. ACCESS CONTROL
    // ==========================================

    test.describe('Access Control', () => {
        test('guest, client, dan mitra ditolak mengakses /admin/dashboard', async ({
            page,
            asGuest,
            asClient,
            asMitra,
        }) => {
            await dismissNotificationPrompt(page);

            await asGuest();
            await page.goto('/admin/dashboard');
            await expect(page).toHaveURL(/\/login$/);

            await asClient();
            await page.goto('/admin/dashboard');
            await expect(page).toHaveURL(/\/client\/dashboard$/);

            await asMitra();
            await page.goto('/admin/dashboard');
            await expect(page).toHaveURL(/\/mitra\/dashboard$/);
        });
    });

    // ==========================================
    // 2. STATISTIK, GRAFIK & BOOKING TERBARU
    // ==========================================

    test.describe('Statistik, Grafik & Booking Terbaru', () => {
        test('menampilkan sapaan admin, kartu statistik, grafik tren pendapatan, dan daftar booking terbaru', async ({
            page,
            asAdmin,
            seed,
        }) => {
            await dismissNotificationPrompt(page);
            await asAdmin();

            const runId = Date.now();
            const packageNama = `Paket Dash Admin ${runId}`;

            const pkg = await seed.createPackage(page, {
                mitra_email: SEED_USERS.mitra.email,
                nama: packageNama,
                status: 'aktif',
                durasi_hari: 1,
                durasi_malam: 0,
                harga_per_orang: 300_000,
                days: [{ judul: 'Hari 1: Wisata', lokasi: 'Banjarmasin' }],
                facilities: [{ tipe: 'include', nama: 'Transportasi' }],
            });

            await seed.createBooking(page, {
                client_email: SEED_USERS.client.email,
                package_kode: pkg.kode,
                status: 'confirmed',
                tanggal_keberangkatan: getDateOffset(6),
                jumlah_peserta: 1,
                participants: [{ nama: 'Peserta Dash Admin', umur: 25 }],
            });

            await page.goto('/admin/dashboard');
            await page.waitForLoadState('networkidle');

            const mainArea = page.getByRole('main');

            // 1. Heading Sapaan & Tautan Laporan
            await expect(
                mainArea.getByRole('heading', {
                    name: new RegExp(`Halo, ${SEED_USERS.admin.name}`),
                }),
            ).toBeVisible();
            await expect(
                mainArea.getByRole('link', { name: 'Lihat Laporan' }),
            ).toBeVisible();

            // 2. Kartu Statistik Utama (StatsGrid)
            await expect(mainArea.getByText('Total Pendapatan')).toBeVisible();
            await expect(mainArea.getByText('Total Mitra')).toBeVisible();
            await expect(mainArea.getByText('Total Booking')).toBeVisible();
            await expect(mainArea.getByText('Total Trip')).toBeVisible();

            // Tautan detail pada kartu statistik
            await expect(
                mainArea.getByRole('link', {
                    name: 'Lihat detail Total Booking',
                }),
            ).toHaveAttribute('href', /\/admin\/bookings$/);
            await expect(
                mainArea.getByRole('link', { name: 'Lihat detail Total Trip' }),
            ).toHaveAttribute('href', /\/admin\/trips$/);

            // 3. Grafik Tren Pendapatan 6 Bulan Terakhir
            await expect(
                mainArea.getByRole('heading', { name: 'Tren Pendapatan' }),
            ).toBeVisible();
            await expect(
                mainArea.getByText('Pergerakan pendapatan 6 bulan terakhir'),
            ).toBeVisible();
            await expect(
                mainArea.getByRole('link', { name: 'Detail Laporan' }),
            ).toHaveAttribute('href', /\/admin\/reports$/);

            // 4. Booking Terbaru (RecentBookings)
            await expect(mainArea.getByText(packageNama).first()).toBeVisible();
        });
    });
});
