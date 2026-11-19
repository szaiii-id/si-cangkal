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

test.describe('Admin — Monitoring Pesanan (Booking Index & Show)', () => {
    // ==========================================
    // ACCESS CONTROL
    // ==========================================

    test.describe('Access Control', () => {
        test('guest, client, dan mitra ditolak mengakses /admin/bookings', async ({
            page,
            asGuest,
            asClient,
            asMitra,
        }) => {
            await dismissNotificationPrompt(page);

            await asGuest();
            await page.goto('/admin/bookings');
            await expect(page).toHaveURL(/\/login$/);

            await asClient();
            await page.goto('/admin/bookings');
            await expect(page).toHaveURL(/\/client\/dashboard$/);

            await asMitra();
            await page.goto('/admin/bookings');
            await expect(page).toHaveURL(/\/mitra\/dashboard$/);
        });

        test('non-admin ditolak mengakses endpoint bukti transfer admin', async ({
            page,
            asClient,
            asMitra,
            seed,
        }) => {
            const pkg = await seed.createPackage(page, {
                mitra_email: SEED_USERS.mitra.email,
                nama: `Paket Sec Test ${Date.now()}`,
                status: 'aktif',
            });
            const booking = await seed.createBooking(page, {
                client_email: SEED_USERS.client.email,
                package_kode: pkg.kode,
                status: 'pending_payment',
            });

            const proofUrl = `/admin/bookings/${booking.booking_id}/payment-proof`;

            await asClient();
            const clientRes = await page.request.get(proofUrl, {
                maxRedirects: 0,
                failOnStatusCode: false,
            });
            expect([302, 401, 403]).toContain(clientRes.status());

            await asMitra();
            const mitraRes = await page.request.get(proofUrl, {
                maxRedirects: 0,
                failOnStatusCode: false,
            });
            expect([302, 401, 403]).toContain(mitraRes.status());
        });
    });

    // ==========================================
    // LISTING, SEARCH & FILTER (/admin/bookings)
    // ==========================================

    test.describe('Listing & Filter Daftar Pesanan', () => {
        test('menampilkan daftar pesanan, pencarian kode booking, toggle Pesanan Hari Ini, dan reset filter', async ({
            page,
            asAdmin,
            seed,
        }) => {
            await dismissNotificationPrompt(page);
            await asAdmin();

            const pkg = await seed.createPackage(page, {
                mitra_email: SEED_USERS.mitra.email,
                nama: `Paket Filter Test ${Date.now()}`,
                status: 'aktif',
            });
            const booking = await seed.createBooking(page, {
                client_email: SEED_USERS.client.email,
                package_kode: pkg.kode,
                status: 'pending_payment',
            });

            await page.goto('/admin/bookings');
            await page.waitForLoadState('networkidle');

            await expect(
                page.getByRole('main').getByRole('heading', { name: 'Daftar Pesanan' }),
            ).toBeVisible();

            const table = page.getByRole('table', { name: 'Daftar Booking' });

            const searchInput = page.getByPlaceholder('Cari kode pesanan, nama pelanggan...');
            await searchInput.fill(booking.kode);

            await expect(table.getByText(booking.kode)).toBeVisible();

            const todayBtn = page.getByRole('button', { name: /Pesanan Hari Ini/i });
            await todayBtn.click();
            await expect(page).toHaveURL(/is_today=1/);

            await page.getByRole('button', { name: 'Hapus semua filter' }).click();
            await expect(page).not.toHaveURL(/is_today=1/);
        });
    });

    // ==========================================
    // DETAIL PESANAN & REGENERATE E-TICKET
    // ==========================================

    test.describe('Detail Pesanan & Aksi Recovery Admin', () => {
        test('tombol Regenerate E-Ticket tampil pada booking confirmed dan dapat diklik', async ({
            page,
            asAdmin,
            seed,
        }) => {
            await dismissNotificationPrompt(page);
            await asAdmin();

            const pkg = await seed.createPackage(page, {
                mitra_email: SEED_USERS.mitra.email,
                nama: `Paket Confirmed Test ${Date.now()}`,
                status: 'aktif',
            });
            const booking = await seed.createBooking(page, {
                client_email: SEED_USERS.client.email,
                package_kode: pkg.kode,
                status: 'confirmed', // Status confirmed langsung dari seeder
            });

            await page.goto(`/admin/bookings/${booking.booking_id}`);
            await page.waitForLoadState('networkidle');

            // Verifikasi elemen halaman detail
            await expect(page.getByText('Pesanan Telah Dikonfirmasi')).toBeVisible();
            await expect(page.getByText(booking.kode).first()).toBeVisible();

            // Cek tombol Regenerate E-Ticket
            const regenerateBtn = page.getByRole('button', { name: /Regenerate E-Ticket/i });
            await expect(regenerateBtn).toBeVisible();
            await regenerateBtn.click();

            const dialog = page.getByRole('dialog');
            await expect(dialog.getByText('Regenerate E-Ticket?')).toBeVisible();
            await dialog.getByRole('button', { name: /Batal/i }).click(); // Batal agar test aman dan bersih
        });

        test('tombol Regenerate E-Ticket tidak muncul pada pesanan pending_payment', async ({
            page,
            asAdmin,
            seed,
        }) => {
            await dismissNotificationPrompt(page);
            await asAdmin();

            const pkg = await seed.createPackage(page, {
                mitra_email: SEED_USERS.mitra.email,
                nama: `Paket Pending Test ${Date.now()}`,
                status: 'aktif',
            });
            const booking = await seed.createBooking(page, {
                client_email: SEED_USERS.client.email,
                package_kode: pkg.kode,
                status: 'pending_payment',
            });

            await page.goto(`/admin/bookings/${booking.booking_id}`);
            await page.waitForLoadState('networkidle');

            await expect(page.getByText('Menunggu Konfirmasi Pembayaran dari Mitra')).toBeVisible();
            await expect(page.getByRole('button', { name: /Regenerate E-Ticket/i })).toBeHidden();
        });
    });
});