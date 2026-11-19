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

function generateUniquePackageCode(workerIndex: number): string {
    const rand = Math.random().toString(36).slice(2, 7).toUpperCase();
    return `PKG-ADM-REF-${workerIndex}-${Date.now()}-${rand}`;
}

test.describe('Admin — Monitoring Pengembalian Dana (Read-Only)', () => {
    // ==========================================
    // 1. ACCESS CONTROL
    // ==========================================

    test.describe('Access Control', () => {
        test('guest, client, dan mitra ditolak mengakses /admin/refunds', async ({
            page,
            asGuest,
            asClient,
            asMitra,
        }) => {
            await dismissNotificationPrompt(page);

            await asGuest();
            await page.goto('/admin/refunds');
            await expect(page).toHaveURL(/\/login$/);

            await asClient();
            await page.goto('/admin/refunds');
            await expect(page).toHaveURL(/\/client\/dashboard$/);

            await asMitra();
            await page.goto('/admin/refunds');
            await expect(page).toHaveURL(/\/mitra\/dashboard$/);
        });
    });

    // ==========================================
    // 2. LISTING, FILTER & READ-ONLY ENFORCEMENT
    // ==========================================

    test.describe('Listing, Filter & Strict Read-Only', () => {
        test('menampilkan daftar refund dalam mode read-only tanpa tombol approve/reject, pencarian, dan filter status', async ({
            page,
            asAdmin,
            asMitra,
            seed,
        }, testInfo) => {
            await dismissNotificationPrompt(page);

            // 1. Setup: Buat paket & booking confirmed, lalu batalkan via Mitra agar menghasilkan Refund PENDING
            const uniqueKode = generateUniquePackageCode(testInfo.workerIndex);
            const pkg = await seed.createPackage(page, {
                mitra_email: SEED_USERS.mitra.email,
                kode: uniqueKode,
                nama: `Paket Admin Refund E2E ${uniqueKode}`,
                status: 'aktif',
                durasi_hari: 1,
                durasi_malam: 0,
                harga_per_orang: 400_000,
                days: [{ judul: 'Hari 1: Wisata', lokasi: 'Banjarbaru' }],
                facilities: [{ tipe: 'include', nama: 'Snack' }],
            });

            const booking = await seed.createBooking(page, {
                client_email: SEED_USERS.client.email,
                package_kode: pkg.kode,
                status: 'confirmed',
                tanggal_keberangkatan: getDateOffset(7),
                jumlah_peserta: 1,
                participants: [{ nama: 'Peserta Refund Admin', umur: 24 }],
            });

            // Batalkan booking sebagai Mitra melalui tombol "Batalkan & Refund"
            await asMitra();
            await page.goto(`/mitra/bookings/${booking.booking_id}`);
            await page.waitForLoadState('networkidle');

            await page.getByRole('button', { name: 'Batalkan & Refund' }).click();

            const dialog = page.getByRole('dialog');
            await expect(dialog).toBeVisible();

            const textInput = dialog.locator('textarea, input[type="text"]').first();
            if (await textInput.isVisible()) {
                await textInput.fill('Cuaca ekstrem di lokasi wisata');
            }

            await dialog
                .getByRole('button', { name: /Batalkan|Konfirmasi|Ya|Kirim|Proses/i })
                .last()
                .click();
            await page.waitForLoadState('networkidle');

            // 2. Login sebagai Admin & buka halaman /admin/refunds
            await asAdmin();
            await page.goto('/admin/refunds');
            await page.waitForLoadState('networkidle');

            // Heading utama di dalam <main>
            await expect(
                page.getByRole('main').getByRole('heading', { name: 'Pengembalian Dana' }),
            ).toBeVisible();

            // Pastikan card refund dengan kode booking tersebut tampil
            const refundCard = page
                .locator('article')
                .filter({ hasText: booking.kode })
                .first();
            await expect(refundCard).toBeVisible();
            await expect(refundCard.getByText('Butuh Tindakan Mitra')).toBeVisible();

            // Verifikasi strict read-only: tidak ada tombol Setujui / Tolak / Approve / Reject
            const mainArea = page.getByRole('main');
            await expect(
                mainArea.getByRole('button', { name: /Setujui|Tolak|Approve|Reject/i }),
            ).toHaveCount(0);

            // Uji link ke detail pesanan (/admin/bookings/{id}) tersedia di card
            const bookingLink = refundCard.getByRole('link', {
                name: new RegExp(booking.kode),
            });
            await expect(bookingLink).toHaveAttribute(
                'href',
                new RegExp(`/admin/bookings/${booking.booking_id}$`),
            );

            // Uji Pencarian by kode pesanan
            const searchInput = page.getByPlaceholder(
                'Cari kode pesanan atau nama pelanggan...',
            );
            await searchInput.fill(booking.kode);
            await expect(page).toHaveURL(new RegExp(`search=${booking.kode}`));
            await expect(refundCard).toBeVisible();

            // Uji Hapus Semua Filter
            await page.getByRole('button', { name: 'Hapus semua filter' }).click();
            await expect(searchInput).toHaveValue('');
        });
    });
});