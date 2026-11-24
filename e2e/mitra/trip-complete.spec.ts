import { test, expect, SEED_USERS } from '../fixtures/base';
import { getDateOffset } from '../fixtures/selectors';

/**
 * Alur "selesaikan trip" dari UI mitra.
 *
 * Fase 0 menambahkan aksi manual ini karena sebelumnya status COMPLETED
 * tidak pernah tercapai lewat UI — akibatnya fitur review client mati.
 */
test.describe('Mitra — Selesaikan Perjalanan', () => {
    test('trip aktif → ditandai selesai → booking client ikut selesai', async ({
        page,
        asMitra,
        seed,
    }) => {
        await asMitra();

        const unik = Date.now();
        const pkg = await seed.createPackage(page, {
            mitra_email: SEED_USERS.mitra.email,
            nama: `Paket Selesai E2E ${unik}`,
            status: 'aktif',
            durasi_hari: 1,
            durasi_malam: 0,
            harga_per_orang: 250_000,
        });
        const booking = await seed.createBooking(page, {
            client_email: SEED_USERS.client.email,
            package_kode: pkg.kode,
            status: 'confirmed',
            tanggal_keberangkatan: getDateOffset(7),
            jumlah_peserta: 1,
            participants: [{ nama: 'Peserta Selesai' }],
        });
        const trip = await seed.createTrip(page, booking.kode);
        await seed.resetTrip(page, trip.trip_id, 'active');

        await page.goto(`/mitra/trips/${trip.trip_id}`);
        await expect(
            page.getByRole('button', { name: 'Selesaikan Perjalanan' }),
        ).toBeVisible();

        await page
            .getByRole('button', { name: 'Selesaikan Perjalanan' })
            .click();
        await page
            .getByRole('dialog')
            .getByRole('button', { name: 'Ya, Selesaikan' })
            .click();

        await expect(
            page
                .getByRole('main')
                .getByText('Perjalanan berhasil diselesaikan.'),
        ).toBeVisible();
        await expect(
            page.getByRole('button', { name: 'Selesaikan Perjalanan' }),
        ).toHaveCount(0);

        // Status trip di halaman detail ikut berubah.
        await expect(
            page
                .getByRole('main')
                .getByText('Selesai', { exact: true })
                .first(),
        ).toBeVisible();
    });
});
