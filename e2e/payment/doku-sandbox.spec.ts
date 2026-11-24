import { test, expect } from '../fixtures/base';
import { DOKU } from '../config';
import { SEED_USERS } from '../fixtures/base';
import { getDateOffset } from '../fixtures/selectors';

/**
 * E2E sandbox DOKU.
 *
 * Dijalankan HANYA bila DOKU_SANDBOX_ENABLED=true dan prerequisites
 * terpenuhi: kredensial sandbox, tunnel localhost.run aktif, serta
 * settlement bank id yang terdaftar di dashboard sandbox DOKU.
 *
 * Test ini sengaja tidak dijalankan di CI normal karena bergantung pada
 * jaringan eksternal dan simulator pembayaran DOKU.
 */
test.describe('Pembayaran DOKU (sandbox)', () => {
    test.skip(
        !DOKU.sandboxEnabled,
        'Butuh DOKU_SANDBOX_ENABLED=true + tunnel + kredensial sandbox.',
    );

    test('client pending → bayar dengan DOKU → redirect ke checkout sandbox', async ({
        page,
        asClient,
        seed,
    }) => {
        await asClient();

        await seed.settleBankAccount(
            page,
            SEED_USERS.mitra.email,
            DOKU.settlementBankAccountId,
        );

        const pkg = await seed.createPackage(page, {
            mitra_email: SEED_USERS.mitra.email,
            nama: `Paket Doku E2E ${Date.now()}`,
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
            participants: [{ nama: 'Peserta Doku' }],
        });

        await page.goto(`/client/bookings/${booking.booking_id}`);

        await expect(page.getByText('Bayar dengan DOKU')).toBeVisible();
        await expect(
            page.getByRole('button', { name: 'Bayar Sekarang' }),
        ).toBeVisible();

        await Promise.all([
            page.waitForURL(/sandbox\.doku\.com\/checkout-link-v2\//, {
                timeout: 30_000,
            }),
            page.getByRole('button', { name: 'Bayar Sekarang' }).click(),
        ]);

        await expect(page).toHaveURL(/sandbox\.doku\.com\/checkout-link-v2\//);
    });

    test('mitra booking manual melihat opsi payment link', async ({
        page,
        asMitra,
        seed,
    }) => {
        await asMitra();

        await seed.settleBankAccount(
            page,
            SEED_USERS.mitra.email,
            DOKU.settlementBankAccountId,
        );

        const pkg = await seed.createPackage(page, {
            mitra_email: SEED_USERS.mitra.email,
            nama: `Paket Link E2E ${Date.now()}`,
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

        await page.goto(`/mitra/bookings/${booking.booking_id}`);

        await expect(
            page.getByRole('button', { name: 'Buat & Salin Link' }),
        ).toBeVisible();
        await expect(
            page.getByText('Upload Bukti Transfer', { exact: false }).first(),
        ).toBeVisible();
    });
});
