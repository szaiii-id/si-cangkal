import { test, expect, SEED_USERS } from '../fixtures/base';

test.describe('Client Review — submit ulasan paket', () => {
    test('trip selesai → beri review → ulasan tayang di katalog', async ({
        page,
        asClient,
        seed,
    }) => {
        await asClient();

        const namaPaket = `Paket Review E2E ${Date.now()}`;
        const komentar =
            'Pengalaman perjalanan sangat menyenangkan dan teratur.';

        const pkg = await seed.createPackage(page, {
            mitra_email: SEED_USERS.mitra.email,
            nama: namaPaket,
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

        // Detail booking — tombol review hanya muncul saat trip selesai.
        await page.goto(`/client/bookings/${booking.booking_id}`);
        const tombolReview = page.getByRole('link', { name: /Beri Review/ });
        await expect(tombolReview).toBeVisible();
        await tombolReview.click();

        await expect(page).toHaveURL(/\/review$/);
        await page.getByRole('button', { name: '4 bintang' }).click();
        await page.getByRole('textbox', { name: /Komentar/ }).fill(komentar);
        await page.getByRole('button', { name: 'Kirim Review' }).click();

        // Redirect eksplisit ke detail booking + flash status.
        await expect(page).toHaveURL(`/client/bookings/${booking.booking_id}`);
        await expect(
            page.getByText('Terima kasih! Review kamu sudah terkirim.'),
        ).toBeVisible();
        await expect(page.getByText('Review terkirim')).toBeVisible();

        // Ulasan otomatis tayang di katalog tanpa approval.
        // Pakai filter search: di suite penuh katalog terpaginated, jadi
        // paket yang baru dibuat belum tentu ada di halaman pertama.
        await page.goto(
            `/client/catalog?search=${encodeURIComponent(namaPaket)}`,
        );
        await page
            .getByRole('link', { name: new RegExp(namaPaket) })
            .first()
            .click();

        await expect(
            page.getByRole('heading', { name: 'Ulasan Client' }),
        ).toBeVisible();
        await expect(page.getByText(komentar)).toBeVisible();
        await expect(page.getByText('4.0', { exact: true })).toBeVisible();
    });

    test('trip masih scheduled → tombol review tidak muncul', async ({
        page,
        asClient,
        seed,
    }) => {
        await asClient();

        const pkg = await seed.createPackage(page, {
            mitra_email: SEED_USERS.mitra.email,
            nama: `Paket Belum Selesai ${Date.now()}`,
            status: 'aktif',
            harga_per_orang: 350_000,
        });
        const booking = await seed.createBooking(page, {
            client_email: SEED_USERS.client.email,
            package_kode: pkg.kode,
            status: 'confirmed',
        });
        await seed.createTrip(page, booking.kode);

        await page.goto(`/client/bookings/${booking.booking_id}`);

        await expect(
            page.getByRole('link', { name: /Beri Review/ }),
        ).toBeHidden();
    });
});
