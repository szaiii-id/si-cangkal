// e2e/mitra/refund-index.spec.ts
import { test, expect, SEED_USERS } from '../fixtures/base';
import { getDateOffset } from '../fixtures/selectors';
import { apiPost } from '../fixtures/api';
import type { Page } from '@playwright/test';

async function dismissNotificationPrompt(page: Page): Promise<void> {
    await page.addLocatorHandler(
        page.getByRole('dialog', { name: /Aktifkan Notifikasi/i }),
        async () => {
            await page.getByRole('button', { name: /Nanti Saja/i }).click();
        },
    );
}

test.describe('Mitra — Refund Index, Filter & Search', () => {
    const runId = Date.now();
    let bookingKode = '';

    test.beforeAll(async ({ browser }) => {
        const context = await browser.newContext();
        const page = await context.newPage();
        const { seed } = await import('../fixtures/seed');

        await seed.login(
            page,
            SEED_USERS.mitra.email,
            SEED_USERS.mitra.password,
        );

        const pkg = await seed.createPackage(page, {
            mitra_email: SEED_USERS.mitra.email,
            nama: `Paket Refund Index ${runId}`,
            status: 'aktif',
            harga_per_orang: 300_000,
            durasi_hari: 1,
            durasi_malam: 0,
            days: [{ judul: 'Hari 1', lokasi: 'Banjarmasin' }],
            facilities: [
                { tipe: 'include', nama: 'Guide' },
                { tipe: 'exclude', nama: 'Makan' },
            ],
        });

        const booking = await seed.createBooking(page, {
            client_email: SEED_USERS.client.email,
            package_kode: pkg.kode,
            status: 'confirmed',
            tanggal_keberangkatan: getDateOffset(8),
            jumlah_peserta: 1,
        });
        bookingKode = booking.kode;

        // Batalkan booking via endpoint mitra agar menghasilkan record Refund PENDING
        await page.goto('/mitra/dashboard');
        await apiPost(page, `/mitra/bookings/${booking.booking_id}/cancel`, {
            alasan: 'Setup data refund untuk pengujian halaman index',
        });

        await context.close();
    });

    test('Menampilkan daftar refund, detail nominal, dan status tab', async ({
        page,
        asMitra,
    }) => {
        await dismissNotificationPrompt(page);
        await asMitra();

        await page.goto('/mitra/refunds');
        await page.waitForLoadState('networkidle');

        await expect(
            page.getByRole('heading', { name: 'Pengembalian Dana (Refund)' }),
        ).toBeVisible();

        const card = page
            .locator('article')
            .filter({ hasText: bookingKode })
            .first();
        await expect(card).toBeVisible({ timeout: 10_000 });
        await expect(card.getByText(/Rp\s*300\.000/i)).toBeVisible();
        await expect(
            card.getByRole('button', { name: /Setujui/i }),
        ).toBeVisible();
        await expect(
            card.getByRole('button', { name: /Tolak/i }),
        ).toBeVisible();
    });

    test('Pencarian berdasarkan kode booking dan reset filter bekerja dengan benar', async ({
        page,
        asMitra,
    }) => {
        await dismissNotificationPrompt(page);
        await asMitra();

        await page.goto('/mitra/refunds');
        await page.waitForLoadState('networkidle');

        const searchInput = page.getByPlaceholder(
            /Cari kode pesanan atau nama pelanggan/i,
        );
        await searchInput.fill(bookingKode);

        await expect(
            page.locator('article').filter({ hasText: bookingKode }).first(),
        ).toBeVisible({ timeout: 10_000 });

        // Cari kode yang tidak ada -> EmptyState tampil
        await searchInput.fill(`TIDAK-ADA-${runId}`);
        await expect(
            page.getByText(/Tidak ada pengembalian ditemukan/i),
        ).toBeVisible({ timeout: 10_000 });
    });
});
