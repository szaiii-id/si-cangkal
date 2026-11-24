import { test, expect, SEED_USERS } from '../fixtures/base';
import { DOKU } from '../config';
import { getDateOffset } from '../fixtures/selectors';

/**
 * E2E Sub Account V2 (Fase 1b).
 *
 * Dijalankan hanya bila DOKU_SUB_ACCOUNT_E2E=true dan public key sudah
 * didaftarkan di dashboard DOKU sandbox. Tanpa itu seluruh spes dilewati.
 */
test.describe('DOKU Sub Account V2 (sandbox)', () => {
    test.skip(
        !DOKU.subAccountEnabled,
        'Butuh DOKU_SUB_ACCOUNT_E2E=true + public key terdaftar di DOKU.',
    );

    test('mitra terdaftar sub-account, client checkout langsung ke DOKU', async ({
        page,
        asMitra,
        asClient,
        seed,
    }) => {
        await asMitra();

        const registered = await seed.registerDokuSubAccount(
            page,
            SEED_USERS.mitra.email,
        );

        expect(registered.profile_id).toBeTruthy();
        expect(registered.account_no).toBeTruthy();
        expect(registered.split_rule_id).toBeTruthy();

        const pkg = await seed.createPackage(page, {
            mitra_email: SEED_USERS.mitra.email,
            nama: `Paket Sub Account E2E ${Date.now()}`,
            status: 'aktif',
            durasi_hari: 1,
            durasi_malam: 0,
            harga_per_orang: 400_000,
        });

        await asClient();

        const booking = await seed.createBooking(page, {
            client_email: SEED_USERS.client.email,
            package_kode: pkg.kode,
            status: 'pending_payment',
            tanggal_keberangkatan: getDateOffset(7),
            jumlah_peserta: 1,
            participants: [{ nama: 'Peserta Sub Account' }],
        });

        await page.goto(`/client/bookings/${booking.booking_id}`);

        await Promise.all([
            page.waitForURL(/sandbox\.doku\.com\/checkout-link-v2\//, {
                timeout: 30_000,
            }),
            page.getByRole('button', { name: 'Bayar Sekarang' }).click(),
        ]);

        await expect(page).toHaveURL(/sandbox\.doku\.com\/checkout-link-v2\//);
    });
});
