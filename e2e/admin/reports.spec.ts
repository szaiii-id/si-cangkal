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

test.describe('Admin — Laporan Pendapatan & Kinerja Mitra', () => {
    // ==========================================
    // 1. ACCESS CONTROL
    // ==========================================

    test.describe('Access Control', () => {
        test('guest, client, dan mitra ditolak mengakses halaman dan endpoint export laporan', async ({
            page,
            asGuest,
            asClient,
            asMitra,
        }) => {
            await dismissNotificationPrompt(page);

            // Guest
            await asGuest();
            await page.goto('/admin/reports');
            await expect(page).toHaveURL(/\/login$/);

            // Client
            await asClient();
            await page.goto('/admin/reports');
            await expect(page).toHaveURL(/\/client\/dashboard$/);

            const clientPdfRes = await page.request.get(
                '/admin/reports/export-pdf',
                {
                    maxRedirects: 0,
                    failOnStatusCode: false,
                },
            );
            expect([302, 401, 403]).toContain(clientPdfRes.status());

            // Mitra
            await asMitra();
            await page.goto('/admin/reports/mitra');
            await expect(page).toHaveURL(/\/mitra\/dashboard$/);

            const mitraExcelRes = await page.request.get(
                '/admin/reports/export-excel',
                {
                    maxRedirects: 0,
                    failOnStatusCode: false,
                },
            );
            expect([302, 401, 403]).toContain(mitraExcelRes.status());
        });
    });

    // ==========================================
    // 2. LAPORAN PENDAPATAN (/admin/reports)
    // ==========================================

    test.describe('Laporan Pendapatan & Export', () => {
        test('menampilkan summary pendapatan, grafik tren, rincian transaksi, dan validasi filter tanggal', async ({
            page,
            asAdmin,
            seed,
        }) => {
            await dismissNotificationPrompt(page);
            await asAdmin();

            const runId = Date.now();
            const packageNama = `Paket Report Rev ${runId}`;

            const pkg = await seed.createPackage(page, {
                mitra_email: SEED_USERS.mitra.email,
                nama: packageNama,
                status: 'aktif',
                durasi_hari: 1,
                durasi_malam: 0,
                harga_per_orang: 500_000,
                days: [{ judul: 'Hari 1: Wisata', lokasi: 'Banjarmasin' }],
                facilities: [{ tipe: 'include', nama: 'Makan' }],
            });

            const booking = await seed.createBooking(page, {
                client_email: SEED_USERS.client.email,
                package_kode: pkg.kode,
                status: 'confirmed',
                tanggal_keberangkatan: getDateOffset(7),
                jumlah_peserta: 2,
                participants: [
                    { nama: 'Peserta Rev 1', umur: 25 },
                    { nama: 'Peserta Rev 2', umur: 26 },
                ],
            });

            await page.goto('/admin/reports');
            await page.waitForLoadState('networkidle');

            // Heading utama di dalam <main> & Navigasi Tab aktif
            await expect(
                page
                    .getByRole('main')
                    .getByRole('heading', { name: 'Laporan Pendapatan' }),
            ).toBeVisible();
            await expect(
                page
                    .getByRole('navigation', { name: 'Navigasi Laporan' })
                    .getByRole('link', { name: 'Pendapatan' }),
            ).toHaveAttribute('aria-current', 'page');

            // Summary & Grafik Tren
            await expect(page.getByText('Total Pendapatan')).toBeVisible();
            await expect(page.getByText('Tren Pendapatan')).toBeVisible();
            await expect(page.locator('.fc-kbd-nav')).toBeAttached();

            // Tabel Rincian Transaksi (Desktop)
            const table = page.getByRole('table', {
                name: 'Rincian Transaksi',
            });
            await expect(table.getByText(booking.kode)).toBeVisible();
            await expect(table.getByText(packageNama)).toBeVisible();

            // Uji validasi client-side saat tanggal "Dari" > "Sampai"
            const startInput = page.getByLabel('Dari Tanggal');
            const endInput = page.getByLabel('Sampai Tanggal');
            const applyBtn = page.getByRole('button', {
                name: 'Terapkan filter tanggal',
            });

            await startInput.fill('2026-11-25');
            await endInput.fill('2026-11-10');

            await expect(
                page.getByRole('alert').filter({
                    hasText:
                        'Tanggal "Dari" tidak boleh lebih besar dari tanggal "Sampai".',
                }),
            ).toBeVisible();
            await expect(applyBtn).toBeDisabled();

            // Kembalikan tanggal valid & terapkan filter
            const todayStr = new Date().toISOString().slice(0, 10);
            await startInput.fill(todayStr);
            await endInput.fill(todayStr);
            await expect(applyBtn).toBeEnabled();

            await applyBtn.click();
            await expect(page).toHaveURL(new RegExp(`start_date=${todayStr}`));
            await expect(table.getByText(booking.kode)).toBeVisible();
        });

        test('export PDF dan export Excel (download .xlsx) laporan pendapatan berfungsi', async ({
            page,
            asAdmin,
        }) => {
            await dismissNotificationPrompt(page);
            await asAdmin();

            await page.goto('/admin/reports');
            await page.waitForLoadState('networkidle');

            // 1. Export Excel -> memicu download file .xlsx
            const excelLink = page.getByRole('link', { name: 'Export Excel' });
            await expect(excelLink).toBeVisible();

            const [excelDownload] = await Promise.all([
                page.waitForEvent('download'),
                excelLink.click(),
            ]);
            expect(excelDownload.suggestedFilename()).toMatch(
                /^Laporan-Pendapatan-\d{4}-\d{2}-\d{2}\.xlsx$/,
            );

            // 2. Export PDF -> verifikasi URL & response stream application/pdf (lintas browser)
            const pdfLink = page.getByRole('link', { name: 'Export PDF' });
            await expect(pdfLink).toBeVisible();
            await expect(pdfLink).toHaveAttribute('target', '_blank');

            const pdfHref = await pdfLink.getAttribute('href');
            expect(pdfHref).toContain('/admin/reports/export-pdf');

            const pdfResponse = await page.request.get(pdfHref!);
            expect(pdfResponse.status()).toBe(200);
            expect(pdfResponse.headers()['content-type']).toContain(
                'application/pdf',
            );
        });
    });

    // ==========================================
    // 3. LAPORAN KINERJA MITRA (/admin/reports/mitra)
    // ==========================================

    test.describe('Laporan Kinerja Mitra & Export', () => {
        test('berpindah tab ke Kinerja Mitra, menampilkan statistik & tabel peringkat mitra, serta export PDF/Excel', async ({
            page,
            asAdmin,
        }) => {
            await dismissNotificationPrompt(page);
            await asAdmin();

            await page.goto('/admin/reports');
            await page.waitForLoadState('networkidle');

            // Klik tab Kinerja Mitra
            await page
                .getByRole('navigation', { name: 'Navigasi Laporan' })
                .getByRole('link', { name: 'Kinerja Mitra' })
                .click();

            await expect(page).toHaveURL(/\/admin\/reports\/mitra/);
            await page.waitForLoadState('networkidle');

            // Kartu Ringkasan Mitra
            await expect(page.getByText('Total Mitra Aktif')).toBeVisible();
            await expect(page.getByText('Trip Selesai').first()).toBeVisible();

            // Tabel Peringkat Kinerja Mitra
            const mitraTable = page.getByRole('table', {
                name: 'Peringkat Kinerja Mitra',
            });
            await expect(mitraTable).toBeVisible();
            await expect(
                mitraTable.getByText(SEED_USERS.mitra.email),
            ).toBeVisible();

            // 1. Export Excel Kinerja Mitra
            const [excelDownload] = await Promise.all([
                page.waitForEvent('download'),
                page.getByRole('link', { name: 'Export Excel' }).click(),
            ]);
            expect(excelDownload.suggestedFilename()).toMatch(
                /^Laporan-Kinerja-Mitra-\d{4}-\d{2}-\d{2}\.xlsx$/,
            );

            // 2. Export PDF Kinerja Mitra
            const pdfLink = page.getByRole('link', { name: 'Export PDF' });
            await expect(pdfLink).toBeVisible();
            await expect(pdfLink).toHaveAttribute('target', '_blank');

            const pdfHref = await pdfLink.getAttribute('href');
            expect(pdfHref).toContain('/admin/reports/mitra/export-pdf');

            const pdfResponse = await page.request.get(pdfHref!);
            expect(pdfResponse.status()).toBe(200);
            expect(pdfResponse.headers()['content-type']).toContain(
                'application/pdf',
            );
        });
    });
});
