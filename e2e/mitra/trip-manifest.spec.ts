import { test, expect, SEED_USERS } from '../fixtures/base';
import { getDateOffset } from '../fixtures/selectors';
import type { Page } from '@playwright/test';

async function dismissNotificationPrompt(page: Page): Promise<void> {
    await page.addLocatorHandler(
        page.getByRole('dialog', { name: /Aktifkan Notifikasi/i }),
        async () => {
            await page.getByRole('button', { name: /Nanti Saja/i }).click();
        },
    );
}

test.describe('Mitra — Manifest Peserta Trip', () => {
    // ==========================================
    // ACCESS CONTROL & ISOLASI ANTAR MITRA
    // ==========================================

    test.describe('Access Control', () => {
        test('guest, client, admin, dan mitra lain ditolak mengakses /mitra/trips/{id}/manifest', async ({
            page,
            asGuest,
            asClient,
            asAdmin,
            seed,
        }) => {
            await dismissNotificationPrompt(page);

            const runId = Date.now();
            const pkg = await seed.createPackage(page, {
                mitra_email: SEED_USERS.mitra.email,
                nama: `Paket Mitra Manifest Auth ${runId}`,
                status: 'aktif',
                durasi_hari: 1,
                durasi_malam: 0,
                harga_per_orang: 350_000,
                days: [{ judul: 'Hari 1: Eksplorasi Lokasi', lokasi: 'Banjarbaru' }],
                facilities: [{ tipe: 'include', nama: 'Dokumentasi & Snack' }],
            });

            const booking = await seed.createBooking(page, {
                client_email: SEED_USERS.client.email,
                package_kode: pkg.kode,
                status: 'confirmed',
                tanggal_keberangkatan: getDateOffset(7),
                jumlah_peserta: 1,
                participants: [
                    { nama: 'Peserta Auth Manifest', umur: 25, no_hp: '081233334444' },
                ],
            });

            const trip = await seed.createTrip(page, booking.kode);
            const manifestUrl = `/mitra/trips/${trip.trip_id}/manifest`;

            // Guest
            await asGuest();
            const guestRes = await page.request.get(manifestUrl, {
                maxRedirects: 0,
                failOnStatusCode: false,
            });
            expect([302, 401, 403]).toContain(guestRes.status());

            // Client
            await asClient();
            const clientRes = await page.request.get(manifestUrl, {
                maxRedirects: 0,
                failOnStatusCode: false,
            });
            expect([302, 403]).toContain(clientRes.status());

            // Admin
            await asAdmin();
            const adminRes = await page.request.get(manifestUrl, {
                maxRedirects: 0,
                failOnStatusCode: false,
            });
            expect([302, 403]).toContain(adminRes.status());

            // Mitra lain -> 403 Forbidden
            const otherMitraEmail = `mitra-other-manifest-${runId}@test.com`;
            await seed.createUser(page, {
                email: otherMitraEmail,
                name: 'Mitra Lain Manifest',
                role: 'mitra',
                password: 'password123',
                with_bank_account: true,
            });

            await seed.login(page, otherMitraEmail, 'password123');
            const otherMitraRes = await page.request.get(manifestUrl, {
                maxRedirects: 0,
                failOnStatusCode: false,
            });
            expect(otherMitraRes.status()).toBe(403);
        });
    });

    // ==========================================
    // CETAK & DOWNLOAD MANIFEST PESERTA
    // ==========================================

    test.describe('Cetak & Download Manifest Peserta', () => {
        test('menampilkan tombol Cetak Daftar Peserta pada detail trip dan mengembalikan file PDF manifest valid', async ({
            page,
            asMitra,
            seed,
        }) => {
            await dismissNotificationPrompt(page);
            await asMitra();

            const runId = Date.now();
            const packageNama = `Paket Mitra Manifest E2E ${runId}`;

            const pkg = await seed.createPackage(page, {
                mitra_email: SEED_USERS.mitra.email,
                nama: packageNama,
                status: 'aktif',
                durasi_hari: 1,
                durasi_malam: 0,
                harga_per_orang: 400_000,
                days: [{ judul: 'Hari 1: Susur Sungai', lokasi: 'Banjarmasin' }],
                facilities: [{ tipe: 'include', nama: 'Perahu Klotok & Guide' }],
            });

            const booking = await seed.createBooking(page, {
                client_email: SEED_USERS.client.email,
                package_kode: pkg.kode,
                status: 'confirmed',
                tanggal_keberangkatan: getDateOffset(10),
                jumlah_peserta: 2,
                participants: [
                    { nama: 'Peserta Manifest Satu', umur: 25, no_hp: '081233334444' },
                    { nama: 'Peserta Manifest Dua', umur: 27, no_hp: '081255556666' },
                ],
            });

            const trip = await seed.createTrip(page, booking.kode);

            await page.goto(`/mitra/trips/${trip.trip_id}`);
            await page.waitForLoadState('networkidle');

            await expect(page.getByText(trip.kode).first()).toBeVisible();
            await expect(
                page.getByRole('heading', { name: packageNama, level: 1 }),
            ).toBeVisible();

            // Verifikasi tombol Cetak Daftar Peserta dari TripHeaderCard
            const manifestLink = page.getByRole('link', {
                name: /Cetak daftar peserta/i,
            });
            await expect(manifestLink).toBeVisible();
            await expect(manifestLink).toHaveAttribute('target', '_blank');
            await expect(manifestLink).toHaveAttribute(
                'href',
                `/mitra/trips/${trip.trip_id}/manifest`,
            );

            // Verifikasi response stream PDF/HTML lintas browser (Chromium & Firefox)
            const response = await page.request.get(
                `/mitra/trips/${trip.trip_id}/manifest`,
            );
            expect(response.status()).toBe(200);

            const contentType = response.headers()['content-type'] ?? '';
            expect(contentType).toMatch(/application\/pdf|text\/html/);

            const body = await response.body();
            expect(body.byteLength).toBeGreaterThan(100);

            if (contentType.includes('application/pdf')) {
                expect(body.subarray(0, 4).toString()).toBe('%PDF');
            } else {
                const html = body.toString('utf-8');
                expect(html).toContain(trip.kode);
                expect(html).toContain('Peserta Manifest Satu');
                expect(html).toContain('Peserta Manifest Dua');
            }
        });
    });
});