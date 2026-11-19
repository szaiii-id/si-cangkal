import { test, expect, SEED_USERS } from '../fixtures/base';
import { SEL, getDateOffset } from '../fixtures/selectors';
import { ClientBookingPage } from '../pages/ClientBookingPage';
import type { Page } from '@playwright/test';

async function dismissNotificationPrompt(page: Page): Promise<void> {
    await page.addLocatorHandler(
        page.getByRole('dialog', { name: /Aktifkan Notifikasi/i }),
        async () => {
            await page.getByRole('button', { name: /Nanti Saja/i }).click();
        },
    );
}

test.describe('Dashboard Notifications — Mitra & Admin (/notifications)', () => {
    test.describe('Mitra Notifications', () => {
        test('menampilkan empty state dan menyembunyikan tombol tandai semua dibaca saat kosong', async ({
            page,
            seed,
        }) => {
            await dismissNotificationPrompt(page);

            const suffix = `${Date.now()}_${Math.floor(Math.random() * 10000)}`;
            const mitraEmail = `mitra-notif-empty-${suffix}@test.com`;

            await seed.createUser(page, {
                email: mitraEmail,
                name: 'Mitra Notif Empty',
                role: 'mitra',
                password: 'password123',
                with_bank_account: true,
            });
            await seed.resetUser(page, mitraEmail, {
                fcm_token: 'e2e-dummy-fcm-token',
            });

            await seed.login(page, mitraEmail, 'password123');
            await page.goto('/notifications');

            await expect(
                page
                    .locator('main')
                    .getByRole('heading', { name: 'Notifikasi', level: 1 }),
            ).toBeVisible();
            await expect(
                page.getByText('0 belum dibaca', { exact: true }),
            ).toBeVisible();
            await expect(
                page.getByText('Belum ada notifikasi'),
            ).toBeVisible();
            await expect(
                page.getByText('Notifikasi akan muncul di sini.'),
            ).toBeVisible();

            await expect(
                page
                    .locator('header')
                    .getByRole('link', { name: /Notifikasi \d+ belum dibaca/i }),
            ).toHaveCount(0);
            await expect(SEL.NOTIFICATIONS.markAllRead(page)).toHaveCount(0);
        });

        test('mitra menerima notifikasi booking baru, membaca satuan & redirect, lalu menandai semua dibaca', async ({
            page,
            asClient,
            seed,
        }, testInfo) => {
            await dismissNotificationPrompt(page);

            // 1. Buat mitra terisolasi + paket aktif miliknya
            const suffix = `${testInfo.workerIndex}_${Date.now()}_${Math.floor(Math.random() * 10000)}`;
            const mitraEmail = `mitra-notif-${suffix}@test.com`;

            await seed.createUser(page, {
                email: mitraEmail,
                name: 'Mitra Notif Test',
                role: 'mitra',
                password: 'password123',
                with_bank_account: true,
            });
            await seed.resetUser(page, mitraEmail, {
                fcm_token: 'e2e-dummy-fcm-token',
            });
            await seed.resetUser(page, SEED_USERS.client.email, {
                fcm_token: 'e2e-dummy-fcm-token',
            });

            const pkg = await seed.createPackage(page, {
                mitra_email: mitraEmail,
                kode: `PKG-NOTIF-${suffix}`,
                nama: `Paket Notif Mitra ${suffix}`,
                status: 'aktif',
                harga_per_orang: 400_000,
                durasi_hari: 2,
                durasi_malam: 1,
                days: [
                    { judul: 'Hari 1', lokasi: 'Banjarmasin' },
                    { judul: 'Hari 2', lokasi: 'Martapura' },
                ],
                facilities: [
                    { tipe: 'include', nama: 'Transportasi AC' },
                    { tipe: 'exclude', nama: 'Belanja Pribadi' },
                ],
            });

            // 2. Client membuat 2 booking pada paket mitra tersebut -> memicu 2 notifikasi untuk mitra
            await asClient();
            const clientBookingPage = new ClientBookingPage(page);

            for (let i = 1; i <= 2; i++) {
                await clientBookingPage.gotoCreate(pkg.package_id);
                await clientBookingPage.fillStep1({
                    tanggal: getDateOffset(5 + i),
                    jumlahPeserta: 1,
                });
                await clientBookingPage.proceedToStep2();
                await clientBookingPage.fillParticipants([
                    { nama: `Peserta Notif Mitra ${i}` },
                ]);
                await SEL.CLIENT.booking.submit(page).click();
                await page.waitForURL(/\/client\/bookings\/\d+/, {
                    timeout: 20_000,
                });
            }

            // 3. Login sebagai mitra & buka /notifications
            await seed.login(page, mitraEmail, 'password123');
            await page.goto('/notifications');

            await expect(
                page.getByText('2 belum dibaca', { exact: true }),
            ).toBeVisible();
            await expect(
                page.locator('header').getByRole('link', {
                    name: 'Notifikasi 2 belum dibaca',
                    exact: true,
                }),
            ).toBeVisible();

            const unreadDots = page
                .locator('main')
                .getByLabel('Belum dibaca', { exact: true });
            await expect(unreadDots).toHaveCount(2);

            // 4. Klik notifikasi pertama -> menandai dibaca & redirect ke halaman booking mitra
            const firstUnreadItem = page
                .locator('main button')
                .filter({
                    has: page.getByLabel('Belum dibaca', { exact: true }),
                })
                .first();
            await firstUnreadItem.click();

            await expect(page).toHaveURL(/\/mitra\/bookings\/\d+/);

            // 5. Kembali ke /notifications -> sisa 1 belum dibaca, lalu klik "Tandai Semua Dibaca"
            await page.goto('/notifications');
            await expect(
                page.getByText('1 belum dibaca', { exact: true }),
            ).toBeVisible();
            await expect(
                page.locator('header').getByRole('link', {
                    name: 'Notifikasi 1 belum dibaca',
                    exact: true,
                }),
            ).toBeVisible();
            await expect(unreadDots).toHaveCount(1);

            const markAllBtn = SEL.NOTIFICATIONS.markAllRead(page);
            await expect(markAllBtn).toBeVisible();
            await markAllBtn.click();

            await expect(
                page.getByText('0 belum dibaca', { exact: true }),
            ).toBeVisible();
            await expect(
                page
                    .locator('header')
                    .getByRole('link', { name: /Notifikasi \d+ belum dibaca/i }),
            ).toHaveCount(0);
            await expect(unreadDots).toHaveCount(0);
            await expect(markAllBtn).toHaveCount(0);
        });
    });

    test.describe('Admin Notifications', () => {
        test('admin baru menerima notifikasi akun dibuat, membaca satuan & redirect ke /admin/dashboard, serta mendukung tandai semua dibaca', async ({
            page,
            asAdmin,
            seed,
            browserName,
        }) => {
            await dismissNotificationPrompt(page);

            const tag = `${Date.now()}${browserName.slice(0, 3)}`;
            const newAdminEmail = `admin-notif-${tag}@sicangkal.test`;
            const newAdminUsername = `admnotif${tag}`;
            const initialPassword = 'AdminPass123!@#';
            const finalPassword = 'AdminFinalPass123!';

            // 1. Admin membuat akun admin baru via /admin/users/create -> memicu notifikasi account_created
            await asAdmin();
            await page.goto('/admin/users/create');
            await page.waitForLoadState('networkidle');

            await page.locator('#name').fill(`Admin Notif ${tag}`);
            await page.locator('#email').fill(newAdminEmail);
            await page.locator('#username').fill(newAdminUsername);
            await page.getByPlaceholder('Minimal 8 karakter').fill(initialPassword);

            await Promise.all([
                page.waitForResponse(
                    (res) =>
                        res.url().includes('/admin/users') &&
                        res.request().method() === 'POST',
                ),
                page.getByRole('button', { name: /Buat Admin/i }).click(),
            ]);

            const credDialog = page.getByRole('dialog', {
                name: /Kredensial User/i,
            });
            await expect(credDialog).toBeVisible({ timeout: 10_000 });
            await credDialog.getByRole('button', { name: /^Tutup$/i }).click();

            // 2. Login sebagai admin baru & selesaikan wajib ganti password agar bisa masuk /admin/dashboard
            await seed.login(page, newAdminEmail, initialPassword);
            await page.goto('/change-password');
            await page.fill('input#password', finalPassword);
            await page.fill('input#password_confirmation', finalPassword);
            await Promise.all([
                page.waitForURL('/login', { timeout: 15_000 }),
                page
                    .getByRole('button', { name: /Simpan Password Baru/i })
                    .click(),
            ]);

            await seed.login(page, newAdminEmail, finalPassword);
            await page.goto('/notifications');

            await expect(
                page
                    .locator('main')
                    .getByRole('heading', { name: 'Notifikasi', level: 1 }),
            ).toBeVisible();
            await expect(
                page.getByText('1 belum dibaca', { exact: true }),
            ).toBeVisible();
            await expect(
                page.locator('header').getByRole('link', {
                    name: 'Notifikasi 1 belum dibaca',
                    exact: true,
                }),
            ).toBeVisible();

            const unreadDots = page
                .locator('main')
                .getByLabel('Belum dibaca', { exact: true });
            await expect(unreadDots).toHaveCount(1);

            const notifItem = page
                .locator('main button')
                .filter({ hasText: 'Akun Anda Telah Dibuat' })
                .first();
            await expect(notifItem).toBeVisible();

            const markAllBtn = SEL.NOTIFICATIONS.markAllRead(page);
            await expect(markAllBtn).toBeVisible();

            // 3. Klik notifikasi -> menandai dibaca & redirect ke /admin/dashboard
            await notifItem.click();
            await expect(page).toHaveURL(/\/admin\/dashboard/);

            // 4. Kembali ke /notifications -> sudah dibaca (0 belum dibaca) & tombol tandai semua tersembunyi
            await page.goto('/notifications');
            await expect(
                page.getByText('0 belum dibaca', { exact: true }),
            ).toBeVisible();
            await expect(
                page
                    .locator('header')
                    .getByRole('link', { name: /Notifikasi \d+ belum dibaca/i }),
            ).toHaveCount(0);
            await expect(unreadDots).toHaveCount(0);
            await expect(markAllBtn).toHaveCount(0);
        });

        test('admin dapat menandai semua notifikasi dibaca menggunakan tombol Tandai Semua Dibaca', async ({
            page,
            asAdmin,
            seed,
            browserName,
        }) => {
            await dismissNotificationPrompt(page);

            const tag = `${Date.now()}${browserName.slice(0, 3)}`;
            const newAdminEmail = `admin-markall-${tag}@sicangkal.test`;
            const newAdminUsername = `admmark${tag}`;
            const adminPassword = 'AdminPass123!@#';

            // 1. Buat admin baru lewat UI
            await asAdmin();
            await page.goto('/admin/users/create');
            await page.waitForLoadState('networkidle');

            await page.locator('#name').fill(`Admin MarkAll ${tag}`);
            await page.locator('#email').fill(newAdminEmail);
            await page.locator('#username').fill(newAdminUsername);
            await page.getByPlaceholder('Minimal 8 karakter').fill(adminPassword);

            await Promise.all([
                page.waitForResponse(
                    (res) =>
                        res.url().includes('/admin/users') &&
                        res.request().method() === 'POST',
                ),
                page.getByRole('button', { name: /Buat Admin/i }).click(),
            ]);

            const credDialog = page.getByRole('dialog', {
                name: /Kredensial User/i,
            });
            await expect(credDialog).toBeVisible({ timeout: 10_000 });
            await credDialog.getByRole('button', { name: /^Tutup$/i }).click();

            // 2. Login sebagai admin baru & klik "Tandai Semua Dibaca"
            await seed.login(page, newAdminEmail, adminPassword);
            await page.goto('/notifications');

            await expect(
                page.getByText('1 belum dibaca', { exact: true }),
            ).toBeVisible();
            const markAllBtn = SEL.NOTIFICATIONS.markAllRead(page);
            await expect(markAllBtn).toBeVisible();
            await markAllBtn.click();

            await expect(
                page.getByText('0 belum dibaca', { exact: true }),
            ).toBeVisible();
            await expect(
                page
                    .locator('header')
                    .getByRole('link', { name: /Notifikasi \d+ belum dibaca/i }),
            ).toHaveCount(0);
            await expect(
                page.locator('main').getByLabel('Belum dibaca', { exact: true }),
            ).toHaveCount(0);
            await expect(markAllBtn).toHaveCount(0);
        });
    });
});