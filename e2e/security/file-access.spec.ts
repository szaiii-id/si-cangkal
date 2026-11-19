// e2e/security/file-access.spec.ts
import { test, expect, SEED_USERS } from '../fixtures/base';

test.describe('Security — Private File & Storage Access Control', () => {
    const runId = Date.now();
    let bookingId = 0;
    let eTicketUuid = '';

    const unauthorizedClientEmail = `client-file-${runId}@test.com`;

    function futureDate(daysAhead: number): string {
        const d = new Date();
        d.setDate(d.getDate() + daysAhead);
        return d.toISOString().split('T')[0];
    }

    test.beforeAll(async ({ browser }) => {
        const context = await browser.newContext();
        const page = await context.newPage();
        const { seed } = await import('../fixtures/seed');

        await seed.createUser(page, {
            email: unauthorizedClientEmail,
            name: 'File Unauthorized Tester',
            role: 'client',
            password: 'password123',
        });

        const pkg = await seed.createPackage(page, {
            mitra_email: SEED_USERS.mitra.email,
            nama: `Paket File Security ${runId}`,
            status: 'aktif',
            durasi_hari: 1,
            durasi_malam: 0,
            harga_per_orang: 300_000,
            days: [{ judul: 'Hari 1', jam_mulai: '09:00', jam_selesai: '15:00', lokasi: 'Martapura' }],
            facilities: [
                { tipe: 'include', nama: 'Transportasi' },
                { tipe: 'exclude', nama: 'Makan' },
            ],
        });

        const booking = await seed.createBooking(page, {
            client_email: SEED_USERS.client.email,
            package_kode: pkg.kode,
            status: 'confirmed',
            tanggal_keberangkatan: futureDate(6),
            jumlah_peserta: 1,
            participants: [{ nama: 'Peserta File Test' }],
        });
        bookingId = booking.booking_id;

        const trip = await seed.createTrip(page, booking.kode);
        const ticket = await seed.generateETicket(page, trip.kode);
        eTicketUuid = ticket.uuid;

        await context.close();
    });

    test('Guest mengakses langsung /storage/private/* → 403/404 Blocked', async ({
        page,
        asGuest,
    }) => {
        await asGuest();
        const response = await page.goto('/storage/private/payment-proofs/sample.jpg');
        expect([403, 404]).toContain(response?.status());
    });

    test('Guest mengakses endpoint download E-Ticket → redirect ke /login', async ({
        page,
        asGuest,
    }) => {
        await asGuest();
        await page.goto(`/client/e-tickets/${eTicketUuid}/download`);
        await expect(page).toHaveURL(/\/login$/);
    });

    test('Guest mengakses endpoint payment-proof Admin/Client/Mitra → redirect ke /login', async ({
        page,
        asGuest,
    }) => {
        await asGuest();

        await page.goto(`/admin/bookings/${bookingId}/payment-proof`);
        await expect(page).toHaveURL(/\/login$/);

        await page.goto(`/client/bookings/${bookingId}/payment-proof`);
        await expect(page).toHaveURL(/\/login$/);

        await page.goto(`/mitra/bookings/${bookingId}/payment-proof`);
        await expect(page).toHaveURL(/\/login$/);
    });

    test('Client lain mengakses payment-proof milik Client utama → 403 Forbidden', async ({
        page,
        seed,
    }) => {
        await seed.login(page, unauthorizedClientEmail, 'password123');
        const response = await page.goto(`/client/bookings/${bookingId}/payment-proof`);
        expect(response?.status()).toBe(403);
    });

    test('Client mengakses E-Ticket dengan UUID acak/tidak valid → 404 Not Found', async ({
        page,
        asClient,
    }) => {
        await asClient();
        const fakeUuid = '00000000-0000-0000-0000-000000000000';
        const response = await page.goto(`/client/e-tickets/${fakeUuid}/download`);
        expect(response?.status()).toBe(404);
    });
});