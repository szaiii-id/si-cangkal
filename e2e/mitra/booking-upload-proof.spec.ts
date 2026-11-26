import { test, expect, SEED_USERS } from '../fixtures/base';
import { getDateOffset } from '../fixtures/selectors';
import { VALID_JPEG_BUFFER, DUMMY_PDF_BUFFER } from '../fixtures/files';
import type { Page } from '@playwright/test';

async function dismissNotificationPrompt(page: Page): Promise<void> {
    await page.addLocatorHandler(
        page.getByRole('dialog', { name: /Aktifkan Notifikasi/i }),
        async () => {
            await page.getByRole('button', { name: /Nanti Saja/i }).click();
        },
    );
}

test.describe('Mitra — Upload Bukti Transfer Pesanan Manual', () => {
    // ==========================================
    // 1. ACCESS CONTROL & AUTHORIZATION
    // ==========================================

    test.describe('Access Control', () => {
        test('mitra lain ditolak (403) mengunggah bukti transfer pada booking bukan miliknya', async ({
            page,
            seed,
        }) => {
            await dismissNotificationPrompt(page);

            const pkg = await seed.createPackage(page, {
                mitra_email: SEED_USERS.mitra.email,
                nama: `Paket Upload Mitra Auth ${Date.now()}`,
                status: 'aktif',
                durasi_hari: 1,
                durasi_malam: 0,
                harga_per_orang: 300_000,
                days: [{ judul: 'Hari 1: Wisata', lokasi: 'Banjarmasin' }],
                facilities: [{ tipe: 'include', nama: 'Snack' }],
            });

            const booking = await seed.createBooking(page, {
                client_email: SEED_USERS.client.email,
                package_kode: pkg.kode,
                status: 'pending_payment',
                tanggal_keberangkatan: getDateOffset(6),
                jumlah_peserta: 1,
                participants: [{ nama: 'Peserta Auth Upload', umur: 25 }],
            });

            const otherMitraEmail = `mitra-other-upload-${Date.now()}@test.com`;
            await seed.createUser(page, {
                email: otherMitraEmail,
                name: 'Mitra Lain Upload',
                role: 'mitra',
                password: 'password123',
                with_bank_account: true,
            });

            await seed.login(page, otherMitraEmail, 'password123');
            const response = await page.goto(
                `/mitra/bookings/${booking.booking_id}`,
            );
            expect(response?.status()).toBe(403);
        });
    });

    // ==========================================
    // 2. VALIDASI & UPLOAD BUKTI PEMBAYARAN
    // ==========================================

    test.describe('Validasi & Upload Bukti Pembayaran', () => {
        test('menolak file non-gambar (PDF) dan ukuran > 5MB, lalu berhasil upload gambar valid hingga tombol verifikasi muncul', async ({
            page,
            asMitra,
            seed,
        }) => {
            await dismissNotificationPrompt(page);
            await asMitra();

            const pkg = await seed.createPackage(page, {
                mitra_email: SEED_USERS.mitra.email,
                nama: `Paket Upload Mitra E2E ${Date.now()}`,
                status: 'aktif',
                durasi_hari: 1,
                durasi_malam: 0,
                harga_per_orang: 350_000,
                days: [{ judul: 'Hari 1: Wisata', lokasi: 'Banjarbaru' }],
                facilities: [{ tipe: 'include', nama: 'Makan' }],
            });

            const booking = await seed.createBooking(page, {
                client_email: SEED_USERS.client.email,
                package_kode: pkg.kode,
                status: 'pending_payment',
                tanggal_keberangkatan: getDateOffset(6),
                jumlah_peserta: 1,
                participants: [{ nama: 'Peserta Upload Mitra', umur: 27 }],
            });

            await page.goto(`/mitra/bookings/${booking.booking_id}`);
            await page.waitForLoadState('networkidle');

            // Pastikan kartu Kirim Bukti Transfer muncul
            await expect(
                page.getByRole('heading', { name: 'Kirim Bukti Transfer' }),
            ).toBeVisible();

            const fileInput = page.locator('input[type="file"]').first();
            const alertBox = page.getByRole('alert').first();
            const uploadBtn = page.getByRole('button', {
                name: 'Kirim Bukti',
            });

            // 1. Uji file PDF (validasi client-side langsung menampilkan alert & tombol Unggah Bukti tidak muncul)
            await fileInput.setInputFiles({
                name: 'bukti-invalid.pdf',
                mimeType: 'application/pdf',
                buffer: DUMMY_PDF_BUFFER,
            });
            await expect(alertBox).toBeVisible();
            await expect(uploadBtn).toHaveCount(0);

            // 2. Uji file > 5MB (validasi client-side langsung menampilkan alert & tombol Unggah Bukti tidak muncul)
            const oversizedJpeg = Buffer.concat([
                VALID_JPEG_BUFFER,
                Buffer.alloc(6 * 1024 * 1024),
            ]);
            await fileInput.setInputFiles({
                name: 'bukti-oversized.jpg',
                mimeType: 'image/jpeg',
                buffer: oversizedJpeg,
            });
            await expect(alertBox).toBeVisible();
            await expect(uploadBtn).toHaveCount(0);

            // 3. Upload gambar JPEG valid -> preview & tombol "Unggah Bukti" muncul -> submit
            await fileInput.setInputFiles({
                name: 'bukti-transfer-valid.jpg',
                mimeType: 'image/jpeg',
                buffer: VALID_JPEG_BUFFER,
            });

            await expect(
                page.getByAltText('Preview bukti transfer'),
            ).toBeVisible();
            await expect(uploadBtn).toBeVisible();

            await Promise.all([
                page.waitForResponse(
                    (res) =>
                        res
                            .url()
                            .includes(
                                `/mitra/bookings/${booking.booking_id}/upload-proof`,
                            ) && res.request().method() === 'POST',
                ),
                uploadBtn.click(),
            ]);

            await page.waitForLoadState('networkidle');

            // Setelah bukti terunggah, VerifyPaymentCard muncul (tombol Verifikasi / Tolak)
            await expect(
                page.getByRole('button', { name: /Verifikasi/i }).first(),
            ).toBeVisible();
            await expect(
                page.getByRole('button', { name: /Tolak/i }).first(),
            ).toBeVisible();
        });
    });
});
