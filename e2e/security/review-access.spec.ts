// e2e/security/review-access.spec.ts
import { test, expect, SEED_USERS } from '../fixtures/base';
import { loginAsCustom } from '../fixtures/auth';

test.describe('Security — Review Client (OWASP A01/A03)', () => {
    test('IDOR: client lain tidak bisa membaca atau mengirim review booking orang lain', async ({
        page,
        asClient,
        seed,
    }) => {
        await asClient();

        const pkg = await seed.createPackage(page, {
            mitra_email: SEED_USERS.mitra.email,
            nama: `Paket Security Review ${Date.now()}`,
            status: 'aktif',
            harga_per_orang: 350_000,
        });
        const booking = await seed.createBooking(page, {
            client_email: SEED_USERS.client.email,
            package_kode: pkg.kode,
            status: 'confirmed',
        });
        const trip = await seed.createTrip(page, booking.kode);
        await seed.resetTrip(page, trip.trip_id, 'completed');

        const otherEmail = `client-review-idor-${Date.now()}@test.com`;
        await seed.createUser(page, {
            email: otherEmail,
            name: 'Client IDOR Review',
            role: 'client',
            password: 'password123',
        });
        await loginAsCustom(page, otherEmail, 'password123');

        const getResponse = await page.request.get(
            `/client/bookings/${booking.booking_id}/review`,
            { failOnStatusCode: false },
        );
        expect(getResponse.status()).toBe(403);

        // page.request tidak mengirim header CSRF otomatis seperti Inertia,
        // jadi ambil token dari cookie supaya sampai ke Policy (bukan 419).
        const csrf = await page.evaluate(() =>
            decodeURIComponent(
                document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '',
            ),
        );
        const postResponse = await page.request.post(
            `/client/bookings/${booking.booking_id}/review`,
            {
                data: { rating: 5, komentar: 'Coba mengirim review booking orang lain.' },
                headers: { 'X-XSRF-TOKEN': csrf },
                failOnStatusCode: false,
            },
        );
        expect(postResponse.status()).toBe(403);
    });

    test('mitra tidak bisa mengakses endpoint review client', async ({
        page,
        asMitra,
    }) => {
        await asMitra();

        const response = await page.request.get('/client/bookings/1/review', {
            failOnStatusCode: false,
            maxRedirects: 0,
        });

        expect([302, 403]).toContain(response.status());
    });
});
