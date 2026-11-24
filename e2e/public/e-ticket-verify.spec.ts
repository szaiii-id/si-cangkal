import { test, expect, SEED_USERS } from '../fixtures/base';
import { getDateOffset } from '../fixtures/selectors';

/**
 * Smoke test QR E-Ticket: hasil scan harus membuka halaman publik yang
 * ringan dan tidak membocorkan data pribadi client.
 */
test.describe('Publik — Verifikasi E-Ticket', () => {
    test('QR → halaman verifikasi tanpa login & tanpa data sensitif', async ({
        page,
        asClient,
        seed,
    }) => {
        await asClient();

        const unik = Date.now();
        const pkg = await seed.createPackage(page, {
            mitra_email: SEED_USERS.mitra.email,
            nama: `Paket QR E2E ${unik}`,
            status: 'aktif',
            durasi_hari: 1,
            durasi_malam: 0,
            harga_per_orang: 200_000,
        });
        const booking = await seed.createBooking(page, {
            client_email: SEED_USERS.client.email,
            package_kode: pkg.kode,
            status: 'confirmed',
            tanggal_keberangkatan: getDateOffset(7),
            jumlah_peserta: 1,
            participants: [{ nama: 'Peserta QR' }],
        });
        const trip = await seed.createTrip(page, booking.kode);
        const ticket = await seed.generateETicket(page, trip.kode);

        // Halaman verifikasi sengaja bisa dibuka tanpa sesi login.
        await page.goto(`/e-tickets/${ticket.uuid}`);

        await expect(
            page.getByRole('heading', { name: pkg.nama }),
        ).toBeVisible();
        await expect(page.getByText('E-Ticket Berlaku')).toBeVisible();

        const isi = await page.locator('body').innerText();
        expect(isi).not.toContain(SEED_USERS.client.email);
        expect(isi).not.toContain('Peserta QR');
    });
});
