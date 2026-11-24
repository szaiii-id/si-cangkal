// e2e/landing/landing.spec.ts
/**
- Test: Landing Page & Contact Form
 *
- Menguji:
- - Access control & Role-Aware UI (Guest, Client, Mitra, Admin)
- - Contract (Hero, Trip & Bundle, Visi & Misi, Contact, Footer & Legal links)
- - Interaksi UI (Carousel Trip & Bundle, pausable autoplay)
- - Validasi Browser (HTML5 required & email format)
- - Error Handling Level UI (Validasi server 422 → pesan ramah pengguna + ARIA)
- - Mutation — Happy Path (Submit form kontak valid → flash status + reset form)
- - Compatibility / Responsive (Mobile viewport menu hamburger & thumbnail carousel)
- - Usability & Accessibility (Axe WCAG 2.0 AA scan & navigasi keyboard)
 *
- Backend: ContactController@store → ContactService::send
- Route: GET / (home)
-        POST /contact (contact.store)
 */

import AxeBuilder from '@axe-core/playwright';
import { test, expect } from '../fixtures/base';

// ==========================================
// KONSTANTA
// ==========================================

const LEGAL_LINKS = [
    { label: 'Kebijakan Privasi', href: '/legal/privacy' },
    { label: 'Syarat & Ketentuan', href: '/legal/terms' },
    { label: 'Kebijakan Refund', href: '/legal/refund-policy' },
    { label: 'Kebijakan Cookie', href: '/legal/cookie-policy' },
    { label: 'Aturan Mitra', href: '/legal/mitra-rules' },
    { label: 'Penggunaan Wajar', href: '/legal/acceptable-use' },
] as const;

// ==========================================
// TEST SUITE
// ==========================================

test.describe('Landing Page & Contact', () => {
    // ==========================================
    // ACCESS CONTROL & ROLE-AWARE UI
    // ==========================================

    test.describe('Access Control & Role-Aware UI', () => {
        test('guest dapat mengakses halaman landing tanpa redirect', async ({
            page,
            asGuest,
        }) => {
            await asGuest();
            await page.goto('/');

            await expect(page).toHaveURL('/');
            await expect(page).toHaveTitle(/Beranda \| SI-CANGKAL/i);
        });

        test('guest melihat tombol Login, Daftar, dan Gabung Mitra Travel', async ({
            page,
            asGuest,
        }) => {
            await asGuest();
            await page.goto('/');

            const header = page.locator('header');
            await expect(
                header.getByRole('link', { name: /^Daftar$/i }),
            ).toBeVisible();
            await expect(
                header.getByRole('link', { name: /^Login$/i }),
            ).toBeVisible();

            await expect(
                page.getByRole('link', { name: /Gabung Mitra Travel/i }),
            ).toBeVisible();
            await expect(
                page.getByRole('link', { name: /Masuk Sistem/i }),
            ).toBeVisible();
        });

        test('client login melihat tombol Dashboard mengarah ke /client/dashboard', async ({
            page,
            asClient,
        }) => {
            await asClient();
            await page.goto('/');

            const header = page.locator('header');
            const dashboardNav = header.getByRole('link', {
                name: /^Dashboard$/i,
            });

            await expect(dashboardNav).toBeVisible();
            await expect(dashboardNav).toHaveAttribute(
                'href',
                /\/client\/dashboard$/,
            );
            await expect(
                header.getByRole('link', { name: /^Login$/i }),
            ).not.toBeVisible();

            const heroCta = page.getByRole('link', {
                name: /Kembali ke Dashboard/i,
            });
            await expect(heroCta).toBeVisible();
            await expect(heroCta).toHaveAttribute(
                'href',
                /\/client\/dashboard$/,
            );
        });

        test('mitra login melihat tombol Dashboard mengarah ke /mitra/dashboard', async ({
            page,
            asMitra,
        }) => {
            await asMitra();
            await page.goto('/');

            const dashboardNav = page
                .locator('header')
                .getByRole('link', { name: /^Dashboard$/i });

            await expect(dashboardNav).toBeVisible();
            await expect(dashboardNav).toHaveAttribute(
                'href',
                /\/mitra\/dashboard$/,
            );
        });

        test('admin login melihat tombol Dashboard mengarah ke /admin/dashboard', async ({
            page,
            asAdmin,
        }) => {
            await asAdmin();
            await page.goto('/');

            const dashboardNav = page
                .locator('header')
                .getByRole('link', { name: /^Dashboard$/i });

            await expect(dashboardNav).toBeVisible();
            await expect(dashboardNav).toHaveAttribute(
                'href',
                /\/admin\/dashboard$/,
            );
        });
    });

    // ==========================================
    // CONTRACT — ELEMEN HALAMAN & SECTION
    // ==========================================

    test.describe('Contract — Elemen & Section', () => {
        test.beforeEach(async ({ page, asGuest }) => {
            await asGuest();
            await page.goto('/');
        });

        test('menampilkan menu navigasi utama desktop', async ({ page }) => {
            const nav = page.getByRole('navigation', { name: 'Menu Utama' });
            await expect(nav).toBeVisible();

            await expect(
                nav.getByRole('link', { name: 'Beranda' }),
            ).toHaveAttribute('href', '#beranda');
            await expect(
                nav.getByRole('link', { name: 'Trip & Bundle' }),
            ).toHaveAttribute('href', '#trip');
            await expect(
                nav.getByRole('link', { name: 'Visi Misi' }),
            ).toHaveAttribute('href', '#visimisi');
            await expect(
                nav.getByRole('link', { name: 'Kontak' }),
            ).toHaveAttribute('href', '#contact');
        });

        test('menampilkan section Hero, TripBundle, VisiMisi, dan Contact', async ({
            page,
        }) => {
            await expect(
                page.getByRole('heading', {
                    level: 1,
                    name: /Sinergi Pariwisata Banua/i,
                }),
            ).toBeVisible();

            await expect(
                page.getByRole('heading', {
                    level: 2,
                    name: /Destinasi Unggulan/i,
                }),
            ).toBeVisible();

            await expect(
                page.getByRole('heading', {
                    level: 2,
                    name: /Visi & Misi SI-CANGKAL/i,
                }),
            ).toBeVisible();

            await expect(
                page.getByRole('heading', {
                    level: 2,
                    name: /Punya Pertanyaan atau/i,
                }),
            ).toBeVisible();
        });

        test('menampilkan 3 pilar misi SI-CANGKAL', async ({ page }) => {
            const visiMisiSection = page.locator('#visimisi');

            await expect(
                visiMisiSection.getByRole('heading', {
                    name: 'Digitalisasi Terpadu',
                }),
            ).toBeVisible();
            await expect(
                visiMisiSection.getByRole('heading', {
                    name: 'Pengawalan & Akuntabilitas',
                }),
            ).toBeVisible();
            await expect(
                visiMisiSection.getByRole('heading', {
                    name: 'Kolaborasi Strategis',
                }),
            ).toBeVisible();
        });

        test('menampilkan informasi kontak resmi Dinas Pariwisata / SI-CANGKAL', async ({
            page,
        }) => {
            const contactSection = page.locator('#contact');

            await expect(
                contactSection.getByText(
                    /Kertak Hanyar, Kalimantan Selatan, Indonesia/i,
                ),
            ).toBeVisible();
            await expect(
                contactSection.getByText('halo@si-cangkal.id'),
            ).toBeVisible();
            await expect(
                contactSection.getByText('+62 811 2233 4455'),
            ).toBeVisible();
        });

        test('footer menampilkan semua tautan legal dengan href valid', async ({
            page,
        }) => {
            const footer = page.locator('footer');

            for (const item of LEGAL_LINKS) {
                const link = footer.getByRole('link', { name: item.label });
                await expect(link).toBeVisible();
                await expect(link).toHaveAttribute(
                    'href',
                    new RegExp(`${item.href}$`),
                );
            }
        });

        test('tautan legal di footer dapat diklik dan memuat halaman', async ({
            page,
        }) => {
            const footer = page.locator('footer');

            await Promise.all([
                page.waitForURL('/legal/privacy', { timeout: 15_000 }),
                footer.getByRole('link', { name: 'Kebijakan Privasi' }).click(),
            ]);

            await expect(page).toHaveURL('/legal/privacy');
        });
    });

    // ==========================================
    // INTERAKSI — CAROUSEL TRIP & BUNDLE
    // ==========================================

    test.describe('Interaksi — Carousel Trip & Bundle', () => {
        test.beforeEach(async ({ page, asGuest }) => {
            await asGuest();
            await page.goto('/');
        });

        test('klik kartu bundle mengganti paket aktif dan detail deskripsi', async ({
            page,
        }) => {
            const carousel = page.getByRole('region', {
                name: 'Galeri paket wisata unggulan',
            });

            // Hover region untuk menjeda autoplay agar tidak terjadi race condition
            await carousel.hover();

            const targetButton = carousel.locator('button').filter({
                hasText: /Pesona Samber Gelap/i,
            });
            await targetButton.click();

            await expect(targetButton).toHaveAttribute('aria-pressed', 'true');
            await expect(
                page
                    .locator('#trip')
                    .getByRole('heading', {
                        level: 3,
                        name: 'Pesona Samber Gelap',
                    })
                    .last(),
            ).toBeVisible();
            await expect(
                page
                    .locator('#trip')
                    .getByText(
                        /Nikmati ketenangan pesisir pasir putih dan keindahan bawah laut/i,
                    ),
            ).toBeVisible();
        });

        test('tombol "Lihat Detail Kemitraan" mengarahkan guest ke /login', async ({
            page,
        }) => {
            const cta = page
                .locator('#trip')
                .getByRole('link', { name: 'Lihat Detail Kemitraan' });

            await expect(cta).toHaveAttribute('href', /\/login$/);

            await Promise.all([
                page.waitForURL('/login', { timeout: 15_000 }),
                cta.click(),
            ]);

            await expect(page).toHaveURL('/login');
        });
    });

    // ==========================================
    // VALIDASI BROWSER (Client-Side)
    // ==========================================

    test.describe('Validasi Browser — Contact Form', () => {
        test.beforeEach(async ({ page, asGuest }) => {
            await asGuest();
            await page.goto('/');
        });

        test('input form kontak memiliki atribut required dan tipe yang sesuai', async ({
            page,
        }) => {
            await expect(page.locator('#nama')).toHaveAttribute('required', '');
            await expect(page.locator('#email')).toHaveAttribute(
                'required',
                '',
            );
            await expect(page.locator('#email')).toHaveAttribute(
                'type',
                'email',
            );
            await expect(page.locator('#pesan')).toHaveAttribute(
                'required',
                '',
            );
        });

        test('submit form kontak kosong diblokir validasi HTML5', async ({
            page,
        }) => {
            let postTriggered = false;
            page.on('request', (req) => {
                if (req.url().includes('/contact') && req.method() === 'POST') {
                    postTriggered = true;
                }
            });

            await page
                .getByRole('button', { name: 'Kirim Pesan Sekarang' })
                .click();

            expect(postTriggered).toBe(false);
            await expect(page).toHaveURL('/');
        });

        test('submit email tanpa simbol @ diblokir validasi HTML5', async ({
            page,
        }) => {
            await page.locator('#nama').fill('Budi Santoso');
            await page.locator('#email').fill('bukan-email-valid');
            await page
                .locator('#pesan')
                .fill('Ini adalah pesan pertanyaan mengenai kemitraan wisata.');

            let postTriggered = false;
            page.on('request', (req) => {
                if (req.url().includes('/contact') && req.method() === 'POST') {
                    postTriggered = true;
                }
            });

            await page
                .getByRole('button', { name: 'Kirim Pesan Sekarang' })
                .click();

            expect(postTriggered).toBe(false);
            await expect(page).toHaveURL('/');
        });
    });

    // ==========================================
    // ERROR HANDLING LEVEL UI (Validasi Server 422)
    // ==========================================

    test.describe('Error Handling — Validasi Server (422)', () => {
        test.beforeEach(async ({ page, asGuest }) => {
            await asGuest();
            await page.goto('/');
        });

        test('pesan kurang dari 10 karakter menampilkan error ramah pengguna & aria-invalid', async ({
            page,
        }) => {
            await page.locator('#nama').fill('Budi Santoso');
            await page.locator('#email').fill('budi@instansi.go.id');
            await page.locator('#pesan').fill('Halo tes'); // 8 karakter (< 10)

            await Promise.all([
                page.waitForResponse(
                    (resp) =>
                        resp.url().includes('/contact') &&
                        resp.request().method() === 'POST',
                    { timeout: 15_000 },
                ),
                page
                    .getByRole('button', { name: 'Kirim Pesan Sekarang' })
                    .click(),
            ]);

            const pesanInput = page.locator('#pesan');
            const pesanError = page.locator('#pesan-error');

            await expect(pesanInput).toHaveAttribute('aria-invalid', 'true');
            await expect(pesanInput).toHaveAttribute(
                'aria-describedby',
                'pesan-error',
            );
            await expect(pesanError).toBeVisible();
            await expect(pesanError).toHaveAttribute('role', 'alert');
            await expect(pesanError).toHaveText('Pesan minimal 10 karakter.');
        });

        test('field kosong dan format email ditolak server (422) menampilkan error ramah pengguna', async ({
            page,
        }) => {
            // Bypass validasi HTML5 browser agar payload invalid benar-benar dikirim ke Laravel (422)
            await page
                .locator('#contact form')
                .evaluate((form: HTMLFormElement) => {
                    form.noValidate = true;
                });

            await page.locator('#nama').fill('');
            await page.locator('#email').fill('bukan-email-valid');
            await page
                .locator('#pesan')
                .fill('Permohonan informasi kerja sama paket wisata daerah.');

            await Promise.all([
                page.waitForResponse(
                    (resp) =>
                        resp.url().includes('/contact') &&
                        resp.request().method() === 'POST',
                    { timeout: 15_000 },
                ),
                page
                    .getByRole('button', { name: 'Kirim Pesan Sekarang' })
                    .click(),
            ]);

            const namaInput = page.locator('#nama');
            const namaError = page.locator('#nama-error');
            await expect(namaInput).toHaveAttribute('aria-invalid', 'true');
            await expect(namaError).toBeVisible();
            await expect(namaError).toHaveText('Nama wajib diisi.');

            const emailInput = page.locator('#email');
            const emailError = page.locator('#email-error');
            await expect(emailInput).toHaveAttribute('aria-invalid', 'true');
            await expect(emailInput).toHaveAttribute(
                'aria-describedby',
                'email-error',
            );
            await expect(emailError).toBeVisible();
            await expect(emailError).toHaveText('Format email tidak valid.');
        });
    });

    // ==========================================
    // MUTATION — HAPPY PATH CONTACT FORM
    // ==========================================

    test.describe('Mutation — Contact Form Valid', () => {
        test('submit pesan kontak valid menampilkan flash sukses dan mereset form', async ({
            page,
            asGuest,
        }) => {
            await asGuest();
            await page.goto('/');

            await page.locator('#nama').fill('Dinas Pariwisata Kab. Banjar');
            await page.locator('#email').fill('kemitraan@banjarkab.go.id');
            await page
                .locator('#pesan')
                .fill(
                    'Kami ingin berkoordinasi terkait integrasi paket wisata unggulan ke platform SI-CANGKAL.',
                );

            await Promise.all([
                page.waitForResponse(
                    (resp) =>
                        resp.url().includes('/contact') &&
                        resp.request().method() === 'POST',
                    { timeout: 15_000 },
                ),
                page
                    .getByRole('button', { name: 'Kirim Pesan Sekarang' })
                    .click(),
            ]);

            const statusBanner = page
                .locator('#contact')
                .locator('[role="status"]');

            await expect(statusBanner).toBeVisible({ timeout: 10_000 });
            await expect(
                statusBanner.getByRole('heading', {
                    name: 'Pesan Berhasil Terkirim!',
                }),
            ).toBeVisible();
            await expect(
                statusBanner.getByText(
                    'Pesan Anda berhasil terkirim. Tim kami akan segera merespons.',
                ),
            ).toBeVisible();

            // Form otomatis ter-reset setelah onSuccess
            await expect(page.locator('#nama')).toHaveValue('');
            await expect(page.locator('#email')).toHaveValue('');
            await expect(page.locator('#pesan')).toHaveValue('');
        });
    });

    // ==========================================
    // COMPATIBILITY — RESPONSIVE MOBILE VIEWPORT
    // ==========================================

    test.describe('Compatibility — Responsive Mobile', () => {
        test.use({ viewport: { width: 375, height: 812 } });

        test('menu hamburger mobile dapat dibuka, menampilkan navigasi, dan menutup saat item diklik', async ({
            page,
            asGuest,
        }) => {
            await asGuest();
            await page.goto('/');

            // Menu desktop tersembunyi di mobile
            await expect(
                page.getByRole('navigation', { name: 'Menu Utama' }),
            ).not.toBeVisible();

            const menuButton = page.getByRole('button', {
                name: 'Buka menu navigasi',
            });
            await expect(menuButton).toBeVisible();
            await expect(menuButton).toHaveAttribute('aria-expanded', 'false');

            // Buka menu mobile
            await menuButton.click();
            await expect(menuButton).toHaveAttribute('aria-expanded', 'true');

            const registerMobileLink = page
                .locator('header')
                .getByRole('link', { name: 'Daftar Akun' });
            await expect(registerMobileLink).toBeVisible();

            // Klik item menu → menu menutup
            await page
                .locator('header')
                .getByRole('link', { name: 'Visi Misi' })
                .click();
            await expect(menuButton).toHaveAttribute('aria-expanded', 'false');
        });

        test('thumbnail carousel mobile dapat digunakan untuk memilih destinasi', async ({
            page,
            asGuest,
        }) => {
            await asGuest();
            await page.goto('/');

            const carousel = page.getByRole('region', {
                name: 'Galeri paket wisata unggulan',
            });
            await carousel.hover();

            // Ubah selector ke thumbnail mobile bagian bawah (yang memiliki aria-label "Lihat detail ...")
            const mobileThumb = carousel.getByRole('button', {
                name: /Lihat detail Budaya & Alam Loksado/i,
            });
            await expect(mobileThumb).toBeVisible();

            await mobileThumb.click();
            await expect(
                page
                    .locator('#trip')
                    .getByRole('heading', {
                        level: 3,
                        name: 'Budaya & Alam Loksado',
                    })
                    .last(),
            ).toBeVisible();
            await expect(
                page
                    .locator('#trip')
                    .getByText(
                        /Rasakan sensasi bamboo rafting \(balanting paring\)/i,
                    ),
            ).toBeVisible();
        });
    });

    // ==========================================
    // USABILITY & ACCESSIBILITY (A11Y)
    // ==========================================

    test.describe('Usability & Accessibility', () => {
        test('halaman landing lolos audit WCAG 2.0 AA (tanpa pelanggaran critical/serious)', async ({
            page,
            asGuest,
        }) => {
            await asGuest();
            await page.goto('/');

            const results = await new AxeBuilder({ page })
                .withTags(['wcag2a', 'wcag2aa'])
                .disableRules(['region', 'landmark-one-main'])
                .analyze();

            const blockingViolations = results.violations.filter(
                (v) => v.impact === 'critical' || v.impact === 'serious',
            );

            expect(blockingViolations).toEqual([]);
        });

        test('form kontak dapat dinavigasi dan diisi menggunakan keyboard (Tab)', async ({
            page,
            asGuest,
        }) => {
            await asGuest();
            await page.goto('/');

            const namaInput = page.locator('#nama');
            await namaInput.focus();
            await expect(namaInput).toBeFocused();
            await page.keyboard.type('Penguji Keyboard');

            await page.keyboard.press('Tab');
            await expect(page.locator('#email')).toBeFocused();
            await page.keyboard.type('keyboard@test.com');

            await page.keyboard.press('Tab');
            await expect(page.locator('#pesan')).toBeFocused();
            await page.keyboard.type(
                'Pengujian navigasi keyboard pada formulir kontak.',
            );

            await page.keyboard.press('Tab');
            await expect(
                page.getByRole('button', { name: 'Kirim Pesan Sekarang' }),
            ).toBeFocused();
        });
    });
});
