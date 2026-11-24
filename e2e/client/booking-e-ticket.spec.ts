import { test, expect, SEED_USERS } from '../fixtures/base';
import { loginAsCustom } from '../fixtures/auth';
import { ClientBookingPage } from '../pages/ClientBookingPage';
import { SEL } from '../fixtures/selectors';

test.describe('Client Booking — E-Ticket & Unduhan PDF', () => {
    test('booking pending_payment tidak menampilkan kartu maupun tombol E-Ticket', async ({
        page,
        asClient,
        seed,
    }) => {
        await asClient();
        const pkg = await seed.createPackage(page, {
            mitra_email: SEED_USERS.mitra.email,
            nama: `Paket Pending ETicket ${Date.now()}`,
            status: 'aktif',
            harga_per_orang: 350_000,
        });
        const booking = await seed.createBooking(page, {
            client_email: SEED_USERS.client.email,
            package_kode: pkg.kode,
            status: 'pending_payment',
        });

        const bookingPage = new ClientBookingPage(page);
        await bookingPage.gotoShow(booking.booking_id);

        await expect(SEL.CLIENT.booking.downloadETicket(page)).toBeHidden();
        await expect(SEL.CLIENT.booking.eticketProcessing(page)).toBeHidden();
    });

    test('booking confirmed tanpa E-Ticket menampilkan state sedang diproses', async ({
        page,
        asClient,
        seed,
    }) => {
        await asClient();
        const pkg = await seed.createPackage(page, {
            mitra_email: SEED_USERS.mitra.email,
            nama: `Paket Processing ETicket ${Date.now()}`,
            status: 'aktif',
            harga_per_orang: 350_000,
        });
        const booking = await seed.createBooking(page, {
            client_email: SEED_USERS.client.email,
            package_kode: pkg.kode,
            status: 'confirmed',
        });

        const bookingPage = new ClientBookingPage(page);
        await bookingPage.gotoShow(booking.booking_id);

        await expect(page.getByRole('button', { name: /Aktifkan/i }))
            .toBeHidden({ timeout: 5_000 })
            .catch(() => {});

        await expect(SEL.CLIENT.booking.eticketProcessing(page)).toBeVisible();
        await expect(SEL.CLIENT.booking.downloadETicket(page)).toBeHidden();
    });

    test('booking confirmed dengan Trip & E-Ticket menampilkan tombol Download E-Ticket dan mengunduh PDF valid', async ({
        page,
        asClient,
        seed,
    }) => {
        await asClient();
        const pkg = await seed.createPackage(page, {
            mitra_email: SEED_USERS.mitra.email,
            nama: `Paket Ready ETicket ${Date.now()}`,
            status: 'aktif',
            harga_per_orang: 350_000,
        });
        const booking = await seed.createBooking(page, {
            client_email: SEED_USERS.client.email,
            package_kode: pkg.kode,
            status: 'confirmed',
        });
        const trip = await seed.createTrip(page, booking.kode);
        const ticket = await seed.generateETicket(page, trip.kode);

        const bookingPage = new ClientBookingPage(page);
        await bookingPage.gotoShow(booking.booking_id);

        const downloadLink = SEL.CLIENT.booking.downloadETicket(page);
        await expect(downloadLink).toBeVisible();
        await expect(downloadLink).toHaveAttribute(
            'href',
            new RegExp(`/client/e-tickets/${ticket.uuid}/download$`),
        );

        // Verifikasi endpoint download mengembalikan PDF valid
        const response = await page.request.get(ticket.download_url);
        expect(response.status()).toBe(200);
        expect(response.headers()['content-type']).toContain('application/pdf');
    });

    test('security: client lain ditolak (403) saat mengunduh E-Ticket milik orang lain', async ({
        page,
        asClient,
        seed,
    }) => {
        await asClient();
        const pkg = await seed.createPackage(page, {
            mitra_email: SEED_USERS.mitra.email,
            nama: `Paket Security ETicket ${Date.now()}`,
            status: 'aktif',
            harga_per_orang: 350_000,
        });
        const booking = await seed.createBooking(page, {
            client_email: SEED_USERS.client.email,
            package_kode: pkg.kode,
            status: 'confirmed',
        });
        const trip = await seed.createTrip(page, booking.kode);
        const ticket = await seed.generateETicket(page, trip.kode);

        // Buat & login sebagai client berbeda
        const otherEmail = `other_client_${Date.now()}@test.com`;
        await seed.createUser(page, {
            email: otherEmail,
            name: 'Other Client',
            role: 'client',
            password: 'password',
        });
        await loginAsCustom(page, otherEmail, 'password');

        const response = await page.request.get(ticket.download_url, {
            failOnStatusCode: false,
        });
        expect(response.status()).toBe(403);
    });
});
