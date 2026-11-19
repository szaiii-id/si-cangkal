import { test, expect, SEED_USERS } from '../fixtures/base';

function todayInMakassar(): string {
    return new Intl.DateTimeFormat('en-CA', {
        timeZone: 'Asia/Makassar',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).format(new Date());
}

test.describe('E2E Flow — Siklus Aktivasi & Penyelesaian Trip (Scheduler trips:activate)', () => {
    test('trip scheduled yang memasuki tanggal keberangkatan diaktivasi oleh trips:activate dan sinkron ke Mitra & Client', async ({
        page,
        asMitra,
        asClient,
        seed,
    }) => {
        const runId = Date.now();
        const packageNama = `Paket Aktivasi Trip ${runId}`;

        // 1. Seed paket aktif + booking confirmed dengan tanggal keberangkatan hari ini
        const pkg = await seed.createPackage(page, {
            mitra_email: SEED_USERS.mitra.email,
            nama: packageNama,
            status: 'aktif',
            durasi_hari: 1,
            durasi_malam: 0,
            harga_per_orang: 400_000,
            timezone: 'Asia/Makassar',
            days: [{ judul: 'Hari 1: Eksplorasi Wisata', lokasi: 'Banjarmasin' }],
            facilities: [{ tipe: 'include', nama: 'Transportasi & Makan' }],
        });

        const booking = await seed.createBooking(page, {
            client_email: SEED_USERS.client.email,
            package_kode: pkg.kode,
            status: 'confirmed',
            tanggal_keberangkatan: todayInMakassar(),
            jumlah_peserta: 2,
            participants: [
                { nama: 'Peserta Aktivasi 1', umur: 26 },
                { nama: 'Peserta Aktivasi 2', umur: 28 },
            ],
        });

        const trip = await seed.createTrip(page, booking.kode);

        // 2. Pastikan sebelum command dijalankan, status trip masih Terjadwal (scheduled)
        await asMitra();
        await page.goto(`/mitra/trips/${trip.trip_id}`, {
            waitUntil: 'domcontentloaded',
        });
        await expect(page.getByText(/Terjadwal/i).first()).toBeVisible({
            timeout: 10_000,
        });

        // 3. Jalankan scheduler command `trips:activate` melalui endpoint test
        const activateRes = await page.request.post('/_test/run-command', {
            data: { command: 'trips:activate' },
        });
        expect(activateRes.status()).toBe(200);
        const activateJson = await activateRes.json();
        expect(activateJson.ok).toBe(true);

        // 4. Verifikasi di sisi Mitra (/mitra/trips & /mitra/trips/{id}): status berubah menjadi Sedang Berjalan (active) + badge Hari ini
        await page.goto('/mitra/trips', { waitUntil: 'domcontentloaded' });
        const searchInput = page.getByPlaceholder(
            'Cari kode trip atau nama paket...',
        );
        await expect(searchInput).toBeVisible();

        // Tunggu response pencarian debounce selesai sebelum klik kartu trip
        await Promise.all([
            page.waitForResponse(
                (res) =>
                    res.url().includes('/mitra/trips') &&
                    res.url().includes(`search=${encodeURIComponent(trip.kode)}`) &&
                    res.request().method() === 'GET',
            ),
            searchInput.fill(trip.kode),
        ]);

        const tripCard = page.getByRole('link').filter({ hasText: trip.kode });
        await expect(tripCard).toBeVisible({ timeout: 10_000 });
        await expect(tripCard.getByText(/Sedang Berjalan|Aktif/i)).toBeVisible();
        await expect(tripCard.getByText('Hari ini')).toBeVisible();

        await Promise.all([
            page.waitForURL(new RegExp(`/mitra/trips/${trip.trip_id}$`), {
                timeout: 20_000,
                waitUntil: 'commit',
            }),
            tripCard.click(),
        ]);
        await expect(page.getByText(/Sedang Berjalan|Aktif/i).first()).toBeVisible({
            timeout: 10_000,
        });

        // 5. Verifikasi di sisi Client (/client/bookings/{id}): status booking ikut sinkron (Sedang Berjalan / Dikonfirmasi)
        await asClient();
        await page.goto(`/client/bookings/${booking.booking_id}`, {
            waitUntil: 'domcontentloaded',
        });
        await expect(page.getByText(booking.kode).first()).toBeVisible({
            timeout: 10_000,
        });
        await expect(
            page.getByText(/Sedang Berjalan|Dikonfirmasi|Aktif/i).first(),
        ).toBeVisible();

        // 6. Simulasikan penyelesaian trip (`completed`) dan verifikasi status Selesai di panel Mitra
        const completeRes = await page.request.post('/_test/reset-trip', {
            data: { trip_id: trip.trip_id, status: 'completed' },
        });
        expect(completeRes.status()).toBe(200);

        await asMitra();
        await page.goto(`/mitra/trips/${trip.trip_id}`, {
            waitUntil: 'domcontentloaded',
        });
        await expect(page.getByText(/Selesai/i).first()).toBeVisible({
            timeout: 10_000,
        });
    });
});