import { test, expect, SEED_USERS } from '../fixtures/base';
import { getDateOffset } from '../fixtures/selectors';
import AxeBuilder from '@axe-core/playwright';
import type { Page } from '@playwright/test';

async function dismissNotificationPrompt(page: Page): Promise<void> {
    await page.addLocatorHandler(
        page.getByRole('dialog', { name: /Aktifkan Notifikasi/i }),
        async () => {
            await page.getByRole('button', { name: /Nanti Saja/i }).click();
        },
    );
}

async function expectNoCriticalA11yViolations(
    page: Page,
    pageLabel: string,
): Promise<void> {
    await page
        .waitForLoadState('networkidle', { timeout: 3_000 })
        .catch(() => {});
    await page.locator('body').waitFor({ state: 'visible' });

    const results = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
        .analyze();

    const blockingViolations = results.violations.filter((v) =>
        ['critical', 'serious'].includes(v.impact ?? ''),
    );

    if (blockingViolations.length > 0) {
        const formatted = blockingViolations
            .map(
                (v) =>
                    `[${v.impact?.toUpperCase()}] ${v.id}: ${v.help} (${v.nodes.length} node)\n` +
                    v.nodes
                        .slice(0, 3)
                        .map(
                            (n) =>
                                `  - ${n.target.join(' ')}: ${n.failureSummary}`,
                        )
                        .join('\n'),
            )
            .join('\n\n');

        expect(
            blockingViolations,
            `Ditemukan pelanggaran aksesibilitas critical/serious pada ${pageLabel}:\n\n${formatted}`,
        ).toEqual([]);
    }
}

async function auditRoutes(
    page: Page,
    routes: Array<{ url: string; label: string }>,
): Promise<void> {
    for (const route of routes) {
        await page.goto(route.url, {
            waitUntil: 'domcontentloaded',
            timeout: 45_000,
        });
        await expectNoCriticalA11yViolations(page, route.label);
    }
}

test.describe('Accessibility Audit (WCAG 2.1 AA — Axe-Core)', () => {
    // ==========================================
    // 1. HALAMAN PUBLIK & AUTENTIKASI
    // ==========================================

    test.describe('Public & Auth Pages', () => {
        test('halaman publik (Landing, Login, Register, Forgot Password) bebas pelanggaran critical/serious', async ({
            page,
            asGuest,
        }) => {
            test.setTimeout(120_000);
            await asGuest();

            await auditRoutes(page, [
                { url: '/', label: 'Landing Page (/)' },
                { url: '/login', label: 'Login Page (/login)' },
                { url: '/register', label: 'Register Client (/register)' },
                {
                    url: '/register-mitra',
                    label: 'Register Mitra (/register-mitra)',
                },
                {
                    url: '/forgot-password',
                    label: 'Forgot Password (/forgot-password)',
                },
            ]);
        });

        test('halaman verifikasi email bebas pelanggaran critical/serious', async ({
            page,
            asGuest,
            seed,
        }) => {
            test.setTimeout(120_000);
            await asGuest();

            const email = `a11y-verify-${Date.now()}@test.com`;
            await seed.createUser(page, {
                email,
                name: 'Verifikasi A11y',
                role: 'client',
                password: 'password',
                email_verified_at: false,
            });
            await seed.login(page, email, 'password');
            await dismissNotificationPrompt(page);

            await auditRoutes(page, [
                { url: '/verify-email', label: 'Verify Email (/verify-email)' },
            ]);
        });
    });

    // ==========================================
    // 2. HALAMAN CLIENT
    // ==========================================

    test.describe('Client Portal Pages', () => {
        test('halaman utama Client (Dashboard, Catalog, Bookings, Profile) bebas pelanggaran critical/serious', async ({
            page,
            asClient,
            seed,
        }) => {
            test.setTimeout(180_000);
            await dismissNotificationPrompt(page);

            const suffix = Date.now();
            const pkg = await seed.createPackage(page, {
                mitra_email: SEED_USERS.mitra.email,
                nama: `Paket A11y Client ${suffix}`,
                status: 'aktif',
                durasi_hari: 2,
                durasi_malam: 1,
                harga_per_orang: 400_000,
                days: [{ judul: 'Hari 1: Wisata', lokasi: 'Banjarmasin' }],
                facilities: [{ tipe: 'include', nama: 'Makan & Transportasi' }],
            });

            const booking = await seed.createBooking(page, {
                client_email: SEED_USERS.client.email,
                package_kode: pkg.kode,
                status: 'pending_payment',
                tanggal_keberangkatan: getDateOffset(7),
                jumlah_peserta: 1,
                participants: [{ nama: 'Peserta A11y', umur: 25 }],
            });

            // Booking + trip selesai untuk mengaudit halaman form review.
            const formBooking = await seed.createBooking(page, {
                client_email: SEED_USERS.client.email,
                package_kode: pkg.kode,
                status: 'confirmed',
                tanggal_keberangkatan: getDateOffset(14),
                jumlah_peserta: 1,
                participants: [{ nama: 'Peserta Review A11y', umur: 25 }],
            });
            const formTrip = await seed.createTrip(page, formBooking.kode);
            await seed.resetTrip(page, formTrip.trip_id, 'completed');

            // Booking kedua dipakai untuk mengisi review yang tampil di katalog.
            const catalogBooking = await seed.createBooking(page, {
                client_email: SEED_USERS.client.email,
                package_kode: pkg.kode,
                status: 'confirmed',
                tanggal_keberangkatan: getDateOffset(21),
                jumlah_peserta: 1,
                participants: [{ nama: 'Peserta Ulasan A11y', umur: 27 }],
            });
            const catalogTrip = await seed.createTrip(
                page,
                catalogBooking.kode,
            );
            await seed.resetTrip(page, catalogTrip.trip_id, 'completed');

            await asClient();

            // Isi satu review supaya katalog juga dirender dalam state ada ulasan.
            const csrf = await page.evaluate(() =>
                decodeURIComponent(
                    document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '',
                ),
            );
            const submitReview = await page.request.post(
                `/client/bookings/${catalogBooking.booking_id}/review`,
                {
                    data: {
                        rating: 5,
                        komentar: 'Perjalanan sangat nyaman dan tepat waktu.',
                    },
                    headers: { 'X-XSRF-TOKEN': csrf },
                },
            );
            expect(submitReview.ok()).toBeTruthy();

            // Slug paket di-generate dengan suffix acak, jadi ambil dari link katalog.
            await page.goto('/client/catalog');
            const katalogHref = await page
                .getByRole('link', {
                    name: new RegExp(`Paket A11y Client ${suffix}`),
                })
                .first()
                .getAttribute('href');

            await auditRoutes(page, [
                { url: '/client/dashboard', label: 'Client Dashboard' },
                { url: '/client/catalog', label: 'Client Catalog Index' },
                {
                    url: katalogHref ?? '/client/catalog',
                    label: 'Client Catalog Show (dengan review)',
                },
                { url: '/client/bookings', label: 'Client Bookings Index' },
                {
                    url: `/client/bookings/${booking.booking_id}`,
                    label: 'Client Booking Show',
                },
                {
                    url: `/client/bookings/${formBooking.booking_id}/review`,
                    label: 'Client Review Create',
                },
                { url: '/client/profile', label: 'Client Profile' },
            ]);
        });
    });

    // ==========================================
    // 3. HALAMAN MITRA
    // ==========================================

    test.describe('Mitra Portal Pages', () => {
        test('halaman utama Mitra (Dashboard, Packages, Bookings, Trips, Refunds, Bank Accounts, Profile) bebas pelanggaran critical/serious', async ({
            page,
            asMitra,
            seed,
        }) => {
            test.setTimeout(180_000);
            await dismissNotificationPrompt(page);

            const pkg = await seed.createPackage(page, {
                mitra_email: SEED_USERS.mitra.email,
                nama: `Paket A11y Mitra ${Date.now()}`,
                status: 'aktif',
                durasi_hari: 1,
                durasi_malam: 0,
                harga_per_orang: 350_000,
                days: [{ judul: 'Hari 1: Wisata', lokasi: 'Banjarbaru' }],
                facilities: [{ tipe: 'include', nama: 'Snack' }],
            });

            const booking = await seed.createBooking(page, {
                client_email: SEED_USERS.client.email,
                package_kode: pkg.kode,
                status: 'confirmed',
                tanggal_keberangkatan: getDateOffset(8),
                jumlah_peserta: 1,
                participants: [{ nama: 'Peserta A11y Mitra', umur: 26 }],
            });

            const trip = await seed.createTrip(page, booking.kode);

            await asMitra();

            await auditRoutes(page, [
                { url: '/mitra/dashboard', label: 'Mitra Dashboard' },
                { url: '/mitra/packages', label: 'Mitra Packages Index' },
                {
                    url: '/mitra/packages/create',
                    label: 'Mitra Package Create',
                },
                { url: '/mitra/bookings', label: 'Mitra Bookings Index' },
                {
                    url: `/mitra/bookings/${booking.booking_id}`,
                    label: 'Mitra Booking Show',
                },
                { url: '/mitra/trips', label: 'Mitra Trips Index' },
                {
                    url: `/mitra/trips/${trip.trip_id}`,
                    label: 'Mitra Trip Show',
                },
                { url: '/mitra/refunds', label: 'Mitra Refunds Index' },
                { url: '/mitra/bank-accounts', label: 'Mitra Bank Accounts' },
                { url: '/mitra/profile', label: 'Mitra Profile' },
            ]);
        });
    });

    // ==========================================
    // 4. HALAMAN ADMIN
    // ==========================================

    test.describe('Admin Portal Pages', () => {
        test('halaman utama Admin (Dashboard, Users, Clients, Bookings, Trips, Refunds, Reports) bebas pelanggaran critical/serious', async ({
            page,
            asAdmin,
        }) => {
            test.setTimeout(180_000);
            await dismissNotificationPrompt(page);
            await asAdmin();

            await auditRoutes(page, [
                { url: '/admin/dashboard', label: 'Admin Dashboard' },
                { url: '/admin/users', label: 'Admin Users' },
                { url: '/admin/clients', label: 'Admin Clients' },
                { url: '/admin/bookings', label: 'Admin Bookings' },
                { url: '/admin/trips', label: 'Admin Trips' },
                { url: '/admin/refunds', label: 'Admin Refunds' },
                { url: '/admin/reports', label: 'Admin Reports' },
            ]);
        });
    });
});
