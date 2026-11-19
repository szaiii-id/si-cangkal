// e2e/config.ts
/**
 * Konfigurasi terpusat untuk Playwright E2E.
 *
 * Prinsip:
 * - Satu sumber kebenaran untuk semua konfigurasi test.
 * - Fail-fast: validasi saat import, bukan saat runtime.
 * - Type-safe: semua export pakai `as const` untuk literal type.
 * - Configurable: bisa override via env (untuk CI berbeda).
 * - Terkelompok: `APP`, `DB`, `SHARD`, `CI`, `TIMEOUTS`, `PATHS`.
 *
 * Penggunaan:
 *   import { APP, DB, TIMEOUTS, PATHS, SHARD, CI } from './config';
 *
 *   console.log(APP.url);       // http://localhost:8000
 *   console.log(DB.database);   // sicangkal_db_testing
 *   console.log(TIMEOUTS.test); // 60000
 */

import { config as loadEnv } from 'dotenv';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

// ==========================================
// PROJECT ROOT
// ==========================================
const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

/**
 * Root project (folder yang berisi `composer.json`, `package.json`).
 * Dipakai untuk resolve path absolut.
 */
export const PROJECT_ROOT = path.resolve(__dirname, '..');

// ==========================================
// LOAD ENV
// ==========================================
/**
 * Load `.env.testing` SEBELUM membaca `process.env`.
 *
 * `override: false` → env dari shell (CI) menang, `.env.testing` hanya fallback.
 * Ini memungkinkan CI override value via GitHub Secrets.
 */
loadEnv({
    path: path.resolve(PROJECT_ROOT, '.env.testing'),
    override: false,
});

// ==========================================
// CI
// ==========================================
/**
 * Info environment CI.
 *
 * - `isCI`: true jika berjalan di CI (GitHub Actions, GitLab CI, dll).
 * - `retries`: jumlah retry saat CI (test flaky di-retry, lokal tidak).
 * - `workers`: jumlah worker paralel. CI default 2, lokal 1 (sequential).
 * - `maxFailures`: fail-fast saat CI untuk hemat resource.
 */
export const CI = {
    isCI: !!process.env.CI,
    retries: process.env.CI ? 2 : 0,
    workers: process.env.CI ? 2 : 1,
    maxFailures: process.env.CI ? 10 : 0,
} as const;

// ==========================================
// SHARD
// ==========================================
/**
 * Info sharding.
 *
 * Sharding = memecah test suite jadi N bagian, jalan paralel di N proses.
 * Berguna untuk CI yang punya banyak core.
 *
 * - `index`: 0-based index shard (0, 1, 2, ...).
 * - `total`: total jumlah shard (1 = tidak sharding).
 * - `isSharded`: true jika `total > 1`.
 * - `isHeavyLoad`: true jika `total > 4` (butuh timeout lebih longgar).
 *
 * Cara pakai:
 *   SHARD_INDEX=0 TOTAL_SHARDS=4 bun scripts/e2e-shard.ts
 */
export const SHARD = {
    index: Number(process.env.SHARD_INDEX ?? 0),
    total: Number(process.env.TOTAL_SHARDS ?? 1),
    get isSharded(): boolean {
        return this.total > 1;
    },
    get isHeavyLoad(): boolean {
        return this.total > 4;
    },
} as const;

// ==========================================
// APP
// ==========================================
/**
 * Konfigurasi aplikasi.
 *
 * ⚠️ PENTING: host HARUS `localhost`, bukan `127.0.0.1`.
 * Alasan: FE `isFcmSupported()` di `lib/firebase.ts` cek
 * `hostname === 'localhost'` untuk menentukan FCM support.
 * Kalau pakai `127.0.0.1`, FCM banner tidak akan muncul.
 *
 * Port per shard: `8000 + SHARD_INDEX`.
 * - Shard 0 → 8000
 * - Shard 1 → 8001
 * - Shard 2 → 8002
 * - dst.
 */
const APP_HOST = 'localhost';
const BASE_PORT = 8000;
const APP_PORT = SHARD.index > 0 ? BASE_PORT + SHARD.index : BASE_PORT;

export const APP = {
    host: APP_HOST,
    port: APP_PORT,
    url: `http://${APP_HOST}:${APP_PORT}`,
    env: process.env.E2E_APP_ENV ?? 'testing',
} as const;

// ==========================================
// DB
// ==========================================
/**
 * Konfigurasi database testing.
 *
 * Sharding: setiap shard punya DB sendiri (isolasi penuh).
 * - Shard 0 → `sicangkal_db_testing_w0`
 * - Shard 1 → `sicangkal_db_testing_w1`
 * - dst.
 *
 * Tanpa sharding: pakai `sicangkal_db_testing` langsung.
 *
 * ⚠️ PENTING: user DB harus punya izin CREATEDB
 * (untuk `global-setup.ts` yang buat DB jika belum ada).
 */
const BASE_DB = process.env.E2E_DB_DATABASE ?? 'sicangkal_db_testing';
const DB_DATABASE = SHARD.isSharded ? `${BASE_DB}_w${SHARD.index}` : BASE_DB;

export const DB = {
    connection: process.env.E2E_DB_CONNECTION ?? 'pgsql',
    host: process.env.E2E_DB_HOST ?? '127.0.0.1',
    port: Number(process.env.E2E_DB_PORT ?? 5432),
    database: DB_DATABASE,
    username:
        process.env.E2E_DB_USERNAME ?? process.env.DB_USERNAME ?? 'postgres',
    password:
        process.env.E2E_DB_PASSWORD ?? process.env.DB_PASSWORD ?? '',
} as const;

// ==========================================
// PATHS
// ==========================================
/**
 * Path absolut & relatif untuk E2E.
 *
 * - `projectRoot`: root project.
 * - `storage`: storage per shard (isolasi compiled view, cache, log, session).
 * - `outputDir`: direktori artifact Playwright (trace, screenshot, video).
 * - `reportDir`: direktori HTML report.
 *
 * Storage isolation WAJIB untuk sharding — tanpa ini, 8 shard compile
 * Blade bersamaan → view corrupt → test flaky.
 */
export const PATHS = {
    projectRoot: PROJECT_ROOT,
    storage:
        process.env.E2E_STORAGE_PATH ??
        path.resolve(PROJECT_ROOT, `storage/shard-${SHARD.index}`),
    outputDir:
        process.env.E2E_OUTPUT_DIR ?? `test-results/shard-${SHARD.index}`,
    reportDir:
        process.env.E2E_REPORT_DIR ?? `playwright-report/shard-${SHARD.index}`,
} as const;

// ==========================================
// TIMEOUTS (adaptive)
// ==========================================
/**
 * Timeout adaptif berdasarkan:
 * - `CI.isCI`: CI lebih lambat dari lokal (retry, cold start).
 * - `SHARD.isHeavyLoad`: 8 shard paralel → CPU/RAM terbagi → timeout naik.
 *
 * Semua nilai dalam milidetik.
 *
 * - `test`: timeout total per test (termasuk `beforeEach`/`afterEach`).
 * - `action`: timeout per action (click, fill, dll).
 * - `navigation`: timeout per navigasi (`page.goto`).
 * - `expect`: timeout untuk `expect(...).toBeVisible()` dll.
 * - `webServer`: timeout start PHP server (8 shard start bersamaan).
 * - `globalTimeout`: timeout keseluruhan run.
 */
export const TIMEOUTS = {
    test: CI.isCI
        ? SHARD.isHeavyLoad
            ? 90_000
            : 60_000
        : 60_000,
    action: CI.isCI
        ? SHARD.isHeavyLoad
            ? 30_000
            : 20_000
        : 10_000,
    navigation: CI.isCI
        ? SHARD.isHeavyLoad
            ? 45_000
            : 30_000
        : 15_000,
    expect: CI.isCI
        ? SHARD.isHeavyLoad
            ? 30_000
            : 20_000
        : 10_000,
    webServer: 180_000,
    globalTimeout: 45 * 60_000,
} as const;

// ==========================================
// HEALTHCHECK
// ==========================================
/**
 * Konfigurasi health-check untuk `/_test/*` route.
 *
 * Tujuan: fail-fast jika route `/_test/*` tidak terdaftar.
 * Penyebab umum:
 * - `APP_ENV` bukan `testing` → `bootstrap/app.php` tidak load `web-testing.php`.
 * - Port zombie (`php -S` dari run sebelumnya).
 * - Storage shard tidak writable.
 *
 * Health-check polling sampai status != 404 (route terdaftar).
 */
export const HEALTHCHECK = {
    path: '/_test/health',
    method: 'GET' as const,
    maxAttempts: 30,
    intervalMs: 500,
    timeoutMs: 3_000,
} as const;

// ==========================================
// ARTISAN ENV
// ==========================================
/**
 * Environment untuk `php artisan` & `php -S` (webServer).
 *
 * Kunci utama:
 * - `SESSION_DRIVER='array'` → session per-request, tidak butuh session table.
 * - `CACHE_STORE='array'` → cache in-memory, tidak butuh cache table.
 * - `QUEUE_CONNECTION='sync'` → E-Ticket & notifikasi langsung jadi (deterministik).
 * - `MAIL_MAILER='array'` → email tidak terkirim betulan (untuk test).
 * - `LARAVEL_STORAGE_PATH` → storage per shard (isolasi).
 *
 * ⚠️ `QUEUE_CONNECTION='sync'` WAJIB untuk E2E:
 * - `BookingPaymentService::verifyPayment()` dispatch `BookingConfirmed` event.
 * - Listener `GenerateETicketListener` → dispatch `GenerateETicketJob` ke queue.
 * - Kalau async, E-Ticket mungkin belum siap saat test assert → flaky.
 * - Dengan `sync`, E-Ticket langsung jadi dalam request yang sama.
 * 
 * ✅ FIX RATE LIMIT:
 * - Meneruskan `AUTH_THROTTLE_*_TEST_OVERRIDE` dari `process.env` (script)
 *   ke server `php -S`.
 * - Jika tidak di-set dari script, gunakan fallback '1000' (default `.env.testing`).
 * - Ini memastikan test E2E rate limit menggunakan limit kecil (misal 5),
 *   sedangkan test fungsional biasa tetap menggunakan limit besar (1000).
 */
export const ARTISAN_ENV: Record<string, string> = {
    APP_ENV: APP.env,
    APP_DEBUG: 'true',
    APP_URL: APP.url,
    DB_CONNECTION: DB.connection,
    DB_HOST: DB.host,
    DB_PORT: String(DB.port),
    DB_DATABASE: DB.database,
    DB_USERNAME: DB.username,
    DB_PASSWORD: DB.password,
    SESSION_DRIVER: 'database',
    CACHE_STORE: 'database',
    QUEUE_CONNECTION: 'sync',
    MAIL_MAILER: 'array',
    LARAVEL_STORAGE_PATH: PATHS.storage,

AUTH_THROTTLE_LOGIN_TEST_OVERRIDE: process.env.AUTH_THROTTLE_LOGIN_TEST_OVERRIDE ?? '1000',
    AUTH_THROTTLE_REGISTER_TEST_OVERRIDE: process.env.AUTH_THROTTLE_REGISTER_TEST_OVERRIDE ?? '1000',
    AUTH_THROTTLE_FORGOT_PASSWORD_TEST_OVERRIDE: process.env.AUTH_THROTTLE_FORGOT_PASSWORD_TEST_OVERRIDE ?? '1000',
    AUTH_THROTTLE_RESET_PASSWORD_TEST_OVERRIDE: process.env.AUTH_THROTTLE_RESET_PASSWORD_TEST_OVERRIDE ?? '1000',
    AUTH_THROTTLE_CONTACT_TEST_OVERRIDE: process.env.AUTH_THROTTLE_CONTACT_TEST_OVERRIDE ?? '1000',
    AUTH_THROTTLE_UPLOAD_PROOF_TEST_OVERRIDE: process.env.AUTH_THROTTLE_UPLOAD_PROOF_TEST_OVERRIDE ?? '1000',
    AUTH_THROTTLE_ETICKET_DOWNLOAD_TEST_OVERRIDE: process.env.AUTH_THROTTLE_ETICKET_DOWNLOAD_TEST_OVERRIDE ?? '1000',
};

// ==========================================
// VALIDATION (fail-fast)
// ==========================================
/**
 * Validasi konfigurasi wajib.
 *
 * Jika ada yang salah, throw Error dengan pesan jelas.
 * Dipanggil otomatis saat import (lihat bawah).
 */
export function validateConfig(): void {
    const errors: string[] = [];

    // APP
    if (APP.port < 1 || APP.port > 65535) {
        errors.push(`APP_PORT invalid: ${APP.port} (harus 1-65535)`);
    }

    // DB
    if (!DB.database || DB.database.trim() === '') {
        errors.push('DB_DATABASE tidak boleh kosong');
    }
    if (!DB.username || DB.username.trim() === '') {
        errors.push('DB_USERNAME tidak boleh kosong');
    }
    if (DB.port < 1 || DB.port > 65535) {
        errors.push(`DB_PORT invalid: ${DB.port} (harus 1-65535)`);
    }

    // SHARD
    if (SHARD.index < 0) {
        errors.push(`SHARD_INDEX invalid: ${SHARD.index} (harus >= 0)`);
    }
    if (SHARD.total < 1) {
        errors.push(`TOTAL_SHARDS invalid: ${SHARD.total} (harus >= 1)`);
    }
    if (SHARD.index >= SHARD.total) {
        errors.push(
            `SHARD_INDEX (${SHARD.index}) >= TOTAL_SHARDS (${SHARD.total})`,
        );
    }

    if (errors.length > 0) {
        throw new Error(
            `\n` +
                `╔═══════════════════════════════════════════════════════════\n` +
                `  E2E CONFIG VALIDATION FAILED\n` +
                `╔═══════════════════════════════════════════════════════════\n` +
                `\n` +
                errors.map((e) => `  ✗ ${e}`).join('\n') +
                `\n\n` +
                `Cek file .env.testing Anda atau environment variable.\n` +
                `╔═══════════════════════════════════════════════════════════\n`,
        );
    }
}

// Auto-validate saat import — fail-fast sebelum test jalan
validateConfig();

// ==========================================
// INJECT KE process.env
// ==========================================
/**
 * Inject `LARAVEL_STORAGE_PATH` ke `process.env`.
 *
 * Tujuan: subprocess (`php artisan`, `php -S`) inherit env ini.
 * Laravel baca `$_ENV['LARAVEL_STORAGE_PATH']` di `Application::storagePath()`.
 *
 * Tanpa ini, 8 shard tulis ke `storage/` shared → race condition.
 */
process.env.LARAVEL_STORAGE_PATH = PATHS.storage;