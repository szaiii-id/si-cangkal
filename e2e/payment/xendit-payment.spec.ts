import { test, expect } from '../fixtures/base';
import { XENDIT } from '../config';
import { SEED_USERS } from '../fixtures/base';
import { getDateOffset } from '../fixtures/selectors';

/**
 * E2E Xendit Test Mode.
 *
 * Dijalankan hanya bila XENDIT_E2E_ENABLED=true dan callback URL publik
 * sudah terdaftar di dashboard Xendit. Alur checkout memakai Payment Link
 * sungguhan; konfirmasi dibawa oleh webhook yang ditandatangani dengan
 * callback token yang sama seperti konfigurasi aplikasi.
 */
test.describe('Pembayaran Xendit (Test Mode)', () => {
    test.skip(
        !XENDIT.e2eEnabled || !XENDIT.callbackToken,
        'Butuh XENDIT_E2E_ENABLED=true dan XENDIT_CALLBACK_TOKEN.',
    );

    test('client membayar via Xendit lalu booking dikonfirmasi', async ({
        page,
        asClient,
        seed,
    }) => {
        await asClient();

        const pkg = await seed.createPackage(page, {
            mitra_email: SEED_USERS.mitra.email,
            nama: `Paket Xendit E2E ${Date.now()}`,
            status: 'aktif',
            durasi_hari: 1,
            durasi_malam: 0,
            harga_per_orang: 250_000,
        });

        const booking = await seed.createBooking(page, {
            client_email: SEED_USERS.client.email,
            package_kode: pkg.kode,
            status: 'pending_payment',
            tanggal_keberangkatan: getDateOffset(7),
            jumlah_peserta: 1,
            participants: [{ nama: 'Peserta Xendit' }],
        });

        await page.goto(`/client/bookings/${booking.booking_id}`);

        await expect(page.getByText('Bayar dengan Xendit')).toBeVisible();

        await Promise.all([
            page.waitForURL(/dev\.xen\.to|checkout-staging\.xendit\.co/, {
                timeout: 30_000,
            }),
            page.getByRole('button', { name: 'Bayar Sekarang' }).click(),
        ]);

        await expect(page).toHaveURL(
            /dev\.xen\.to|checkout-staging\.xendit\.co/,
        );

        const response = await page.request.post('/payments/xendit/webhook', {
            headers: {
                'X-Callback-Token': XENDIT.callbackToken,
                'Content-Type': 'application/json',
            },
            data: {
                event: 'payment.capture',
                business_id: 'e2e-business',
                data: {
                    payment_id: `py-e2e-${booking.booking_id}`,
                    payment_request_id: `pr-e2e-${booking.booking_id}`,
                    reference_id: booking.kode,
                    request_amount: 250_000,
                    status: 'SUCCEEDED',
                },
            },
        });

        expect(response.ok()).toBeTruthy();

        await page.goto(`/client/bookings/${booking.booking_id}`);
        await expect(
            page.getByText(/Dikonfirmasi|Menunggu Trip/i).first(),
        ).toBeVisible({
            timeout: 30_000,
        });
    });

    test('mitra melihat opsi payment link Xendit', async ({
        page,
        asMitra,
        seed,
    }) => {
        const pkg = await seed.createPackage(page, {
            mitra_email: SEED_USERS.mitra.email,
            nama: `Paket Link Xendit E2E ${Date.now()}`,
            status: 'aktif',
            durasi_hari: 1,
            durasi_malam: 0,
            harga_per_orang: 250_000,
        });

        const booking = await seed.createBooking(page, {
            client_email: SEED_USERS.client.email,
            package_kode: pkg.kode,
            status: 'pending_payment',
            tanggal_keberangkatan: getDateOffset(7),
            jumlah_peserta: 1,
            participants: [{ nama: 'Peserta Link' }],
        });

        await asMitra();
        await page.goto(`/mitra/bookings/${booking.booking_id}`);

        await expect(
            page.getByRole('button', { name: 'Buat & Salin Link' }),
        ).toBeVisible();
        await expect(
            page.getByText('Upload Bukti Transfer', { exact: false }).first(),
        ).toBeVisible();
    });
});
