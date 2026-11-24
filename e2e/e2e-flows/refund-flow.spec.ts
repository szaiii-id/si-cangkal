// e2e/e2e-flows/refund-flow.spec.ts
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

// ==========================================
// FLOW A — APPROVE REFUND
// ==========================================

test.describe.serial('E2E Flow — Refund Approval (PENDING → SUCCEEDED)', () => {
    let packageKode: string;
    let bookingId: number;
    let bookingKode: string;

    test('step 1: setup booking confirmed & mitra batalkan booking → Refund PENDING', async ({
        page,
        asMitra,
        seed,
    }) => {
        await dismissNotificationPrompt(page);
        await asMitra();

        // --- Setup paket & booking confirmed via /_test/* ---
        const pkg = await seed.createPackage(page, {
            mitra_email: SEED_USERS.mitra.email,
            nama: `Paket Refund Approve ${Date.now()}_${Math.floor(Math.random() * 1000)}`,
            status: 'aktif',
            harga_per_orang: 400_000,
            durasi_hari: 2,
            durasi_malam: 1,
            days: [
                { judul: 'Hari 1: Keberangkatan', lokasi: 'Banjarmasin' },
                { judul: 'Hari 2: Kepulangan', lokasi: 'Martapura' },
            ],
            facilities: [
                { tipe: 'include', nama: 'Transportasi' },
                { tipe: 'exclude', nama: 'Makan Malam' },
            ],
        });
        packageKode = pkg.kode;

        const booking = await seed.createBooking(page, {
            client_email: SEED_USERS.client.email,
            package_kode: packageKode,
            status: 'confirmed',
            tanggal_keberangkatan: getDateOffset(7),
            jumlah_peserta: 2,
        });
        bookingId = booking.booking_id;
        bookingKode = booking.kode;

        // --- Aksi: Mitra buka detail booking & batalkan booking ---
        await page.goto(`/mitra/bookings/${bookingId}`);
        await page.waitForLoadState('networkidle');

        const cancelBtn = page.getByRole('button', {
            name: /Batalkan & Refund/i,
        });
        await expect(
            cancelBtn,
            'Tombol "Batalkan & Refund" harus tampil pada booking confirmed',
        ).toBeVisible({ timeout: 10_000 });
        await cancelBtn.click();

        // --- Isi modal konfirmasi pembatalan ---
        const dialog = page
            .getByRole('dialog')
            .filter({ hasNotText: /Aktifkan Notifikasi/i });
        await expect(dialog).toBeVisible({ timeout: 5_000 });

        const reasonInput = dialog.locator('#confirm-reason');
        if (await reasonInput.isVisible()) {
            await reasonInput.fill(
                'Cuaca ekstrem, perjalanan dibatalkan oleh mitra',
            );
        }

        const confirmBtn = dialog.getByRole('button', {
            name: /Ya, Batalkan|Batalkan/i,
        });
        await Promise.all([
            page.waitForResponse(
                (res) =>
                    res.url().includes(`/mitra/bookings/${bookingId}/cancel`) &&
                    res.request().method() === 'POST',
            ),
            confirmBtn.click(),
        ]);

        // --- Assert: notifikasi pembatalan terkirim ke client ---
        const notif = await seed.latestNotification(
            page,
            SEED_USERS.client.email,
        );
        expect(
            notif.notification,
            'Client harus menerima notifikasi pembatalan booking',
        ).not.toBeNull();
    });

    test('step 2: mitra approve refund di /mitra/refunds → SUCCEEDED & notifikasi client', async ({
        page,
        asMitra,
        seed,
    }) => {
        await dismissNotificationPrompt(page);
        await asMitra();

        await page.goto('/mitra/refunds');
        await page.waitForLoadState('networkidle');

        // --- Cari card refund berdasarkan kode booking ---
        const refundCard = page
            .locator('article')
            .filter({ hasText: bookingKode })
            .first();

        await expect(
            refundCard,
            `Card refund untuk booking ${bookingKode} harus tampil`,
        ).toBeVisible({ timeout: 10_000 });

        await expect(
            refundCard.getByRole('button', { name: /Setujui/i }),
        ).toBeVisible();

        // --- Aksi: klik tombol Setujui & Kembalikan Dana ---
        await refundCard.getByRole('button', { name: /Setujui/i }).click();

        const approveDialog = page.getByRole('dialog', {
            name: /Setujui Pengembalian/i,
        });
        await expect(approveDialog).toBeVisible({ timeout: 5_000 });

        await approveDialog
            .locator('#approve-catatan')
            .fill('Dana telah ditransfer kembali ke rekening pelanggan.');

        await Promise.all([
            page.waitForResponse(
                (res) =>
                    /\/mitra\/refunds\/\d+\/approve/.test(res.url()) &&
                    res.request().method() === 'POST',
            ),
            approveDialog.getByRole('button', { name: /Ya, Setujui/i }).click(),
        ]);

        // --- Assert: card refund berubah status menjadi dana telah dikembalikan ---
        await expect(
            refundCard.getByText(/Dana telah dikembalikan ke pelanggan/i),
            'Status card refund harus menunjukkan dana telah dikembalikan',
        ).toBeVisible({ timeout: 10_000 });

        // --- Assert: notifikasi refund_succeeded terkirim ke client ---
        const notif = await seed.latestNotification(
            page,
            SEED_USERS.client.email,
        );
        expect(notif.notification).not.toBeNull();
        expect(notif.notification?.tipe).toBe('refund_succeeded');
    });

    test('step 3: client melihat status booking berubah menjadi Dibatalkan', async ({
        page,
        asClient,
    }) => {
        await dismissNotificationPrompt(page);
        await asClient();

        await page.goto(`/client/bookings/${bookingId}`);
        await page.waitForLoadState('networkidle');

        await expect(
            page.getByText(/Dibatalkan|cancelled/i).first(),
            'Status booking di sisi client harus Dibatalkan setelah refund disetujui',
        ).toBeVisible({ timeout: 10_000 });
    });
});

// ==========================================
// FLOW B — REJECT REFUND
// ==========================================

test.describe
    .serial('E2E Flow — Refund Rejection (PENDING → CANCELLED)', () => {
    let bookingId: number;
    let bookingKode: string;

    test('step 1: setup booking confirmed & mitra batalkan booking → Refund PENDING', async ({
        page,
        asMitra,
        seed,
    }) => {
        await dismissNotificationPrompt(page);
        await asMitra();

        const pkg = await seed.createPackage(page, {
            mitra_email: SEED_USERS.mitra.email,
            nama: `Paket Refund Reject ${Date.now()}_${Math.floor(Math.random() * 1000)}`,
            status: 'aktif',
            harga_per_orang: 350_000,
            durasi_hari: 1,
            durasi_malam: 0,
            days: [{ judul: 'Hari 1: Tur Kota', lokasi: 'Banjarmasin' }],
            facilities: [
                { tipe: 'include', nama: 'Guide Lokal' },
                { tipe: 'exclude', nama: 'Tiket Masuk' },
            ],
        });

        const booking = await seed.createBooking(page, {
            client_email: SEED_USERS.client.email,
            package_kode: pkg.kode,
            status: 'confirmed',
            tanggal_keberangkatan: getDateOffset(7),
            jumlah_peserta: 1,
        });
        bookingId = booking.booking_id;
        bookingKode = booking.kode;

        await page.goto(`/mitra/bookings/${bookingId}`);
        await page.waitForLoadState('networkidle');

        const cancelBtn = page.getByRole('button', {
            name: /Batalkan & Refund/i,
        });
        await expect(cancelBtn).toBeVisible({ timeout: 10_000 });
        await cancelBtn.click();

        const dialog = page
            .getByRole('dialog')
            .filter({ hasNotText: /Aktifkan Notifikasi/i });
        await expect(dialog).toBeVisible({ timeout: 5_000 });

        const reasonInput = dialog.locator('#confirm-reason');
        if (await reasonInput.isVisible()) {
            await reasonInput.fill(
                'Pembatalan jadwal operasional paket wisata',
            );
        }

        await Promise.all([
            page.waitForResponse(
                (res) =>
                    res.url().includes(`/mitra/bookings/${bookingId}/cancel`) &&
                    res.request().method() === 'POST',
            ),
            dialog
                .getByRole('button', { name: /Ya, Batalkan|Batalkan/i })
                .click(),
        ]);
    });

    test('step 2: mitra reject refund dengan alasan valid → CANCELLED & notifikasi client', async ({
        page,
        asMitra,
        seed,
    }) => {
        await dismissNotificationPrompt(page);
        await asMitra();

        await page.goto('/mitra/refunds');
        await page.waitForLoadState('networkidle');

        const refundCard = page
            .locator('article')
            .filter({ hasText: bookingKode })
            .first();

        await expect(refundCard).toBeVisible({ timeout: 10_000 });

        // --- Aksi: klik tombol Tolak Pengajuan ---
        await refundCard.getByRole('button', { name: /Tolak/i }).click();

        const rejectDialog = page.getByRole('dialog', {
            name: /Tolak Pengembalian/i,
        });
        await expect(rejectDialog).toBeVisible({ timeout: 5_000 });

        const alasan =
            'Tidak memenuhi syarat pengembalian dana sesuai ketentuan paket.';
        await rejectDialog.locator('#confirm-reason').fill(alasan);

        await Promise.all([
            page.waitForResponse(
                (res) =>
                    /\/mitra\/refunds\/\d+\/reject/.test(res.url()) &&
                    res.request().method() === 'POST',
            ),
            rejectDialog.getByRole('button', { name: /Ya, Tolak/i }).click(),
        ]);

        // --- Assert: status card berubah menjadi Pengajuan refund dibatalkan ---
        await expect(
            refundCard.getByText(/Pengajuan refund dibatalkan/i),
            'Status card refund harus menunjukkan pengajuan dibatalkan',
        ).toBeVisible({ timeout: 10_000 });

        // --- Assert: notifikasi refund_rejected terkirim ke client ---
        const notif = await seed.latestNotification(
            page,
            SEED_USERS.client.email,
        );
        expect(notif.notification).not.toBeNull();
        expect(notif.notification?.tipe).toBe('refund_rejected');
        expect(notif.notification?.pesan).toContain(alasan);
    });
});
