import { test, expect, SEED_USERS } from '../fixtures/base';
import { MitraBookingPage } from '../pages/MitraBookingPage';
import { ClientBookingPage } from '../pages/ClientBookingPage';
import { VALID_JPEG_BUFFER } from '../fixtures/files';
import { SEL } from '../fixtures/selectors';

test.describe('Mitra — Verifikasi Pembayaran Booking', () => {
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
            nama: `Paket Verify Test ${runId}`,
            status: 'aktif',
            durasi_hari: 2,
            durasi_malam: 1,
            harga_per_orang: 500_000,
            days: [
                { judul: 'Hari 1 Eksplorasi', jam_mulai: '08:00', jam_selesai: '16:00', lokasi: 'Loksado' },
                { judul: 'Hari 2 Kepulangan', jam_mulai: '09:00', jam_selesai: '14:00', lokasi: 'Banjarmasin' },
            ],
            facilities: [
                { tipe: 'include', nama: 'Transportasi & Makan' },
                { tipe: 'exclude', nama: 'Pengeluaran Pribadi' },
            ],
        });
        packageKode = pkg.kode;
        await context.close();
    });

    test('Mitra verify payment valid → status confirmed, Trip & E-Ticket terbuat, notifikasi ke client', async ({
        page,
        asClient,
        asMitra,
        seed,
    }) => {
        const booking = await seed.createBooking(page, {
            client_email: SEED_USERS.client.email,
            package_kode: packageKode,
            status: 'pending_payment',
            tanggal_keberangkatan: futureDate(7),
            jumlah_peserta: 1,
            participants: [{ nama: 'Peserta Verify Valid' }],
        });

        // Client upload bukti transfer valid
        await asClient();
        const clientPage = new ClientBookingPage(page);
        await clientPage.gotoShow(booking.booking_id);
        await clientPage.uploadPaymentProof(booking.booking_id, {
            name: 'bukti-verify.jpg',
            mimeType: 'image/jpeg',
            buffer: VALID_JPEG_BUFFER,
        });

        // Mitra buka halaman detail booking dan verifikasi pembayaran
        await asMitra();
        const mitraPage = new MitraBookingPage(page);
        await mitraPage.gotoShow(booking.booking_id);

        await expect(mitraPage.proofImage).toBeVisible();
        await expect(mitraPage.verifyButton).toBeVisible();

        await mitraPage.verifyPayment(booking.booking_id);

        await mitraPage.expectFlashStatus(/Pembayaran berhasil diverifikasi/i);
        await mitraPage.expectStatusBadge(/Terkonfirmasi|Confirmed/i);
        await expect(mitraPage.verifyButton).toBeHidden();
        await expect(mitraPage.rejectButton).toBeHidden();

        // Verifikasi notifikasi masuk ke client
        const notifRes = await seed.latestNotification(page, SEED_USERS.client.email);
        expect(notifRes.notification).not.toBeNull();
        expect(notifRes.notification?.judul).toMatch(/Pembayaran.*Diterima|Booking.*Dikonfirmasi|Terverifikasi/i);

        // Verifikasi E-Ticket sudah terbuat & dapat diunduh client
        await asClient();
        await clientPage.gotoShow(booking.booking_id);
        await expect(SEL.CLIENT.booking.downloadETicket(page)).toBeVisible({ timeout: 10_000 });
    });

    test('Booking pending tanpa proof → tombol Verify tidak muncul', async ({
        page,
        asMitra,
        seed,
    }) => {
        const booking = await seed.createBooking(page, {
            client_email: SEED_USERS.client.email,
            package_kode: packageKode,
            status: 'pending_payment',
            tanggal_keberangkatan: futureDate(8),
            jumlah_peserta: 1,
            participants: [{ nama: 'Peserta Tanpa Proof' }],
        });

        await asMitra();
        const mitraPage = new MitraBookingPage(page);
        await mitraPage.gotoShow(booking.booking_id);

        await expect(mitraPage.verifyButton).toBeHidden();
        await expect(mitraPage.rejectButton).toBeHidden();
    });

    test('Booking sudah confirmed → tombol Verify tidak muncul', async ({
        page,
        asMitra,
        seed,
    }) => {
        const booking = await seed.createBooking(page, {
            client_email: SEED_USERS.client.email,
            package_kode: packageKode,
            status: 'confirmed',
            tanggal_keberangkatan: futureDate(9),
            jumlah_peserta: 1,
            participants: [{ nama: 'Peserta Sudah Confirmed' }],
        });

        await asMitra();
        const mitraPage = new MitraBookingPage(page);
        await mitraPage.gotoShow(booking.booking_id);

        await expect(mitraPage.verifyButton).toBeHidden();
        await expect(mitraPage.rejectButton).toBeHidden();
    });

    test('Mitra lain mengakses & memverifikasi booking bukan miliknya → 403/404', async ({
        page,
        seed,
    }) => {
        const otherMitraEmail = `mitra-other-verify-${runId}@test.com`;
        await seed.createUser(page, {
            email: otherMitraEmail,
            name: 'Mitra Lain Verify',
            role: 'mitra',
            password: 'password123',
            with_bank_account: true,
        });

        const booking = await seed.createBooking(page, {
            client_email: SEED_USERS.client.email,
            package_kode: packageKode,
            status: 'pending_payment',
            tanggal_keberangkatan: futureDate(10),
            jumlah_peserta: 1,
            participants: [{ nama: 'Peserta Cross Mitra' }],
        });

        await seed.login(page, otherMitraEmail, 'password123');
        const response = await page.goto(`/mitra/bookings/${booking.booking_id}`);
        expect([403, 404]).toContain(response?.status());
    });
});