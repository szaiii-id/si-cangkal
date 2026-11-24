// e2e/admin/regenerate-eticket.spec.ts
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

test.describe('Admin — Booking Detail & Regenerate E-Ticket', () => {
    const runId = Date.now();
    let pendingBookingId = 0;
    let confirmedBookingId = 0;

    test.beforeAll(async ({ browser }) => {
        const context = await browser.newContext();
        const page = await context.newPage();
        const { seed } = await import('../fixtures/seed');

        await seed.login(
            page,
            SEED_USERS.admin.email,
            SEED_USERS.admin.password,
        );

        const pkg = await seed.createPackage(page, {
            mitra_email: SEED_USERS.mitra.email,
            nama: `Paket Admin ETicket ${runId}`,
            status: 'aktif',
            harga_per_orang: 275_000,
            durasi_hari: 1,
            durasi_malam: 0,
            days: [{ judul: 'Hari 1: Wisata Sungai', lokasi: 'Banjarmasin' }],
            facilities: [
                { tipe: 'include', nama: 'Klotok' },
                { tipe: 'exclude', nama: 'Pengeluaran Pribadi' },
            ],
        });

        const pendingBooking = await seed.createBooking(page, {
            client_email: SEED_USERS.client.email,
            package_kode: pkg.kode,
            status: 'pending_payment',
            tanggal_keberangkatan: getDateOffset(10),
            jumlah_peserta: 1,
        });
        pendingBookingId = pendingBooking.booking_id;

        const confirmedBooking = await seed.createBooking(page, {
            client_email: SEED_USERS.client.email,
            package_kode: pkg.kode,
            status: 'confirmed',
            tanggal_keberangkatan: getDateOffset(11),
            jumlah_peserta: 2,
        });
        confirmedBookingId = confirmedBooking.booking_id;

        await context.close();
    });

    test('Tombol Regenerate E-Ticket tidak tampil pada booking pending_payment', async ({
        page,
        asAdmin,
    }) => {
        await dismissNotificationPrompt(page);
        await asAdmin();

        await page.goto(`/admin/bookings/${pendingBookingId}`);
        await page.waitForLoadState('networkidle');

        await expect(
            page.getByText(/Menunggu Konfirmasi Pembayaran dari Mitra/i),
        ).toBeVisible();
        await expect(
            page.getByRole('button', { name: /Regenerate E-Ticket/i }),
        ).toHaveCount(0);
    });

    test('Admin dapat melakukan Regenerate E-Ticket pada booking confirmed', async ({
        page,
        asAdmin,
    }) => {
        await dismissNotificationPrompt(page);
        await asAdmin();

        await page.goto(`/admin/bookings/${confirmedBookingId}`);
        await page.waitForLoadState('networkidle');

        await expect(
            page.getByText(/Pesanan Telah Dikonfirmasi/i),
        ).toBeVisible();

        const regenBtn = page.getByRole('button', {
            name: /Regenerate E-Ticket/i,
        });
        await expect(regenBtn).toBeVisible({ timeout: 10_000 });
        await regenBtn.click();

        const modal = page.getByRole('dialog', {
            name: /Regenerate E-Ticket\?/i,
        });
        await expect(modal).toBeVisible({ timeout: 5_000 });

        const [response] = await Promise.all([
            page.waitForResponse(
                (res) =>
                    res
                        .url()
                        .includes(
                            `/admin/bookings/${confirmedBookingId}/regenerate-eticket`,
                        ) && res.request().method() === 'POST',
            ),
            modal.getByRole('button', { name: /Ya, Regenerate/i }).click(),
        ]);

        expect([200, 302]).toContain(response.status());
        await expect(modal).toBeHidden({ timeout: 10_000 });
    });
});
