import { test, expect, SEED_USERS } from '../fixtures/base';
import { SEL } from '../fixtures/selectors';
import { ClientBookingPage } from '../pages/ClientBookingPage';
import { MitraBookingPage } from '../pages/MitraBookingPage';
import { VALID_JPEG_BUFFER } from '../fixtures/files';

test.describe('Client Notifications (/client/notifications)', () => {
    test.beforeEach(async ({ page, seed }) => {
        // Set fcm_token dummy pada mitra seed agar NotificationPermissionBanner tidak muncul
        await seed.resetUser(page, SEED_USERS.mitra.email, {
            fcm_token: 'e2e-dummy-fcm-token',
        });
    });

    test('menampilkan status semua dibaca, empty state, dan menyembunyikan tombol tandai semua ketika kosong', async ({
        page,
        seed,
    }) => {
        const suffix = `${Date.now()}_${Math.floor(Math.random() * 10000)}`;
        const clientEmail = `client-notif-empty-${suffix}@test.com`;

        await seed.createUser(page, {
            email: clientEmail,
            name: 'Client Notif Empty',
            role: 'client',
            password: 'password123',
        });
        await seed.resetUser(page, clientEmail, {
            fcm_token: 'e2e-dummy-fcm-token',
        });

        await seed.login(page, clientEmail, 'password123');
        await page.goto('/client/notifications');

        await expect(
            page.getByRole('link', { name: 'Notifikasi', exact: true }),
        ).toBeVisible();

        await expect(
            page.getByRole('heading', { name: 'Notifikasi', level: 1 }),
        ).toBeVisible();
        await expect(
            page.getByText('Semua notifikasi sudah dibaca.').first(),
        ).toBeVisible();
        await expect(
            page.getByRole('heading', { name: 'Belum ada notifikasi' }),
        ).toBeVisible();
        await expect(SEL.NOTIFICATIONS.markAllRead(page)).toHaveCount(0);
    });

    test('client menerima notifikasi baru, melihat badge lonceng, membaca satuan & redirect, lalu menandai semua dibaca', async ({
        page,
        asMitra,
        seed,
    }) => {
        // 1. Buat user client terisolasi + set fcm_token agar banner permission tidak muncul
        const suffix = `${Date.now()}_${Math.floor(Math.random() * 10000)}`;
        const clientEmail = `client-notif-${suffix}@test.com`;

        await seed.createUser(page, {
            email: clientEmail,
            name: 'Client Notif Test',
            role: 'client',
            password: 'password123',
        });
        await seed.resetUser(page, clientEmail, {
            fcm_token: 'e2e-dummy-fcm-token',
        });

        // 2. Setup paket & 2 booking milik client tersebut
        const pkg = await seed.createPackage(page, {
            mitra_email: SEED_USERS.mitra.email,
            nama: `Paket Notif Client ${suffix}`,
            status: 'aktif',
            days: [{ judul: 'Hari 1' }],
            facilities: [
                { tipe: 'include', nama: 'Transportasi' },
                { tipe: 'exclude', nama: 'Pengeluaran Pribadi' },
            ],
        });

        const booking1 = await seed.createBooking(page, {
            client_email: clientEmail,
            package_kode: pkg.kode,
            status: 'pending_payment',
        });

        const booking2 = await seed.createBooking(page, {
            client_email: clientEmail,
            package_kode: pkg.kode,
            status: 'pending_payment',
        });

        // 3. Client upload bukti bayar pada kedua booking
        await seed.login(page, clientEmail, 'password123');
        const clientBookingPage = new ClientBookingPage(page);

        for (const booking of [booking1, booking2]) {
            await clientBookingPage.gotoShow(booking.booking_id);
            await clientBookingPage.uploadPaymentProof(booking.booking_id, {
                name: 'bukti-notif.jpg',
                mimeType: 'image/jpeg',
                buffer: VALID_JPEG_BUFFER,
            });
        }

        // 4. Mitra memverifikasi kedua pembayaran -> menghasilkan 2 notifikasi untuk client
        await asMitra();
        const mitraBookingPage = new MitraBookingPage(page);

        for (const booking of [booking1, booking2]) {
            await mitraBookingPage.gotoShow(booking.booking_id);
            await mitraBookingPage.verifyPayment(booking.booking_id);
            await mitraBookingPage.expectFlashStatus(/berhasil diverifikasi/i);
        }

        // 5. Login kembali sebagai client & buka halaman notifikasi
        await seed.login(page, clientEmail, 'password123');
        await page.goto('/client/notifications');

        const bellLink = page.getByRole('link', {
            name: 'Notifikasi (2 belum dibaca)',
        });
        await expect(bellLink).toBeVisible();
        await expect(bellLink).toContainText('2');

        await expect(
            page.getByText('Anda memiliki 2 notifikasi belum dibaca.'),
        ).toBeVisible();

        const unreadDots = page
            .locator('main')
            .getByLabel('Belum dibaca', { exact: true });
        await expect(unreadDots).toHaveCount(2);

        // 6. Klik notifikasi pertama -> menandai dibaca & redirect ke URL detail
        const firstUnreadItem = page
            .locator('main button')
            .filter({ has: page.getByLabel('Belum dibaca', { exact: true }) })
            .first();
        await firstUnreadItem.click();

        await expect(page).toHaveURL(/\/client\/(bookings|trips|e-tickets)/);

        // 7. Kembali ke /client/notifications -> sisa 1 belum dibaca, lalu klik "Tandai Semua Dibaca"
        await page.goto('/client/notifications');
        await expect(
            page.getByRole('link', {
                name: 'Notifikasi (1 belum dibaca)',
            }),
        ).toBeVisible();
        await expect(unreadDots).toHaveCount(1);

        const markAllBtn = SEL.NOTIFICATIONS.markAllRead(page);
        await expect(markAllBtn).toBeVisible();
        await markAllBtn.click();

        await expect(
            page.getByText('Semua notifikasi sudah dibaca.').first(),
        ).toBeVisible();
        await expect(
            page.getByRole('link', { name: 'Notifikasi', exact: true }),
        ).toBeVisible();
        await expect(unreadDots).toHaveCount(0);
        await expect(markAllBtn).toHaveCount(0);
    });
});