import { test, expect, SEED_USERS } from '../fixtures/base';
import { getDateOffset } from '../fixtures/selectors';

test.describe('Client — Dashboard (Beranda)', () => {
    // ==========================================
    // 1. ACCESS CONTROL
    // ==========================================

    test.describe('Access Control', () => {
        test('guest, mitra, dan admin ditolak mengakses /client/dashboard', async ({
            page,
            asGuest,
            asMitra,
            asAdmin,
        }) => {
            await asGuest();
            await page.goto('/client/dashboard');
            await expect(page).toHaveURL(/\/login$/);

            await asMitra();
            await page.goto('/client/dashboard');
            await expect(page).toHaveURL(/\/mitra\/dashboard$/);

            await asAdmin();
            await page.goto('/client/dashboard');
            await expect(page).toHaveURL(/\/admin\/dashboard$/);
        });
    });

    // ==========================================
    // 2. HERO, PAKET TERBARU & BEST SELLER
    // ==========================================

    test.describe('Hero, Paket Terbaru & Paling Laku', () => {
        test('menampilkan hero sapaan client, section Paket Terbaru, Paling Laku, dan navigasi ke detail paket', async ({
            page,
            asClient,
            seed,
        }) => {
            await asClient();

            const runId = Date.now();
            const packageNama = `Paket Dash Client ${runId}`;

            const pkg = await seed.createPackage(page, {
                mitra_email: SEED_USERS.mitra.email,
                nama: packageNama,
                status: 'aktif',
                durasi_hari: 2,
                durasi_malam: 1,
                harga_per_orang: 450_000,
                days: [
                    { judul: 'Hari 1: Kedatangan', lokasi: 'Banjarmasin' },
                    { judul: 'Hari 2: Wisata Sungai', lokasi: 'Lok Baintan' },
                ],
                facilities: [{ tipe: 'include', nama: 'Hotel & Makan' }],
            });

            // Buat booking confirmed agar paket juga terhitung dalam daftar paket laris
            await seed.createBooking(page, {
                client_email: SEED_USERS.client.email,
                package_kode: pkg.kode,
                status: 'confirmed',
                tanggal_keberangkatan: getDateOffset(7),
                jumlah_peserta: 2,
                participants: [
                    { nama: 'Peserta Client Dash 1', umur: 25 },
                    { nama: 'Peserta Client Dash 2', umur: 26 },
                ],
            });

            await page.goto('/client/dashboard');
            await page.waitForLoadState('networkidle');

            const mainArea = page.getByRole('main');

            // 1. Hero Section
            await expect(
                mainArea.getByRole('heading', { name: new RegExp(`Halo, ${SEED_USERS.client.name}`) }),
            ).toBeVisible();
            const exploreLink = mainArea.getByRole('link', { name: 'Jelajahi Katalog' });
            await expect(exploreLink).toBeVisible();
            await expect(exploreLink).toHaveAttribute('href', /\/client\/catalog$/);

            // 2. Section Paket Terbaru & Paling Laku (Best Seller)
            await expect(
                mainArea.getByRole('heading', { name: 'Paket Terbaru' }),
            ).toBeVisible();
            await expect(
                mainArea.getByRole('heading', { name: /Paling Laku/i }),
            ).toBeVisible();

            // 3. Klik kartu paket yang baru dibuat -> navigasi ke halaman detail katalog paket
            const pkgLink = mainArea.getByRole('link', { name: new RegExp(packageNama) }).first();
            await expect(pkgLink).toBeVisible();

            await Promise.all([
                page.waitForURL(/\/client\/catalog\/.+/, { timeout: 20_000 }),
                pkgLink.click(),
            ]);

            await expect(page.getByRole('heading', { name: packageNama })).toBeVisible();
        });
    });
});