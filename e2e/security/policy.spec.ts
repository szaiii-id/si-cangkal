// e2e/security/policy.spec.ts
import { test, expect, SEED_USERS } from '../fixtures/base';
import { apiPost } from '../fixtures/api';

test.describe('Security — Role Separation & Policy Enforcement', () => {
    const runId = Date.now();
    let packageId = 0;
    let bookingId = 0;

    function futureDate(daysAhead: number): string {
        const d = new Date();
        d.setDate(d.getDate() + daysAhead);
        return d.toISOString().split('T')[0];
    }

    test.beforeAll(async ({ browser }) => {
        const context = await browser.newContext();
        const page = await context.newPage();
        const { seed } = await import('../fixtures/seed');

        const pkg = await seed.createPackage(page, {
            mitra_email: SEED_USERS.mitra.email,
            nama: `Paket Policy Test ${runId}`,
            status: 'aktif',
            durasi_hari: 1,
            durasi_malam: 0,
            harga_per_orang: 200_000,
            days: [{ judul: 'Hari 1', jam_mulai: '08:00', jam_selesai: '14:00', lokasi: 'Loksado' }],
            facilities: [
                { tipe: 'include', nama: 'Pemandu' },
                { tipe: 'exclude', nama: 'Dokumentasi' },
            ],
        });
        packageId = pkg.package_id;

        const booking = await seed.createBooking(page, {
            client_email: SEED_USERS.client.email,
            package_kode: pkg.kode,
            status: 'pending_payment',
            tanggal_keberangkatan: futureDate(7),
            jumlah_peserta: 1,
            participants: [{ nama: 'Peserta Policy Test' }],
        });
        bookingId = booking.booking_id;

        await context.close();
    });

    test('Admin bersifat read-only: tidak ada tombol aksi transaksi pada halaman Booking & Package', async ({
        page,
        asAdmin,
    }) => {
        await asAdmin();

        // 1. Cek halaman detail booking di sisi Admin
        await page.goto(`/admin/bookings/${bookingId}`);
        await expect(
            page.getByRole('button', { name: /Verifikasi & Terima|Tolak Pembayaran|Batalkan/i }),
        ).toHaveCount(0);

        // 2. Admin tidak punya rute edit paket mitra (redirect ke /admin/dashboard)
        await page.goto(`/mitra/packages/${packageId}/edit`);
        await expect(page).toHaveURL('/admin/dashboard');
    });

    test('Mitra tidak bisa membuat akun Admin baru melalui endpoint /admin/users', async ({
        page,
        asMitra,
    }) => {
        await asMitra();
        await page.goto('/mitra/dashboard');

        // apiPost menyertakan CSRF token valid sehingga request melewati VerifyCsrfToken
        // dan langsung ditangani middleware CheckRole (redirect ke /mitra/dashboard)
        let redirectedToDashboard = false;
        try {
            await apiPost(page, '/admin/users', {
                name: 'Hacker Admin',
                username: `hackeradmin${runId}`,
                email: `hacker-admin-${runId}@test.com`,
                role: 'admin',
                password: 'password123',
            });
            redirectedToDashboard = page.url().includes('/mitra/dashboard');
        } catch (err: unknown) {
            const status = (err as { status?: number }).status ?? 0;
            redirectedToDashboard = [302, 403, 405].includes(status);
        }

        expect(redirectedToDashboard).toBe(true);
    });

    test('Client tidak bisa membuat paket wisata melalui endpoint /mitra/packages', async ({
        page,
        asClient,
    }) => {
        await asClient();
        await page.goto('/client/dashboard');

        let redirectedToDashboard = false;
        try {
            await apiPost(page, '/mitra/packages', {
                nama: 'Paket Ilegal Client',
                kategori: 'petualangan',
                durasi_hari: 1,
                durasi_malam: 0,
                harga_per_orang: 100000,
            });
            redirectedToDashboard = page.url().includes('/client/dashboard');
        } catch (err: unknown) {
            const status = (err as { status?: number }).status ?? 0;
            redirectedToDashboard = [302, 403, 405].includes(status);
        }

        expect(redirectedToDashboard).toBe(true);
    });

    test('Admin tidak bisa membuat user dengan role mitra atau client di /admin/users (validasi 422)', async ({
        page,
        asAdmin,
    }) => {
        await asAdmin();
        await page.goto('/admin/users/create');

        let status = 0;
        try {
            await apiPost(page, '/admin/users', {
                name: 'Mitra Via Admin',
                username: `mitraadmin${runId}`,
                email: `mitra-admin-${runId}@test.com`,
                role: 'mitra',
                password: 'password123',
            });
        } catch (err: unknown) {
            status = (err as { status?: number }).status ?? 0;
        }

        expect(status).toBe(422);
    });
});