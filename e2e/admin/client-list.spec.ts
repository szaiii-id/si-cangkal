import { test, expect, SEED_USERS } from '../fixtures/base';
import type { Page } from '@playwright/test';

async function dismissNotificationPrompt(page: Page): Promise<void> {
    await page.addLocatorHandler(
        page.getByRole('dialog', { name: /Aktifkan Notifikasi/i }),
        async () => {
            await page.getByRole('button', { name: /Nanti Saja/i }).click();
        },
    );
}

test.describe('Admin — Daftar Client (Read-Only)', () => {
    // ==========================================
    // 1. ACCESS CONTROL
    // ==========================================

    test.describe('Access Control', () => {
        test('guest, client, dan mitra ditolak mengakses /admin/clients', async ({
            page,
            asGuest,
            asClient,
            asMitra,
        }) => {
            await dismissNotificationPrompt(page);

            await asGuest();
            await page.goto('/admin/clients');
            await expect(page).toHaveURL(/\/login$/);

            await asClient();
            await page.goto('/admin/clients');
            await expect(page).toHaveURL(/\/client\/dashboard$/);

            await asMitra();
            await page.goto('/admin/clients');
            await expect(page).toHaveURL(/\/mitra\/dashboard$/);
        });
    });

    // ==========================================
    // 2. LISTING, SEARCH & READ-ONLY ENFORCEMENT
    // ==========================================

    test.describe('Listing & Pencarian Client', () => {
        test('menampilkan daftar client dalam mode read-only tanpa tombol edit/hapus dan role non-client tidak bocor', async ({
            page,
            asAdmin,
        }) => {
            await dismissNotificationPrompt(page);
            await asAdmin();

            await page.goto('/admin/clients');
            await page.waitForLoadState('networkidle');

            // Heading utama di dalam <main>
            await expect(
                page
                    .getByRole('main')
                    .getByRole('heading', { name: 'Daftar Client' }),
            ).toBeVisible();

            // Indikator Mode Read-only & Total Client Terdaftar
            await expect(page.getByText('Mode Read-only')).toBeVisible();
            await expect(page.getByText('Client Terdaftar')).toBeVisible();

            // Pastikan user seed client tampil di tabel
            const table = page.getByRole('table');
            await expect(table).toBeVisible();
            await expect(
                table.getByText(SEED_USERS.client.email),
            ).toBeVisible();

            // Pastikan akun Admin dan Mitra tidak muncul di daftar client
            await expect(table.getByText(SEED_USERS.admin.email)).toHaveCount(
                0,
            );
            await expect(table.getByText(SEED_USERS.mitra.email)).toHaveCount(
                0,
            );

            // Verifikasi strict read-only: tidak ada tombol Edit, Hapus, atau Tambah di area main
            const mainArea = page.getByRole('main');
            await expect(
                mainArea.getByRole('button', {
                    name: /Edit|Ubah|Hapus|Delete|Tambah/i,
                }),
            ).toHaveCount(0);
            await expect(
                mainArea.getByRole('link', { name: /Edit|Ubah|Hapus|Tambah/i }),
            ).toHaveCount(0);
        });

        test('pencarian client dan empty state saat kata kunci tidak ditemukan berfungsi', async ({
            page,
            asAdmin,
        }) => {
            await dismissNotificationPrompt(page);
            await asAdmin();

            await page.goto('/admin/clients');
            await page.waitForLoadState('networkidle');

            const searchInput = page.getByPlaceholder(
                'Cari nama, email, atau username...',
            );
            await expect(searchInput).toBeVisible();

            // Cari email client yang ada
            await searchInput.fill(SEED_USERS.client.email);
            await expect(page).toHaveURL(
                new RegExp(
                    `search=${encodeURIComponent(SEED_USERS.client.email)}`,
                ),
            );
            await expect(
                page.getByRole('table').getByText(SEED_USERS.client.email),
            ).toBeVisible();

            // Cari kata kunci acak -> tampil EmptyState
            const nonExistentQuery = `tidak-ada-client-${Date.now()}`;
            await searchInput.fill(nonExistentQuery);
            await expect(
                page.getByText('Tidak ada hasil ditemukan'),
            ).toBeVisible();
            await expect(
                page.getByText(
                    `Tidak ditemukan client dengan kata kunci "${nonExistentQuery}".`,
                ),
            ).toBeVisible();

            // Reset pencarian melalui tombol clear pada EmptyState atau SearchInput
            const resetBtn = page
                .getByRole('button', { name: /Reset|Hapus|Clear/i })
                .first();
            await resetBtn.click();
            await expect(
                page.getByRole('table').getByText(SEED_USERS.client.email),
            ).toBeVisible();
        });
    });
});
