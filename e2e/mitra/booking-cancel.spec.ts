import { test, expect, SEED_USERS } from '../fixtures/base';
import { MitraBookingPage } from '../pages/MitraBookingPage';

test.describe('Mitra — Pembatalan Booking oleh Mitra', () => {
    const runId = Date.now();
    let packageKode = '';

    function futureDate(daysAhead: number): string {
        const d = new Date();
        d.setDate(d.getDate() + daysAhead);
        return d.toISOString().split('T')[0];
    }

    test.beforeAll(async ({ browser }) => {
        const context = await browser.newContext();
        const page = await context.newPage();

        const { seed } = await import('../fixtures/seed');
        const pkg = await seed.createPackage(page, {
            mitra_email: SEED_USERS.mitra.email,
            nama: `Paket Cancel Mitra Test ${runId}`,
            status: 'aktif',
            durasi_hari: 1,
            durasi_malam: 0,
            harga_per_orang: 400_000,
            days: [{ judul: 'Tur Sehari', jam_mulai: '08:00', jam_selesai: '16:00', lokasi: 'Martapura' }],
            facilities: [
                { tipe: 'include', nama: 'Transportasi AC' },
                { tipe: 'exclude', nama: 'Makan Malam' },
            ],
        });
        packageKode = pkg.kode;
        await context.close();
    });

    test('Mitra cancel booking confirmed → status cancelled, Refund PENDING dibuat, notifikasi ke client', async ({
        page,
        asMitra,
        seed,
    }) => {
        const cancelReason = 'Cuaca ekstrem di lokasi tujuan sehingga perjalanan tidak dapat dilaksanakan';

        const booking = await seed.createBooking(page, {
            client_email: SEED_USERS.client.email,
            package_kode: packageKode,
            status: 'confirmed',
            tanggal_keberangkatan: futureDate(7),
            jumlah_peserta: 2,
            participants: [{ nama: 'Peserta Cancel 1' }, { nama: 'Peserta Cancel 2' }],
        });

        await asMitra();
        const mitraPage = new MitraBookingPage(page);
        await mitraPage.gotoShow(booking.booking_id);

        await expect(mitraPage.cancelButton).toBeVisible();
        await mitraPage.cancelBooking(booking.booking_id, cancelReason);

        // Cancel redirect ke /mitra/bookings
        await expect(page).toHaveURL('/mitra/bookings');
        await mitraPage.expectFlashStatus(/dibatalkan/i);

        // Pastikan status booking kini cancelled
        await mitraPage.gotoShow(booking.booking_id);
        await mitraPage.expectStatusBadge(/Dibatalkan|Cancelled/i);
        await expect(mitraPage.cancelButton).toBeHidden();

        // Pastikan record Refund PENDING muncul di /mitra/refunds
        await page.goto('/mitra/refunds');
        await expect(page.getByText(booking.kode)).toBeVisible({ timeout: 10_000 });

        // Verifikasi notifikasi masuk ke client
        const notifRes = await seed.latestNotification(page, SEED_USERS.client.email);
        expect(notifRes.notification).not.toBeNull();
        expect(notifRes.notification?.judul).toMatch(/Booking.*Dibatalkan|Pesanan.*Dibatalkan/i);
    });

    test('Booking sudah cancelled → tombol Cancel tidak muncul', async ({
        page,
        asMitra,
        seed,
    }) => {
        const booking = await seed.createBooking(page, {
            client_email: SEED_USERS.client.email,
            package_kode: packageKode,
            status: 'cancelled',
            tanggal_keberangkatan: futureDate(8),
            jumlah_peserta: 1,
            participants: [{ nama: 'Peserta Sudah Cancelled' }],
        });

        await asMitra();
        const mitraPage = new MitraBookingPage(page);
        await mitraPage.gotoShow(booking.booking_id);

        await expect(mitraPage.cancelButton).toBeHidden();
    });

    test('Mitra lain mencoba membatalkan booking bukan miliknya → 403/404', async ({
        page,
        seed,
    }) => {
        const otherMitraEmail = `mitra-other-cancel-${runId}@test.com`;
        await seed.createUser(page, {
            email: otherMitraEmail,
            name: 'Mitra Lain Cancel',
            role: 'mitra',
            password: 'password123',
            with_bank_account: true,
        });

        const booking = await seed.createBooking(page, {
            client_email: SEED_USERS.client.email,
            package_kode: packageKode,
            status: 'confirmed',
            tanggal_keberangkatan: futureDate(9),
            jumlah_peserta: 1,
            participants: [{ nama: 'Peserta Cross Mitra Cancel' }],
        });

        await seed.login(page, otherMitraEmail, 'password123');
        const response = await page.goto(`/mitra/bookings/${booking.booking_id}`);
        expect([403, 404]).toContain(response?.status());
    });
});