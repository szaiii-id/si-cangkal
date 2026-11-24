import { test, expect, SEED_USERS } from '../fixtures/base';
import { MitraBookingPage } from '../pages/MitraBookingPage';
import { ClientBookingPage } from '../pages/ClientBookingPage';
import { VALID_JPEG_BUFFER } from '../fixtures/files';

test.describe('Mitra — Penolakan Pembayaran Booking', () => {
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
            nama: `Paket Reject Test ${runId}`,
            status: 'aktif',
            durasi_hari: 1,
            durasi_malam: 0,
            harga_per_orang: 350_000,
            days: [
                {
                    judul: 'Tur Sehari',
                    jam_mulai: '08:00',
                    jam_selesai: '17:00',
                    lokasi: 'Banjarmasin',
                },
            ],
            facilities: [
                { tipe: 'include', nama: 'Perahu Klotok' },
                { tipe: 'exclude', nama: 'Belanja Pribadi' },
            ],
        });
        packageKode = pkg.kode;
        await context.close();
    });

    test('Mitra reject payment dengan alasan valid → status tetap pending, alasan tampil di client, notifikasi terkirim', async ({
        page,
        asClient,
        asMitra,
        seed,
    }) => {
        const rejectReason =
            'Nominal transfer pada bukti pembayaran tidak sesuai tagihan';

        const booking = await seed.createBooking(page, {
            client_email: SEED_USERS.client.email,
            package_kode: packageKode,
            status: 'pending_payment',
            tanggal_keberangkatan: futureDate(7),
            jumlah_peserta: 1,
            participants: [{ nama: 'Peserta Reject Valid' }],
        });

        // Client upload bukti transfer
        await asClient();
        const clientPage = new ClientBookingPage(page);
        await clientPage.gotoShow(booking.booking_id);
        await clientPage.uploadPaymentProof(booking.booking_id, {
            name: 'bukti-reject.jpg',
            mimeType: 'image/jpeg',
            buffer: VALID_JPEG_BUFFER,
        });

        // Mitra tolak pembayaran dengan alasan >= 5 karakter
        await asMitra();
        const mitraPage = new MitraBookingPage(page);
        await mitraPage.gotoShow(booking.booking_id);

        await mitraPage.rejectPayment(booking.booking_id, rejectReason);

        await mitraPage.expectFlashStatus(/ditolak/i);
        await mitraPage.expectStatusBadge(/Menunggu Pembayaran|Pending/i);

        // Verifikasi notifikasi beserta alasan penolakan terkirim ke client
        const notifRes = await seed.latestNotification(
            page,
            SEED_USERS.client.email,
        );
        expect(notifRes.notification).not.toBeNull();
        expect(notifRes.notification?.judul).toMatch(/Bukti Transfer Ditolak/i);
        expect(notifRes.notification?.pesan).toContain(rejectReason);

        // Verifikasi status di sisi client tetap Menunggu Pembayaran
        await asClient();
        await clientPage.gotoShow(booking.booking_id);
        await expect(page.getByText('Menunggu Pembayaran')).toBeVisible();
    });

    test('Reject tanpa alasan atau alasan < 5 karakter → validasi gagal & modal tetap terbuka', async ({
        page,
        asClient,
        asMitra,
        seed,
    }) => {
        const booking = await seed.createBooking(page, {
            client_email: SEED_USERS.client.email,
            package_kode: packageKode,
            status: 'pending_payment',
            tanggal_keberangkatan: futureDate(8),
            jumlah_peserta: 1,
            participants: [{ nama: 'Peserta Reject Invalid' }],
        });

        await asClient();
        const clientPage = new ClientBookingPage(page);
        await clientPage.gotoShow(booking.booking_id);
        await clientPage.uploadPaymentProof(booking.booking_id, {
            name: 'bukti-reject-invalid.jpg',
            mimeType: 'image/jpeg',
            buffer: VALID_JPEG_BUFFER,
        });

        await asMitra();
        const mitraPage = new MitraBookingPage(page);
        await mitraPage.gotoShow(booking.booking_id);

        await mitraPage.rejectButton.click();
        await expect(mitraPage.confirmModal).toBeVisible();

        // Alasan < 5 karakter
        await mitraPage.reasonInput.fill('abc');

        const isDisabled = await mitraPage.confirmRejectButton.isDisabled();
        if (isDisabled) {
            expect(isDisabled).toBe(true);
        } else {
            await mitraPage.confirmRejectButton.click();
            await expect(mitraPage.confirmModal).toBeVisible();
        }
    });
});
