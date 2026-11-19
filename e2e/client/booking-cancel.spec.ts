import { test, expect, SEED_USERS } from '../fixtures/base';
import { ClientBookingPage } from '../pages/ClientBookingPage';
import { SEL } from '../fixtures/selectors';

test.describe('Client Booking — Pembatalan Pesanan', () => {
    test('client dapat membuka modal konfirmasi dan menutupnya dengan tombol Batal atau Escape', async ({
        page,
        asClient,
        seed,
    }) => {
        await asClient();
        const pkg = await seed.createPackage(page, {
            mitra_email: SEED_USERS.mitra.email,
            nama: `Paket Modal Cancel ${Date.now()}`,
            status: 'aktif',
            harga_per_orang: 400_000,
        });
        const booking = await seed.createBooking(page, {
            client_email: SEED_USERS.client.email,
            package_kode: pkg.kode,
            status: 'pending_payment',
        });

        const bookingPage = new ClientBookingPage(page);
        await bookingPage.gotoShow(booking.booking_id);

        // Buka modal konfirmasi
        await SEL.CLIENT.booking.cancelButton(page).click();
        const modal = SEL.CLIENT.booking.confirmModal(page);
        await expect(modal).toBeVisible();
        await expect(modal).toContainText(booking.kode);

        // Tutup via tombol Batal
        await SEL.CLIENT.booking.dismissCancelButton(page).click();
        await expect(modal).toBeHidden();

        // Buka lagi lalu tutup via tombol Escape
        await SEL.CLIENT.booking.cancelButton(page).click();
        await expect(modal).toBeVisible();
        await page.keyboard.press('Escape');
        await expect(modal).toBeHidden();
    });

    test('client membatalkan booking pending_payment -> status berubah cancelled & tombol cancel hilang', async ({
        page,
        asClient,
        seed,
    }) => {
        await asClient();
        const pkg = await seed.createPackage(page, {
            mitra_email: SEED_USERS.mitra.email,
            nama: `Paket Cancel Pending ${Date.now()}`,
            status: 'aktif',
            harga_per_orang: 400_000,
        });
        const booking = await seed.createBooking(page, {
            client_email: SEED_USERS.client.email,
            package_kode: pkg.kode,
            status: 'pending_payment',
        });

        const bookingPage = new ClientBookingPage(page);
        await bookingPage.gotoShow(booking.booking_id);

        await bookingPage.cancelBooking(booking.booking_id);

        await expect(SEL.CLIENT.booking.confirmModal(page)).toBeHidden();
        await expect(
            SEL.CLIENT.booking.statusHeading(page, 'Dibatalkan'),
        ).toBeVisible();
        await expect(SEL.CLIENT.booking.cancelButton(page)).toBeHidden();
    });

    test('booking berstatus confirmed tidak menampilkan tombol Batalkan Pesanan, melainkan info Pembatalan via Mitra', async ({
        page,
        asClient,
        seed,
    }) => {
        await asClient();
        const pkg = await seed.createPackage(page, {
            mitra_email: SEED_USERS.mitra.email,
            nama: `Paket Confirmed Cancel ${Date.now()}`,
            status: 'aktif',
            harga_per_orang: 400_000,
        });
        const booking = await seed.createBooking(page, {
            client_email: SEED_USERS.client.email,
            package_kode: pkg.kode,
            status: 'confirmed',
        });

        const bookingPage = new ClientBookingPage(page);
        await bookingPage.gotoShow(booking.booking_id);

        await expect(SEL.CLIENT.booking.cancelButton(page)).toBeHidden();
        await expect(page.getByText('Pembatalan via Mitra')).toBeVisible();
    });
});