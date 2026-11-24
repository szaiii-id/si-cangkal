// e2e/mitra/onboarding.spec.ts
/**
 * Test: Mitra Onboarding (Profil Usaha & Rekening Pembayaran)
 *
 * Menguji:
 * - Access Control & Middleware Guard (Guest, Client, Admin, Mitra onboarded, Mitra baru)
 * - Contract (Elemen halaman, section Profil Usaha, section Rekening Pembayaran)
 * - Interaksi Dinamis (Switch Bank/E-Wallet, sanitasi digit nomor rekening, tambah/hapus max 3 rekening)
 * - Validasi Browser & Client-Side (HTML5 required & validate() sebelum POST)
 * - Error Handling Level UI (Validasi server 422 StoreOnboardingRequest → banner + field error + ARIA)
 * - Mutation — Happy Path (Simpan profil + multi-rekening → redirect /mitra/dashboard & guard aktif)
 * - Compatibility / Responsive (Mobile viewport 375x812 tanpa overflow)
 * - Usability & Accessibility (Axe WCAG 2.0 AA scan & navigasi keyboard Tab)
 *
 * Backend: OnboardingController@index, OnboardingController@store → StoreOnboardingRequest
 * Middleware: auth, password.changed, role:mitra, mitra.complete
 * Route: GET  /mitra/onboarding (mitra.onboarding.index)
 *        POST /mitra/onboarding (mitra.onboarding.store)
 */

import AxeBuilder from '@axe-core/playwright';
import type { Page } from '@playwright/test';
import { test, expect } from '../fixtures/base';

// ==========================================
// HELPER
// ==========================================

/**
 * Menyiapkan akun Mitra terverifikasi yang belum punya rekening, lalu
 * masuk dan diarahkan middleware ke /mitra/onboarding.
 */
async function registerFreshMitra(
    page: Page,
    seed: import('../fixtures/seed').SeedHelpers,
): Promise<{
    name: string;
    email: string;
}> {
    const unique = `${Date.now()}_${Math.floor(Math.random() * 100_000)}`;
    const name = `Mitra Onboarding ${unique}`;
    const email = `mitra_onboard_${unique}@test.com`;

    // Setup via fixture testing, bukan lewat UI registrasi. Suite ini menguji
    // onboarding, bukan alur verifikasi email (yang sudah diuji di auth).
    await seed.createUser(page, {
        email,
        name,
        role: 'mitra',
        password: 'password',
        username: `mitra_${unique}`,
        email_verified_at: true,
        with_bank_account: false, // → middleware mitra.complete ke onboarding
    });
    await seed.login(page, email, 'password');

    await page.goto('/mitra/dashboard');
    await expect(page).toHaveURL(/\/mitra\/onboarding$/);

    return { name, email };
}

// ==========================================
// TEST SUITE
// ==========================================

test.describe('Mitra Onboarding', () => {
    // ==========================================
    // ACCESS CONTROL & MIDDLEWARE GUARD
    // ==========================================

    test.describe('Access Control & Middleware Guard', () => {
        test('guest mengakses /mitra/onboarding diarahkan ke /login', async ({
            page,
            asGuest,
        }) => {
            await asGuest();
            await page.goto('/mitra/onboarding');

            await expect(page).toHaveURL(/\/login$/);
        });

        test('client mengakses /mitra/onboarding ditolak dan diarahkan ke dashboard client', async ({
            page,
            asClient,
        }) => {
            await asClient();
            const response = await page.goto('/mitra/onboarding');

            if (response?.status() === 403) {
                expect(response.status()).toBe(403);
            } else {
                await expect(page).not.toHaveURL(/\/mitra\/onboarding/);
                await expect(page).toHaveURL(/\/client\/dashboard$/);
            }
        });

        test('admin mengakses /mitra/onboarding ditolak dan diarahkan ke dashboard admin', async ({
            page,
            asAdmin,
        }) => {
            await asAdmin();
            const response = await page.goto('/mitra/onboarding');

            if (response?.status() === 403) {
                expect(response.status()).toBe(403);
            } else {
                await expect(page).not.toHaveURL(/\/mitra\/onboarding/);
                await expect(page).toHaveURL(/\/admin\/dashboard$/);
            }
        });

        test('mitra yang sudah onboarding diarahkan ke /mitra/dashboard saat membuka /mitra/onboarding', async ({
            page,
            asMitra,
        }) => {
            await asMitra();
            await page.goto('/mitra/onboarding');

            await expect(page).toHaveURL(/\/mitra\/dashboard$/);
        });

        test('mitra baru yang belum onboarding diarahkan dari /mitra/dashboard ke /mitra/onboarding', async ({
            page,
            asGuest,
            seed,
        }) => {
            await asGuest();
            await registerFreshMitra(page, seed);

            // Coba akses dashboard atau paket sebelum onboarding selesai
            await page.goto('/mitra/dashboard');
            await expect(page).toHaveURL(/\/mitra\/onboarding$/);

            await page.goto('/mitra/packages');
            await expect(page).toHaveURL(/\/mitra\/onboarding$/);
        });

        test('tombol Keluar melakukan logout dan mengarahkan ke halaman utama', async ({
            page,
            asGuest,
            seed,
        }) => {
            await asGuest();
            await registerFreshMitra(page, seed);

            await Promise.all([
                page.waitForURL(/\/$/, { timeout: 15_000 }),
                page.getByRole('button', { name: 'Keluar' }).click(),
            ]);

            await expect(page).toHaveURL(/\/$/);
        });
    });

    // ==========================================
    // CONTRACT — ELEMEN HALAMAN & FORM
    // ==========================================

    test.describe('Contract — Elemen Halaman & Form', () => {
        test.beforeEach(async ({ page, asGuest, seed }) => {
            await asGuest();
            await registerFreshMitra(page, seed);
        });

        test('menampilkan judul halaman, heading utama, dan subjudul onboarding', async ({
            page,
        }) => {
            await expect(page).toHaveTitle(
                /Lengkapi Data Mitra \| SI-CANGKAL/i,
            );
            await expect(
                page.getByRole('heading', { level: 2, name: /Data Usaha/i }),
            ).toBeVisible();
            await expect(
                page.getByText(
                    'Lengkapi profil usaha dan rekening Anda untuk mulai berjualan.',
                ),
            ).toBeVisible();
        });

        test('menampilkan section Profil Usaha beserta semua field input', async ({
            page,
        }) => {
            await expect(
                page.getByRole('heading', { level: 3, name: 'Profil Usaha' }),
            ).toBeVisible();

            await expect(page.locator('#nama_usaha')).toBeVisible();
            await expect(page.locator('#no_hp')).toBeVisible();
            await expect(page.locator('#deskripsi')).toBeVisible();
        });

        test('menampilkan section Rekening Pembayaran, badge keamanan, dan 1 rekening default', async ({
            page,
        }) => {
            await expect(
                page.getByRole('heading', {
                    level: 3,
                    name: 'Rekening Pembayaran',
                }),
            ).toBeVisible();
            await expect(
                page.getByText('Data Anda Aman & Terenkripsi'),
            ).toBeVisible();

            await expect(
                page.getByRole('heading', { level: 4, name: /Rekening 1/i }),
            ).toBeVisible();
            await expect(page.locator('#tipe_0')).toHaveValue('bank');
            await expect(page.locator('#nama_penyedia_0')).toBeVisible();
            await expect(page.locator('#nomor_rekening_0')).toBeVisible();
            await expect(page.locator('#nama_pemilik_0')).toBeVisible();

            await expect(
                page.getByRole('button', { name: 'Tambah Rekening Lain' }),
            ).toBeVisible();
            await expect(
                page.getByRole('button', { name: 'Simpan & Masuk Dashboard' }),
            ).toBeVisible();
        });
    });

    // ==========================================
    // INTERAKSI DINAMIS — BANK & E-WALLET MANAGER
    // ==========================================

    test.describe('Interaksi Dinamis — Bank & E-Wallet Manager', () => {
        test.beforeEach(async ({ page, asGuest, seed }) => {
            await asGuest();
            await registerFreshMitra(page, seed);
        });

        test('mengubah tipe rekening ke E-Wallet memperbarui label, opsi penyedia, dan mereset input', async ({
            page,
        }) => {
            await page.locator('#nama_penyedia_0').selectOption('BCA');
            await page.locator('#nomor_rekening_0').fill('1234567890');

            // Switch ke E-Wallet
            await page.locator('#tipe_0').selectOption('ewallet');

            await expect(
                page.locator('label[for="nama_penyedia_0"]'),
            ).toContainText('Nama E-Wallet');
            await expect(
                page.locator('label[for="nomor_rekening_0"]'),
            ).toContainText('Nomor HP E-Wallet');

            // Nilai sebelumnya otomatis di-reset
            await expect(page.locator('#nama_penyedia_0')).toHaveValue('');
            await expect(page.locator('#nomor_rekening_0')).toHaveValue('');

            // Opsi e-wallet tersedia dan dapat dipilih
            await page.locator('#nama_penyedia_0').selectOption('DANA');
            await expect(page.locator('#nama_penyedia_0')).toHaveValue('DANA');
        });

        test('input nomor rekening otomatis menyaring karakter non-angka', async ({
            page,
        }) => {
            const norekInput = page.locator('#nomor_rekening_0');
            await norekInput.fill('123-ABC-456 789!');

            await expect(norekInput).toHaveValue('123456789');
        });

        test('dapat menambah hingga maksimal 3 rekening dan menghapus rekening tambahan', async ({
            page,
        }) => {
            const addButton = page.getByRole('button', {
                name: 'Tambah Rekening Lain',
            });

            // Rekening 1 awal tidak memiliki tombol Hapus
            await expect(
                page.getByRole('button', { name: 'Hapus rekening 1' }),
            ).not.toBeVisible();

            // Tambah Rekening 2
            await addButton.click();
            await expect(
                page.getByRole('heading', { level: 4, name: /Rekening 2/i }),
            ).toBeVisible();

            // Tambah Rekening 3 (batas maksimal)
            await addButton.click();
            await expect(
                page.getByRole('heading', { level: 4, name: /Rekening 3/i }),
            ).toBeVisible();

            // Tombol tambah hilang & muncul pesan batas maksimal
            await expect(addButton).not.toBeVisible();
            await expect(
                page.getByText('Batas maksimal 3 rekening telah tercapai.'),
            ).toBeVisible();

            // Hapus Rekening 2 → tombol tambah muncul kembali
            await page
                .getByRole('button', { name: 'Hapus rekening 2' })
                .click();
            await expect(
                page.getByRole('heading', { level: 4, name: /Rekening 3/i }),
            ).not.toBeVisible();
            await expect(addButton).toBeVisible();
        });
    });

    // ==========================================
    // VALIDASI BROWSER & CLIENT-SIDE
    // ==========================================

    test.describe('Validasi Browser & Client-Side', () => {
        test.beforeEach(async ({ page, asGuest, seed }) => {
            await asGuest();
            await registerFreshMitra(page, seed);
        });

        test('input nama usaha dan no HP memiliki atribut required dan memblokir submit kosong', async ({
            page,
        }) => {
            await expect(page.locator('#nama_usaha')).toHaveAttribute(
                'required',
                '',
            );
            await expect(page.locator('#no_hp')).toHaveAttribute(
                'required',
                '',
            );

            let postTriggered = false;
            page.on('request', (req) => {
                if (
                    req.url().includes('/mitra/onboarding') &&
                    req.method() === 'POST'
                ) {
                    postTriggered = true;
                }
            });

            await page
                .getByRole('button', { name: 'Simpan & Masuk Dashboard' })
                .click();

            expect(postTriggered).toBe(false);
            await expect(page).toHaveURL(/\/mitra\/onboarding$/);
        });

        test('mengosongkan data rekening memicu validasi client-side tanpa request ke server', async ({
            page,
        }) => {
            await page.locator('#nama_usaha').fill('PT Borneo Wisata Utama');
            await page.locator('#no_hp').fill('081234567890');

            let postTriggered = false;
            page.on('request', (req) => {
                if (
                    req.url().includes('/mitra/onboarding') &&
                    req.method() === 'POST'
                ) {
                    postTriggered = true;
                }
            });

            await page
                .getByRole('button', { name: 'Simpan & Masuk Dashboard' })
                .click();

            expect(postTriggered).toBe(false);

            await expect(
                page.getByText('Terdapat 3 kesalahan pada form'),
            ).toBeVisible();
            await expect(
                page.getByText('Nama bank/e-wallet wajib dipilih.'),
            ).toBeVisible();
            await expect(
                page.getByText('Nomor rekening wajib diisi.'),
            ).toBeVisible();
            await expect(
                page.getByText('Nama pemilik rekening wajib diisi.'),
            ).toBeVisible();
        });
    });

    // ==========================================
    // ERROR HANDLING LEVEL UI (Validasi Server 422)
    // ==========================================

    test.describe('Error Handling — Validasi Server (422)', () => {
        test('input tidak memenuhi aturan StoreOnboardingRequest menampilkan pesan error 422 di UI', async ({
            page,
            asGuest,
            seed,
        }) => {
            await asGuest();
            await registerFreshMitra(page, seed);

            // Lolos cek non-empty client-side, namun gagal aturan min/regex backend:
            // - nama_penyedia_usaha: min:2 ('A')
            // - no_hp: regex:/^[0-9+]+$/ ('0812-invalid')
            // - nomor_rekening: min:4 ('123')
            // - nama_pemilik: min:2 ('B')
            await page.locator('#nama_usaha').fill('A');
            await page.locator('#no_hp').fill('0812-invalid');
            await page.locator('#nama_penyedia_0').selectOption('BCA');
            await page.locator('#nomor_rekening_0').fill('123');
            await page.locator('#nama_pemilik_0').fill('B');

            await Promise.all([
                page.waitForResponse(
                    (resp) =>
                        resp.url().includes('/mitra/onboarding') &&
                        resp.request().method() === 'POST',
                    { timeout: 15_000 },
                ),
                page
                    .getByRole('button', { name: 'Simpan & Masuk Dashboard' })
                    .click(),
            ]);

            await expect(
                page.getByText('Terdapat 4 kesalahan pada form'),
            ).toBeVisible();

            const namaUsahaInput = page.locator('#nama_usaha');
            const namaUsahaError = page.locator('#nama_usaha-error');
            await expect(namaUsahaInput).toHaveAttribute(
                'aria-invalid',
                'true',
            );
            await expect(namaUsahaError).toBeVisible();
            await expect(namaUsahaError).toHaveText(
                'Nama penyedia usaha minimal 2 karakter.',
            );

            const noHpInput = page.locator('#no_hp');
            const noHpError = page.locator('#no_hp-error');
            await expect(noHpInput).toHaveAttribute('aria-invalid', 'true');
            await expect(noHpError).toBeVisible();
            await expect(noHpError).toHaveText(
                'Format nomor HP tidak valid. Gunakan hanya angka atau tanda plus.',
            );

            await expect(
                page.getByText('Nomor rekening minimal 4 digit.'),
            ).toBeVisible();
            await expect(
                page.getByText('Nama pemilik rekening minimal 2 karakter.'),
            ).toBeVisible();
        });
    });

    // ==========================================
    // MUTATION — HAPPY PATH ONBOARDING
    // ==========================================

    test.describe('Mutation — Onboarding Valid', () => {
        test('mitra baru melengkapi profil usaha & rekening berhasil masuk ke /mitra/dashboard', async ({
            page,
            asGuest,
            seed,
        }) => {
            await asGuest();
            await registerFreshMitra(page, seed);

            // 1. Isi Profil Usaha
            await page.locator('#nama_usaha').fill('PT Pesona Meratus Tour');
            await page.locator('#no_hp').fill('+6281234567890');
            await page
                .locator('#deskripsi')
                .fill(
                    'Operator wisata petualangan dan budaya di Kalimantan Selatan.',
                );

            // 2. Isi Rekening 1 (Bank)
            await page.locator('#tipe_0').selectOption('bank');
            await page.locator('#nama_penyedia_0').selectOption('BCA');
            await page.locator('#nomor_rekening_0').fill('1234567890');
            await page
                .locator('#nama_pemilik_0')
                .fill('PT Pesona Meratus Tour');

            // 3. Tambah & Isi Rekening 2 (E-Wallet)
            await page
                .getByRole('button', { name: 'Tambah Rekening Lain' })
                .click();
            await page.locator('#tipe_1').selectOption('ewallet');
            await page.locator('#nama_penyedia_1').selectOption('DANA');
            await page.locator('#nomor_rekening_1').fill('081234567890');
            await page.locator('#nama_pemilik_1').fill('Ahmad Fauzi');

            // 4. Submit & verifikasi redirect ke dashboard mitra
            await Promise.all([
                page.waitForURL(/\/mitra\/dashboard$/, { timeout: 15_000 }),
                page
                    .getByRole('button', { name: 'Simpan & Masuk Dashboard' })
                    .click(),
            ]);

            await expect(page).toHaveURL(/\/mitra\/dashboard$/);

            // 5. Verifikasi guard setelah onboarding selesai:
            //    - Kembali ke /mitra/onboarding akan redirect ke /mitra/dashboard
            //    - Akses /mitra/packages tidak lagi di-redirect ke onboarding
            await page.goto('/mitra/onboarding');
            await expect(page).toHaveURL(/\/mitra\/dashboard$/);

            await page.goto('/mitra/packages');
            await expect(page).toHaveURL(/\/mitra\/packages$/);
        });
    });

    // ==========================================
    // COMPATIBILITY — RESPONSIVE MOBILE VIEWPORT
    // ==========================================

    test.describe('Compatibility — Responsive Mobile', () => {
        test.use({ viewport: { width: 375, height: 812 } });

        test('halaman onboarding dapat digunakan pada viewport mobile tanpa overflow', async ({
            page,
            asGuest,
            seed,
        }) => {
            await asGuest();
            await registerFreshMitra(page, seed);

            await expect(page.locator('#nama_usaha')).toBeVisible();
            await expect(page.locator('#no_hp')).toBeVisible();
            await expect(page.locator('#nama_penyedia_0')).toBeVisible();
            await expect(
                page.getByRole('button', { name: 'Simpan & Masuk Dashboard' }),
            ).toBeVisible();

            const hasHorizontalOverflow = await page.evaluate(
                () =>
                    document.documentElement.scrollWidth >
                    document.documentElement.clientWidth,
            );
            expect(hasHorizontalOverflow).toBe(false);
        });
    });

    // ==========================================
    // USABILITY & ACCESSIBILITY (A11Y)
    // ==========================================

    test.describe('Usability & Accessibility', () => {
        test('halaman onboarding lolos audit WCAG 2.0 AA (tanpa pelanggaran critical/serious)', async ({
            page,
            asGuest,
            seed,
        }) => {
            await asGuest();
            await registerFreshMitra(page, seed);

            const results = await new AxeBuilder({ page })
                .withTags(['wcag2a', 'wcag2aa'])
                .disableRules(['region', 'landmark-one-main'])
                .analyze();

            const blockingViolations = results.violations.filter(
                (v) => v.impact === 'critical' || v.impact === 'serious',
            );

            expect(blockingViolations).toEqual([]);
        });

        test('form profil usaha dapat dinavigasi menggunakan keyboard (Tab)', async ({
            page,
            asGuest,
            seed,
        }) => {
            await asGuest();
            await registerFreshMitra(page, seed);

            const namaUsahaInput = page.locator('#nama_usaha');
            await namaUsahaInput.focus();
            await expect(namaUsahaInput).toBeFocused();
            await page.keyboard.type('Borneo Adventure');

            await page.keyboard.press('Tab');
            await expect(page.locator('#no_hp')).toBeFocused();
            await page.keyboard.type('08123456789');

            await page.keyboard.press('Tab');
            await expect(page.locator('#deskripsi')).toBeFocused();
        });
    });
});
