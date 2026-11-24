// e2e/mitra/refund-validation.spec.ts
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

function generateUniquePackageCode(workerIndex: number): string {
    const rand = Math.random().toString(36).slice(2, 7).toUpperCase();
    return `PKG-REF-${workerIndex}-${Date.now()}-${rand}`;
}

test.describe('Mitra — Refund Form Validation & Modal Behavior', () => {
    let bookingKode = '';

    test.beforeAll(async ({ browser }, testInfo) => {
        const context = await browser.newContext();
        const page = await context.newPage();
        const { seed } = await import('../fixtures/seed');

        const uniqueKode = generateUniquePackageCode(testInfo.workerIndex);

        await seed.login(
            page,
            SEED_USERS.mitra.email,
            SEED_USERS.mitra.password,
        );

        const pkg = await seed.createPackage(page, {
            mitra_email: SEED_USERS.mitra.email,
            kode: uniqueKode,
            nama: `Paket Refund Validation ${uniqueKode}`,
            status: 'aktif',
            harga_per_orang: 250_000,
            durasi_hari: 1,
            durasi_malam: 0,
            days: [{ judul: 'Hari 1', lokasi: 'Martapura' }],
            facilities: [
                { tipe: 'include', nama: 'Transportasi' },
                { tipe: 'exclude', nama: 'Dokumentasi' },
            ],
        });

        const booking = await seed.createBooking(page, {
            client_email: SEED_USERS.client.email,
            package_kode: pkg.kode,
            status: 'confirmed',
            tanggal_keberangkatan: getDateOffset(9),
            jumlah_peserta: 1,
        });
        bookingKode = booking.kode;

        await page.goto('/mitra/dashboard');
        await apiPost(page, `/mitra/bookings/${booking.booking_id}/cancel`, {
            alasan: 'Setup data refund untuk pengujian validasi modal',
        });

        await context.close();
    });

    test('Menolak refund dengan alasan kosong atau < 5 karakter menampilkan error validasi', async ({
        page,
        asMitra,
    }) => {
        await dismissNotificationPrompt(page);
        await asMitra();

        await page.goto('/mitra/refunds');
        await page.waitForLoadState('networkidle');

        const card = page
            .locator('article')
            .filter({ hasText: bookingKode })
            .first();
        await expect(card).toBeVisible({ timeout: 10_000 });

        await card.getByRole('button', { name: /Tolak/i }).click();

        const rejectDialog = page.getByRole('dialog', {
            name: /Tolak Pengembalian/i,
        });
        await expect(rejectDialog).toBeVisible({ timeout: 5_000 });

        // Isi alasan kurang dari 5 karakter
        await rejectDialog.locator('#confirm-reason').fill('abc');

        const submitRejectBtn = rejectDialog.getByRole('button', {
            name: /Ya, Tolak/i,
        });

        if (await submitRejectBtn.isDisabled()) {
            await expect(submitRejectBtn).toBeDisabled();
        } else {
            await submitRejectBtn.click();
            await expect(
                rejectDialog.getByText(/Alasan penolakan minimal 5 karakter/i),
            ).toBeVisible();
        }
    });

    test('Modal approve refund dapat dibuka dan dibatalkan tanpa mengubah status', async ({
        page,
        asMitra,
    }) => {
        await dismissNotificationPrompt(page);
        await asMitra();

        await page.goto('/mitra/refunds');
        await page.waitForLoadState('networkidle');

        const card = page
            .locator('article')
            .filter({ hasText: bookingKode })
            .first();
        await expect(card).toBeVisible({ timeout: 10_000 });

        await card.getByRole('button', { name: /Setujui/i }).click();

        const approveDialog = page.getByRole('dialog', {
            name: /Setujui Pengembalian/i,
        });
        await expect(approveDialog).toBeVisible({ timeout: 5_000 });

        // Tutup modal tanpa menyimpan
        await approveDialog.getByRole('button', { name: /Batal/i }).click();
        await expect(approveDialog).toBeHidden();

        // Status masih PENDING (tombol Setujui & Tolak tetap tampil)
        await expect(
            card.getByRole('button', { name: /Setujui/i }),
        ).toBeVisible();
        await expect(
            card.getByRole('button', { name: /Tolak/i }),
        ).toBeVisible();
    });
});
