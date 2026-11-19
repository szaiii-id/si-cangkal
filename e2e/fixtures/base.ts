// e2e/fixtures/base.ts
/**
 * Custom test fixture untuk E2E.
 *
 * Extends `@playwright/test` dengan helper:
 * - `page` (override) — otomatis menekan prompt push notification agar tidak menyebabkan flaky
 * - `asAdmin`, `asMitra`, `asClient`, `asGuest` — login/logout
 * - `seed` — helper setup data via `/_test/*`
 * - `resetTrip` — reset trip status
 * - `resetAll` — reset semua user seed
 *
 * Pemakaian:
 *   import { test, expect } from '../fixtures/base';
 *
 *   test('admin bisa akses user', async ({ page, asAdmin }) => {
 *       await asAdmin();
 *       await page.goto('/admin/users');
 *       // ...
 *   });
 *
 * ⚠️ Semua fixture async — wajib `await`.
 */

import { test as base, expect } from '@playwright/test';
import {
    SEED_USERS,
    forceLogout,
    getSeedUser,
    loginAs,
    logout,
    type SeedRole,
} from './auth';
import { seed, type SeedHelpers } from './seed';

// ==========================================
// TYPE FIXTURES
// ==========================================

export type Fixtures = {
    /**
     * Login sebagai admin seed.
     * Reset user dulu (anti state leak).
     */
    asAdmin: () => Promise<void>;

    /**
     * Login sebagai mitra seed.
     * Reset user + profile dulu.
     */
    asMitra: () => Promise<void>;

    /**
     * Login sebagai client seed.
     * Reset user dulu.
     */
    asClient: () => Promise<void>;

    /**
     * Logout / clear cookie (guest state).
     */
    asGuest: () => Promise<void>;

    /**
     * Helper setup data via `/_test/*`.
     */
    seed: SeedHelpers;

    /**
     * Reset trip status.
     *
     * @example
     *   await resetTrip();  // reset trip default ke scheduled
     *   await resetTrip({ tripKode: 'TRP-2026-001', status: 'active' });
     */
    resetTrip: (options?: {
        tripKode?: string;
        status?: 'scheduled' | 'active' | 'completed' | 'cancelled';
    }) => Promise<void>;

    /**
     * Reset semua user seed (admin, mitra, client).
     *
     * Berguna untuk `beforeEach` di file test yang mengubah banyak user.
     */
    resetAll: () => Promise<void>;
};

// ==========================================
// KONSTANTA
// ==========================================

/**
 * Trip kode default untuk `resetTrip`.
 * Sesuai `TripSeeder` (TRP-2026-001).
 */
const DEFAULT_TRIP_KODE = `TRP-${new Date().getFullYear()}-001`;

// ==========================================
// HELPER: RESET USER
// ==========================================

/**
 * Reset user seed ke state awal.
 *
 * - Password → `"password"` (sesuai `TestSeeder`).
 * - `must_change_password` → `false` (skip force change).
 * - Profile mitra → reset ke seed.
 */
async function resetSeedUser(
    page: import('@playwright/test').Page,
    role: SeedRole,
): Promise<void> {
    const user = SEED_USERS[role];

    const options: Parameters<typeof seed.resetUser>[2] = {
        password: user.password,
        name: user.name,
        username: user.username,
        timezone: user.timezone,
        must_change_password: false,
    };

    // Mitra: reset profile juga
    if (role === 'mitra') {
        options.profile = {
            nama_penyedia_usaha: 'PT Mitra Test',
            nomor_identitas: '1234567890123456',
            alamat: 'Jl. Mitra No. 2',
            telepon: '08123456789',
            provinsi: 'Sulawesi Selatan',
            kota: 'Makassar',
            kode_pos: '90111',
        };
    }

    await seed.resetUser(page, user.email, options);
}

// ==========================================
// EXTEND TEST
// ==========================================

export const test = base.extend<Fixtures>({
    // ==========================================
    // page (Global Anti-Flaky Notification Guard)
    // ==========================================
    page: async ({ page }, use) => {
        // 1. Cegah browser native Notification prompt
        await page.addInitScript(() => {
            try {
                Object.defineProperty(window, 'Notification', {
                    writable: true,
                    value: {
                        permission: 'denied',
                        requestPermission: () => Promise.resolve('denied'),
                    },
                });
            } catch {
                // Abaikan jika browser mengunci properti Notification
            }
        });

        // 2. Intercept tombol "Nanti Saja" pada modal notifikasi kustom (z-[100000], tanpa role="dialog")
        await page.addLocatorHandler(
            page.getByRole('button', { name: /Nanti Saja/i }),
            async (btn) => {
                await btn.click({ force: true }).catch(() => {});
            },
            { noWaitAfter: true },
        );

        await use(page);
    },

    // ==========================================
    // asAdmin
    // ==========================================
    asAdmin: async ({ page }, use) => {
        await use(async () => {
            await resetSeedUser(page, 'admin');
            await loginAs(page, 'admin');
        });
    },

    // ==========================================
    // asMitra
    // ==========================================
    asMitra: async ({ page }, use) => {
        await use(async () => {
            await resetSeedUser(page, 'mitra');
            await loginAs(page, 'mitra');
        });
    },

    // ==========================================
    // asClient
    // ==========================================
    asClient: async ({ page }, use) => {
        await use(async () => {
            await resetSeedUser(page, 'client');
            await loginAs(page, 'client');
        });
    },

    // ==========================================
    // asGuest
    // ==========================================
    asGuest: async ({ page }, use) => {
        await use(async () => {
            await forceLogout(page);
        });
    },

    // ==========================================
    // seed
    // ==========================================
    seed: async ({}, use) => {
        await use(seed);
    },

    // ==========================================
    // resetTrip
    // ==========================================
    resetTrip: async ({ page }, use) => {
        await use(async (options = {}) => {
            const tripKode = options.tripKode ?? DEFAULT_TRIP_KODE;
            const status = options.status ?? 'scheduled';

            // Cari trip ID dari kode
            const trip = await seed.findTrip(page, tripKode);

            // Reset status
            await seed.resetTrip(page, trip.id, status);
        });
    },

    // ==========================================
    // resetAll
    // ==========================================
    resetAll: async ({ page }, use) => {
        await use(async () => {
            // Reset semua user seed (paralel)
            await Promise.all([
                resetSeedUser(page, 'admin'),
                resetSeedUser(page, 'mitra'),
                resetSeedUser(page, 'client'),
            ]);
        });
    },
});

// ==========================================
// RE-EXPORT
// ==========================================

export { expect };

// Re-export helper lain untuk convenience
export {
    SEED_USERS,
    getSeedUser,
    loginAs,
    logout,
    forceLogout,
    type SeedRole,
};