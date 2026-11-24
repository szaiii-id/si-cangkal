// e2e/security/cross-user-access.spec.ts
import { test, expect, SEED_USERS } from '../fixtures/base';
import { apiPost } from '../fixtures/api';

test.describe('Security — Cross-User Data Isolation (IDOR Protection)', () => {
    const runId = Date.now();
    let packageMitra1Kode = '';
    let packageMitra1Id = 0;
    let bookingClient1Id = 0;
    let bookingClient1Kode = '';
    let tripMitra1Id = 0;
    let eTicketUuid = '';

    const otherClientEmail = `client-idor-${runId}@test.com`;
    const otherMitraEmail = `mitra-idor-${runId}@test.com`;

    function futureDate(daysAhead: number): string {
        const d = new Date();
        d.setDate(d.getDate() + daysAhead);
        return d.toISOString().split('T')[0];
    }

    test.beforeAll(async ({ browser }) => {
        const context = await browser.newContext();
        const page = await context.newPage();
        const { seed } = await import('../fixtures/seed');

        // 1. Buat user Client lain & Mitra lain
        await seed.createUser(page, {
            email: otherClientEmail,
            name: 'Client IDOR Tester',
            role: 'client',
            password: 'password123',
        });

        await seed.createUser(page, {
            email: otherMitraEmail,
            name: 'Mitra IDOR Tester',
            role: 'mitra',
            password: 'password123',
            with_bank_account: true,
        });

        // 2. Buat Paket milik Mitra Utama (mitra@test.com)
        const pkg = await seed.createPackage(page, {
            mitra_email: SEED_USERS.mitra.email,
            nama: `Paket IDOR Test ${runId}`,
            status: 'aktif',
            durasi_hari: 1,
            durasi_malam: 0,
            harga_per_orang: 250_000,
            days: [
                {
                    judul: 'Hari 1',
                    jam_mulai: '08:00',
                    jam_selesai: '15:00',
                    lokasi: 'Banjarmasin',
                },
            ],
            facilities: [
                { tipe: 'include', nama: 'Guide' },
                { tipe: 'exclude', nama: 'Tiket Pesawat' },
            ],
        });
        packageMitra1Kode = pkg.kode;
        packageMitra1Id = pkg.package_id;

        // 3. Buat Booking milik Client Utama (client@test.com) yang sudah confirmed + Trip + E-Ticket
        const booking = await seed.createBooking(page, {
            client_email: SEED_USERS.client.email,
            package_kode: packageMitra1Kode,
            status: 'confirmed',
            tanggal_keberangkatan: futureDate(10),
            jumlah_peserta: 1,
            participants: [{ nama: 'Peserta IDOR 1' }],
        });
        bookingClient1Id = booking.booking_id;
        bookingClient1Kode = booking.kode;

        const trip = await seed.createTrip(page, bookingClient1Kode);
        tripMitra1Id = trip.trip_id;

        const ticket = await seed.generateETicket(page, trip.kode);
        eTicketUuid = ticket.uuid;

        await context.close();
    });

    // ==========================================
    // CLIENT A vs CLIENT B
    // ==========================================
    test('Client lain tidak bisa melihat detail booking milik Client utama (403)', async ({
        page,
        seed,
    }) => {
        await seed.login(page, otherClientEmail, 'password123');
        const response = await page.goto(
            `/client/bookings/${bookingClient1Id}`,
        );
        expect(response?.status()).toBe(403);
    });

    test('Client lain tidak bisa mengunduh E-Ticket milik Client utama (403)', async ({
        page,
        seed,
    }) => {
        await seed.login(page, otherClientEmail, 'password123');
        const response = await page.goto(
            `/client/e-tickets/${eTicketUuid}/download`,
        );
        expect(response?.status()).toBe(403);
    });

    test('Client lain tidak bisa membatalkan booking milik Client utama (403)', async ({
        page,
        seed,
    }) => {
        await seed.login(page, otherClientEmail, 'password123');
        await page.goto('/client/dashboard');

        let status = 0;
        try {
            await apiPost(page, `/client/bookings/${bookingClient1Id}/cancel`, {
                reason: 'Mencoba cancel booking orang lain',
            });
        } catch (err: unknown) {
            status = (err as { status?: number }).status ?? 0;
        }
        expect(status).toBe(403);
    });

    // ==========================================
    // MITRA A vs MITRA B
    // ==========================================
    test('Mitra lain tidak bisa melihat atau mengedit paket milik Mitra utama (403)', async ({
        page,
        seed,
    }) => {
        await seed.login(page, otherMitraEmail, 'password123');

        const showRes = await page.goto(`/mitra/packages/${packageMitra1Id}`);
        expect(showRes?.status()).toBe(403);

        const editRes = await page.goto(
            `/mitra/packages/${packageMitra1Id}/edit`,
        );
        expect(editRes?.status()).toBe(403);
    });

    test('Mitra lain tidak bisa melihat detail atau membatalkan booking milik Mitra utama (403)', async ({
        page,
        seed,
    }) => {
        await seed.login(page, otherMitraEmail, 'password123');

        const showRes = await page.goto(`/mitra/bookings/${bookingClient1Id}`);
        expect(showRes?.status()).toBe(403);

        let cancelStatus = 0;
        try {
            await apiPost(page, `/mitra/bookings/${bookingClient1Id}/cancel`, {
                reason: 'Mencoba cancel booking mitra lain',
            });
        } catch (err: unknown) {
            cancelStatus = (err as { status?: number }).status ?? 0;
        }
        expect(cancelStatus).toBe(403);
    });

    test('Mitra lain tidak bisa melihat detail atau manifest trip milik Mitra utama (403)', async ({
        page,
        seed,
    }) => {
        await seed.login(page, otherMitraEmail, 'password123');

        const showRes = await page.goto(`/mitra/trips/${tripMitra1Id}`);
        expect(showRes?.status()).toBe(403);

        const manifestRes = await page.goto(
            `/mitra/trips/${tripMitra1Id}/manifest`,
        );
        expect(manifestRes?.status()).toBe(403);
    });
});
