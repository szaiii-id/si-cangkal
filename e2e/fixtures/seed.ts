// e2e/fixtures/seed.ts
/**
 * Helper untuk semua endpoint `/_test/*` yang terkait setup data.
 *
 * Endpoint ini HANYA aktif saat `APP_ENV=testing` (lihat `routes/web-testing.php`).
 * Tujuannya: setup state deterministik untuk E2E test tanpa interaksi UI.
 *
 * Semua fungsi:
 * - Pakai `apiPost`/`apiGet` dari `api.ts` (CSRF otomatis, retry, error handling).
 * - Return typed object (bukan `any`).
 * - Throw `ApiError` kalau gagal.
 *
 * Pemakaian:
 *   import { seed } from '../fixtures/seed';
 *
 *   await seed.resetUser(page, 'admin@titiktemu.test');
 *   await seed.createPackage(page, { mitra_email: '...', nama: '...' });
 */

import type { Page } from '@playwright/test';
import { apiGet, apiPost, type ApiData } from './api';

// ==========================================
// TIPE RESPONSE
// ==========================================

export type HealthResponse = {
    ok: boolean;
    env: string;
};

export type SeedResponse = {
    ok: boolean;
    seeder: string;
    output: string;
};

export type ResetUserResponse = {
    ok: boolean;
    user: {
        id: number;
        name: string;
        username: string | null;
        email: string;
        role: string;
        timezone: string;
        email_verified_at: string | null;
        must_change_password: boolean;
    };
};

export type CreateUserResponse = {
    ok: boolean;
    user: {
        id: number;
        name: string;
        username: string | null;
        email: string;
        role: string;
        timezone: string;
        email_verified_at: string | null;
        must_change_password: boolean;
    };
    user_id: number;
};

export type VerificationUrlResponse = {
    ok: boolean;
    url: string;
};

export type LoginResponse = {
    ok: boolean;
    user: {
        id: number;
        name: string;
        username: string | null;
        email: string;
        role: string;
        timezone: string;
        must_change_password: boolean;
    };
    user_id: number;
};

export type FindPackageResponse = {
    ok: boolean;
    id: number;
    kode: string;
};

export type CreatePackageResponse = {
    ok: boolean;
    package_id: number;
    kode: string;
};

export type CreateBookingResponse = {
    ok: boolean;
    booking_id: number;
    kode: string;
    status: string;
};

export type FindTripResponse = {
    ok: boolean;
    id: number;
    kode: string;
};

export type ResetTripResponse = {
    ok: boolean;
};

export type CreateTripResponse = {
    ok: boolean;
    trip_id: number;
    kode: string;
};

export type CreatePaymentLinkResponse = {
    ok: boolean;
    token: string;
    payment_id: number;
};

export type GenerateETicketResponse = {
    ok: boolean;
    uuid: string;
    download_url: string;
};

export type LatestNotificationResponse = {
    ok: boolean;
    notification: {
        id: string;
        user_id: number;
        tipe: string;
        judul: string;
        pesan: string;
        data: Record<string, unknown> | null;
        read_at: string | null;
        created_at: string;
    } | null;
    unread_count: number;
};

export type MarkAllNotificationsReadResponse = {
    ok: boolean;
    marked: number;
};

export type ClearThrottleResponse = {
    ok: boolean;
    key: string;
};

export type RunCommandResponse = {
    ok: boolean;
    command: string;
    output: string;
};

export type TruncateResponse = {
    ok: boolean;
    truncated: string[];
};

// ==========================================
// SEED HELPERS
// ==========================================

export const seed = {
    // ==========================================
    // HEALTH
    // ==========================================
    /**
     * Cek server hidup + env testing.
     *
     * @example
     *   const health = await seed.health(page);
     *   expect(health.env).toBe('testing');
     */
    async health(page: Page): Promise<HealthResponse> {
        return apiGet<HealthResponse>(page, '/_test/health');
    },

    // ==========================================
    // SEED DATABASE
    // ==========================================
    /**
     * Seed ulang database.
     *
     * @param seeder - FQCN seeder (default: `TestSeeder`)
     *
     * @example
     *   await seed.seedDatabase(page);  // pakai TestSeeder
     *   await seed.seedDatabase(page, 'Database\\Seeders\\DatabaseSeeder');
     */
    async seedDatabase(page: Page, seeder?: string): Promise<SeedResponse> {
        return apiPost<SeedResponse>(
            page,
            '/_test/seed',
            seeder ? { seeder } : {},
        );
    },

    // ==========================================
    // USER
    // ==========================================
    /**
     * Reset atribut user berdasarkan email.
     *
     * @param email - Email user
     * @param options - Atribut yang mau di-reset (password, name, dll)
     *
     * @example
     *   await seed.resetUser(page, 'admin@titiktemu.test', {
     *       password: 'password123',
     *       must_change_password: false,
     *   });
     */
    async resetUser(
        page: Page,
        email: string,
        options: {
            password?: string;
            name?: string;
            username?: string;
            timezone?: string;
            must_change_password?: boolean;
            email_verified_at?: boolean;
            fcm_token?: string | null;
            profile?: {
                nama_penyedia_usaha?: string;
                nomor_identitas?: string;
                alamat?: string;
                telepon?: string;
                provinsi?: string;
                kota?: string;
                kode_pos?: string;
                deskripsi?: string;
            };
        } = {},
    ): Promise<ResetUserResponse> {
        const data: ApiData = { email };

        for (const [key, value] of Object.entries(options)) {
            if (key === 'profile' && value) {
                data.profile = value;
            } else if (value !== undefined) {
                data[key] = value;
            }
        }

        return apiPost<ResetUserResponse>(page, '/_test/reset-user', data);
    },

    /**
     * Buat user baru dengan role & password deterministik.
     *
     * @param options - Atribut user
     *
     * @example
     *   await seed.createUser(page, {
     *       email: 'new@test.com',
     *       name: 'New User',
     *       role: 'client',
     *       password: 'password123',
     *   });
     */
    async createUser(
        page: Page,
        options: {
            email: string;
            name: string;
            role: 'admin' | 'mitra' | 'client';
            password?: string;
            username?: string;
            timezone?: string;
            must_change_password?: boolean;
            email_verified_at?: boolean;
            with_bank_account?: boolean;
        },
    ): Promise<CreateUserResponse> {
        const data: ApiData = {
            email: options.email,
            name: options.name,
            role: options.role,
        };

        if (options.password !== undefined) data.password = options.password;
        if (options.username !== undefined) data.username = options.username;
        if (options.timezone !== undefined) data.timezone = options.timezone;
        if (options.must_change_password !== undefined) {
            data.must_change_password = options.must_change_password;
        }
        if (options.email_verified_at !== undefined) {
            data.email_verified_at = options.email_verified_at;
        }
        if (options.with_bank_account !== undefined) {
            data.with_bank_account = options.with_bank_account;
        }

        return apiPost<CreateUserResponse>(page, '/_test/create-user', data);
    },

    /**
     * Login via HTTP (return session cookie).
     *
     * @example
     *   await seed.login(page, 'admin@titiktemu.test', 'password123');
     */
    async login(
        page: Page,
        email: string,
        password = 'password123',
    ): Promise<LoginResponse> {
        return apiPost<LoginResponse>(page, '/_test/login', {
            email,
            password,
        });
    },

    /**
     * Logout & invalidate session.
     */
    async logout(page: Page): Promise<{ ok: boolean }> {
        return apiPost<{ ok: boolean }>(page, '/_test/logout');
    },

    /**
     * Buat signed URL verifikasi email untuk user (khusus E2E).
     *
     * @example
     *   const { url } = await seed.verificationUrl(page, 'client@test.com');
     *   await page.goto(url);
     */
    async verificationUrl(
        page: Page,
        email: string,
        expiresIn = 60,
    ): Promise<VerificationUrlResponse> {
        return apiPost<VerificationUrlResponse>(
            page,
            '/_test/verification-url',
            { email, expires_in: expiresIn },
        );
    },

    // ==========================================
    // PACKAGE
    // ==========================================
    /**
     * Cari package by kode.
     *
     * @example
     *   const pkg = await seed.findPackage(page, 'PKG-2026-001');
     *   console.log(pkg.id);  // package ID
     */
    async findPackage(page: Page, kode: string): Promise<FindPackageResponse> {
        return apiPost<FindPackageResponse>(page, '/_test/find-package', {
            kode,
        });
    },

    /**
     * Buat package lengkap dengan days + facilities.
     *
     * @example
     *   await seed.createPackage(page, {
     *       mitra_email: 'budi@ptabc.com',
     *       nama: 'Paket Test',
     *       status: 'aktif',
     *       days: [{ judul: 'Hari 1' }],
     *       facilities: [{ tipe: 'include', nama: 'Transportasi' }],
     *   });
     */
    async createPackage(
        page: Page,
        options: {
            mitra_email: string;
            nama: string;
            kode?: string;
            kategori?: string;
            status?: string;
            durasi_hari?: number;
            durasi_malam?: number;
            harga_per_orang?: number;
            harga_per_rombongan?: number;
            max_peserta?: number;
            timezone?: string;
            days?: Array<{
                judul: string;
                deskripsi?: string;
                jam_mulai?: string;
                jam_selesai?: string;
                lokasi?: string;
            }>;
            facilities?: Array<{
                tipe: 'include' | 'exclude';
                nama: string;
            }>;
        },
    ): Promise<CreatePackageResponse> {
        return apiPost<CreatePackageResponse>(
            page,
            '/_test/create-package',
            options as unknown as ApiData,
        );
    },

    // ==========================================
    // BOOKING
    // ==========================================
    /**
     * Buat booking dengan status tertentu.
     *
     * @example
     *   await seed.createBooking(page, {
     *       client_email: 'client@titiktemu.test',
     *       package_kode: 'PKG-2026-001',
     *       status: 'pending_payment',
     *   });
     */
    async createBooking(
        page: Page,
        options: {
            client_email: string;
            package_kode: string;
            kode?: string;
            status?: string;
            tanggal_keberangkatan?: string;
            jumlah_peserta?: number;
            participants?: Array<{
                nama: string;
                umur?: number;
                no_hp?: string;
                catatan?: string;
            }>;
            catatan?: string;
            expired_at?: string;
            payment_proof_path?: string;
        },
    ): Promise<CreateBookingResponse> {
        return apiPost<CreateBookingResponse>(
            page,
            '/_test/create-booking',
            options as unknown as ApiData,
        );
    },

    /**
     * Buat token tautan pembayaran publik untuk booking tertentu.
     */
    async createPaymentLink(
        page: Page,
        bookingId: string,
    ): Promise<CreatePaymentLinkResponse> {
        return apiPost<CreatePaymentLinkResponse>(
            page,
            '/_test/create-payment-link',
            { booking_id: bookingId },
        );
    },

    // ==========================================
    // TRIP
    // ==========================================
    /**
     * Cari trip by kode.
     */
    async findTrip(page: Page, kode: string): Promise<FindTripResponse> {
        return apiPost<FindTripResponse>(page, '/_test/find-trip', { kode });
    },

    /**
     * Reset status trip.
     *
     * @example
     *   await seed.resetTrip(page, tripId, 'scheduled');
     *   await seed.resetTrip(page, tripId, 'active');
     */
    async resetTrip(
        page: Page,
        tripId: number,
        status:
            | 'scheduled'
            | 'active'
            | 'completed'
            | 'cancelled' = 'scheduled',
    ): Promise<ResetTripResponse> {
        return apiPost<ResetTripResponse>(page, '/_test/reset-trip', {
            trip_id: tripId,
            status,
        });
    },

    /**
     * Buat trip dari booking (bypass event listener).
     *
     * @example
     *   await seed.createTrip(page, 'BKG-2026-001');
     */
    async createTrip(
        page: Page,
        bookingKode: string,
    ): Promise<CreateTripResponse> {
        return apiPost<CreateTripResponse>(page, '/_test/create-trip', {
            booking_kode: bookingKode,
        });
    },

    // ==========================================
    // E-TICKET
    // ==========================================
    /**
     * Generate E-Ticket untuk trip tertentu (sync).
     *
     * @example
     *   const ticket = await seed.generateETicket(page, 'TRP-2026-001');
     *   console.log(ticket.download_url);
     */
    async generateETicket(
        page: Page,
        tripKode: string,
    ): Promise<GenerateETicketResponse> {
        return apiPost<GenerateETicketResponse>(
            page,
            '/_test/generate-eticket',
            { trip_kode: tripKode },
        );
    },

    // ==========================================
    // NOTIFICATION
    // ==========================================
    /**
     * Ambil notifikasi terakhir user.
     *
     * @example
     *   const { notification, unread_count } = await seed.latestNotification(
     *       page,
     *       'client@titiktemu.test',
     *   );
     */
    async latestNotification(
        page: Page,
        email: string,
    ): Promise<LatestNotificationResponse> {
        return apiPost<LatestNotificationResponse>(
            page,
            '/_test/latest-notification',
            { email },
        );
    },

    /**
     * Mark semua notifikasi user sebagai read.
     */
    async markAllNotificationsRead(
        page: Page,
        email: string,
    ): Promise<MarkAllNotificationsReadResponse> {
        return apiPost<MarkAllNotificationsReadResponse>(
            page,
            '/_test/mark-all-notifications-read',
            { email },
        );
    },

    // ==========================================
    // THROTTLE
    // ==========================================
    /**
     * Clear rate limiter entry.
     *
     * @example
     *   await seed.clearThrottle(page, 'login:user@example.com|127.0.0.1');
     */
    async clearThrottle(
        page: Page,
        key: string,
    ): Promise<ClearThrottleResponse> {
        return apiPost<ClearThrottleResponse>(page, '/_test/clear-throttle', {
            key,
        });
    },

    // ==========================================
    // COMMAND
    // ==========================================
    /**
     * Jalankan artisan command dari whitelist.
     *
     * Whitelist: `bookings:expire`, `trips:activate`, `bookings:simulate-expired`.
     *
     * @example
     *   await seed.runCommand(page, 'bookings:expire');
     *   await seed.runCommand(page, 'trips:activate');
     */
    async runCommand(
        page: Page,
        command: string,
        args?: Record<string, unknown>,
    ): Promise<RunCommandResponse> {
        return apiPost<RunCommandResponse>(page, '/_test/run-command', {
            command,
            args: args ?? {},
        });
    },

    // ==========================================
    // TRUNCATE
    // ==========================================
    /**
     * Truncate tabel test (opt-in).
     *
     * @example
     *   await seed.truncate(page);  // truncate semua tabel default
     *   await seed.truncate(page, ['bookings', 'payments']);  // sebagian
     */
    async truncate(page: Page, tables?: string[]): Promise<TruncateResponse> {
        return apiPost<TruncateResponse>(
            page,
            '/_test/truncate',
            tables ? { tables } : {},
        );
    },
} as const;

// ==========================================
// TYPE UNTUK test.extend()
// ==========================================

/**
 * Type `seed` untuk fixture `test.extend()`.
 *
 * @example
 *   import { test as base } from '@playwright/test';
 *   import { seed, type SeedHelpers } from './seed';
 *
 *   type Fixtures = {
 *       seed: SeedHelpers;
 *   };
 *
 *   export const test = base.extend<Fixtures>({
 *       seed: async ({ page }, use) => {
 *           await use(seed);
 *       },
 *   });
 */
export type SeedHelpers = typeof seed;
