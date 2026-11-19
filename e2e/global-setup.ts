// e2e/global-setup.ts
/**
 * Global setup untuk Playwright E2E.
 *
 * Dijalankan SEKALI sebelum semua test (per shard).
 *
 * Urutan:
 * 1. Cleanup artifact lama (test-results, playwright-report)
 * 2. Setup storage shard (storage/shard-N/)
 * 3. Buat database shard jika belum ada
 * 4. migrate:fresh
 * 5. db:seed --class=TestSeeder (deterministik, cepat)
 * 6. Verifikasi seeder (via `php artisan e2e:verify-seeder`)
 * 7. Health-check `/_test/health`
 *
 * ⚠️ Semua step WAJIB sukses. Kalau gagal, throw Error dengan
 * pesan jelas — Playwright akan abort semua test.
 *
 * Kenapa per shard?
 * - Setiap shard punya proses Playwright sendiri (tidak share state).
 * - Setiap shard punya DB sendiri (isolasi penuh).
 * - Setiap shard punya storage sendiri (hindari race compiled view).
 *
 * Kenapa `TestSeeder` (bukan `DatabaseSeeder`)?
 * - `TestSeeder` deterministik (password `"password"`, email `@test.com`).
 * - `DatabaseSeeder` pakai password acak (`Str::password(16)`) — sulit
 *   di-test karena harus baca audit log untuk login.
 * - `TestSeeder` cepat (4 user) vs `DatabaseSeeder` (12 user + relasi).
 * - `TestSeeder` sesuai `SEED_USERS` di `e2e/fixtures/auth.ts`.
 *
 * Kenapa `e2e:verify-seeder` (bukan `tinker`)?
 * - `tinker --execute="..."` rapuh: escaping shell rumit, output
 *   ke STDERR, dll.
 * - Artisan command: type-safe (PHP native), exit code jelas,
 *   bisa di-test dengan Pest.
 */

import { execSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { setTimeout as sleep } from 'node:timers/promises';
import {
    APP,
    ARTISAN_ENV,
    DB,
    HEALTHCHECK,
    PATHS,
    PROJECT_ROOT,
    SHARD,
} from './config';

// ==========================================
// KONSTANTA SEEDER
// ==========================================
/**
 * FQCN `TestSeeder` — dipakai untuk `db:seed --class=...`.
 *
 * ⚠️ Harus pakai double backslash di string JS, karena `\` adalah
 * escape character. `Database\\Seeders\\TestSeeder` →
 * `Database\Seeders\TestSeeder` saat dieksekusi.
 */
const TEST_SEEDER_CLASS = 'Database\\\\Seeders\\\\TestSeeder';

/**
 * Email user yang di-seed `TestSeeder` (untuk log verifikasi).
 *
 * Harus SINKRON dengan:
 * - `app/Console/Commands/VerifySeeder.php`
 * - `e2e/fixtures/auth.ts` (`SEED_USERS`)
 */
const SEEDED_USERS: Array<{ email: string; role: string }> = [
    { email: 'admin@test.com', role: 'admin' },
    { email: 'mitra@test.com', role: 'mitra' },
    { email: 'client@test.com', role: 'client' },
];

// ==========================================
// SECTION LOGGER
// ==========================================
/**
 * Log section header — memudahkan baca log panjang.
 */
function logSection(title: string): void {
    console.log(`\n[E2E] ═══════════════════════════════════════════════`);
    console.log(`[E2E] ${title}`);
    console.log(`[E2E] ═══════════════════════════════════════════════`);
}

function logInfo(message: string): void {
    console.log(`[E2E] ${message}`);
}

function logSuccess(message: string): void {
    console.log(`[E2E] ✅ ${message}`);
}

function logWarning(message: string): void {
    console.warn(`[E2E] ⚠️  ${message}`);
}

// ==========================================
// GLOBAL SETUP
// ==========================================
export default async function globalSetup(): Promise<void> {
    const shardLabel = SHARD.isSharded
        ? `shard-${SHARD.index}/${SHARD.total}`
        : 'single';

    logSection(`GLOBAL SETUP — ${shardLabel}`);
    logInfo(`APP:     ${APP.url}`);
    logInfo(`DB:      ${DB.database}`);
    logInfo(`Storage: ${PATHS.storage}`);
    logInfo(`Output:  ${PATHS.outputDir}`);
    logInfo(`Report:  ${PATHS.reportDir}`);

    // 1. Cleanup artifact lama
    cleanupArtifacts();

    // 2. Setup storage shard
    ensureShardStorage();

    // 3. Buat database shard
    createDatabaseIfNotExists();

    // 4. migrate:fresh
    runArtisan('migrate:fresh', 'Migrasi database...', { force: true });

    // 5. db:seed — pakai TestSeeder (deterministik, cepat)
    runArtisan('db:seed', 'Mengisi seed data...', {
        force: true,
        args: `--class=${TEST_SEEDER_CLASS}`,
    });

    // 6. Verifikasi seeder (via artisan command)
    verifySeeder();

    // 7. Health-check `/_test/health`
    await waitForTestRoutes(shardLabel);

    logSection(`GLOBAL SETUP SELESAI — ${shardLabel}`);
    logSuccess('Database, storage, dan route /_test/* siap.');
    console.log('');
}

// ==========================================
// 1. CLEANUP ARTIFACT
// ==========================================
/**
 * Hapus artifact lama (test-results, playwright-report) untuk shard ini.
 *
 * Tujuan: hindari artifact basi dari run sebelumnya.
 *
 * ⚠️ Hanya hapus folder shard ini (tidak sentuh shard lain).
 */
function cleanupArtifacts(): void {
    logInfo('Cleanup artifact lama...');

    const dirsToClean = [PATHS.outputDir, PATHS.reportDir];

    for (const dir of dirsToClean) {
        const fullPath = path.resolve(PROJECT_ROOT, dir);

        if (fs.existsSync(fullPath)) {
            fs.rmSync(fullPath, { recursive: true, force: true });
            logInfo(`  Hapus: ${dir}`);
        }
    }

    // Buat ulang folder output (Playwright butuh)
    fs.mkdirSync(path.resolve(PROJECT_ROOT, PATHS.outputDir), {
        recursive: true,
    });

    logSuccess('Artifact lama dibersihkan.');
}

// ==========================================
// 2. STORAGE SHARD
// ==========================================
/**
 * Buat struktur folder storage shard.
 *
 * Diperlukan karena Laravel (via `LARAVEL_STORAGE_PATH`) menulis ke:
 * - `framework/views/`   → compiled Blade/Svelte
 * - `framework/cache/`   → file cache
 * - `framework/sessions/`→ session file (kalau pakai file driver)
 * - `logs/`              → laravel.log
 *
 * ⚠️ Tanpa ini, Laravel error "failed to open stream" saat menulis.
 */
function ensureShardStorage(): void {
    logInfo('Setup storage shard...');

    const dirs = [
        'app',
        'framework/cache',
        'framework/cache/data',
        'framework/sessions',
        'framework/views',
        'logs',
    ];

    for (const dir of dirs) {
        const fullPath = path.join(PATHS.storage, dir);
        fs.mkdirSync(fullPath, { recursive: true });
    }

    logSuccess(`Storage dibuat: ${PATHS.storage}`);
}

// ==========================================
// 3. DATABASE
// ==========================================
/**
 * Buat database shard jika belum ada.
 *
 * Menggunakan `psql` (PostgreSQL client).
 *
 * ⚠️ Kalau gagal, throw Error (jangan silent skip).
 * Silent skip → migrate:fresh gagal dengan pesan membingungkan.
 */
function createDatabaseIfNotExists(): void {
    logInfo(`Cek database: ${DB.database}`);

    const checkSql = `SELECT 1 FROM pg_database WHERE datname = '${DB.database}'`;
    const createSql = `CREATE DATABASE "${DB.database}" OWNER "${DB.username}"`;

    const basePsql = `PGPASSWORD="${shellEscape(DB.password)}" psql -h ${DB.host} -p ${DB.port} -U ${DB.username} -d postgres`;

    let exists = false;

    try {
        const result = execSync(`${basePsql} -tAc "${checkSql}"`, {
            encoding: 'utf-8',
            stdio: ['ignore', 'pipe', 'pipe'],
        }).trim();

        exists = result === '1';
    } catch (error) {
        throw new Error(
            `[E2E] Gagal cek database ${DB.database}: ${(error as Error).message}\n` +
                `Pastikan PostgreSQL berjalan di ${DB.host}:${DB.port} ` +
                `dan user "${DB.username}" punya izin.`,
        );
    }

    if (exists) {
        logInfo(`  Database sudah ada.`);
        return;
    }

    logInfo(`  Database belum ada, membuat...`);

    try {
        execSync(`${basePsql} -c "${createSql}"`, {
            stdio: 'inherit',
        });
    } catch (error) {
        throw new Error(
            `[E2E] Gagal membuat database ${DB.database}: ${(error as Error).message}\n` +
                `Pastikan user "${DB.username}" punya izin CREATEDB.`,
        );
    }

    logSuccess(`Database dibuat: ${DB.database}`);
}

// ==========================================
// 4. ARTISAN
// ==========================================
/**
 * Jalankan `php artisan {command}` dengan env testing.
 *
 * @param command - Nama command (mis. `migrate:fresh`)
 * @param message - Pesan log
 * @param options - Opsi:
 *   - `force`: tambah `--force` flag
 *   - `args`: argumen tambahan (mis. `--class=...`)
 *
 * ⚠️ Throw Error (bukan `process.exit`) — biarkan Playwright
 * cleanup dan tampilkan error yang rapi.
 */
function runArtisan(
    command: string,
    message: string,
    options: { force?: boolean; args?: string } = {},
): void {
    const { force = false, args = '' } = options;
    const forceFlag = force ? ' --force' : '';
    const argsFlag = args ? ` ${args}` : '';

    logInfo(`${message}`);

    const envString = Object.entries(ARTISAN_ENV)
        .map(([key, value]) => `${key}=${shellEscape(value)}`)
        .join(' ');

    try {
        execSync(`${envString} php artisan ${command}${forceFlag}${argsFlag}`, {
            cwd: PROJECT_ROOT,
            stdio: 'inherit',
            env: { ...process.env, ...ARTISAN_ENV },
        });
    } catch (error) {
        throw new Error(
            `[E2E] Gagal menjalankan: php artisan ${command}${forceFlag}${argsFlag}\n` +
                `Error: ${(error as Error).message}\n` +
                `Cek log di atas untuk detail.`,
        );
    }
}

// ==========================================
// 5. VERIFIKASI SEEDER
// ==========================================
/**
 * Verifikasi data seed penting ada di database.
 *
 * Panggil `php artisan e2e:verify-seeder` (custom command) — lebih
 * robust dari `tinker` karena:
 * - Tidak ada escaping shell yang rumit.
 * - Exit code jelas (0 = OK, 1 = gagal).
 * - Bisa di-test dengan Pest.
 *
 * Command: `app/Console/Commands/VerifySeeder.php`
 *
 * User yang dicek (sesuai `TestSeeder`):
 * - admin@test.com (role=admin)
 * - mitra@test.com (role=mitra)
 * - client@test.com (role=client)
 *
 * Kalau ada yang hilang → throw Error (seed gagal).
 */
function verifySeeder(): void {
    logInfo('Verifikasi seed data...');

    const envString = Object.entries(ARTISAN_ENV)
        .map(([key, value]) => `${key}=${shellEscape(value)}`)
        .join(' ');

    try {
        execSync(`${envString} php artisan e2e:verify-seeder`, {
            cwd: PROJECT_ROOT,
            stdio: 'inherit',
            env: { ...process.env, ...ARTISAN_ENV },
        });
    } catch (error) {
        throw new Error(
            `[E2E] Verifikasi seeder gagal: ${(error as Error).message}\n` +
                `Cek TestSeeder — mungkin tidak dijalankan atau gagal.\n` +
                `Debug manual: php artisan e2e:verify-seeder`,
        );
    }

    logSuccess(
        `Seed data terverifikasi (${SEEDED_USERS.map((u) => u.role).join(', ')}).`,
    );
}

// ==========================================
// 6. HEALTH-CHECK `/_test/health`
// ==========================================
/**
 * Polling `GET /_test/health` sampai status 200.
 *
 * Tujuan: fail-fast jika route `/_test/*` tidak terdaftar.
 *
 * Penyebab umum:
 * - `APP_ENV` bukan `testing` → `bootstrap/app.php` tidak load `web-testing.php`.
 * - Port zombie (`php -S` dari run sebelumnya).
 * - Storage shard tidak writable.
 *
 * Status yang dianggap berhasil: 200 (atau 4xx selain 404).
 * Status yang dianggap gagal: 404 (route tidak ada) atau network error.
 */
async function waitForTestRoutes(shardLabel: string): Promise<void> {
    const url = `${APP.url}${HEALTHCHECK.path}`;
    const { maxAttempts, intervalMs, timeoutMs } = HEALTHCHECK;

    logInfo(`Health-check ${HEALTHCHECK.path}...`);
    logInfo(`  URL: ${HEALTHCHECK.method} ${url}`);

    let lastStatus: number | null = null;
    let lastError: string | null = null;

    for (let attempt = 1; attempt <= maxAttempts; attempt++) {
        try {
            const controller = new AbortController();
            const timer = setTimeout(() => controller.abort(), timeoutMs);

            const response = await fetch(url, {
                method: HEALTHCHECK.method,
                headers: {
                    Accept: 'application/json',
                },
                signal: controller.signal,
            });

            clearTimeout(timer);
            lastStatus = response.status;
            lastError = null;

            // Route terdaftar jika status BUKAN 404
            if (response.status !== 404) {
                let body: unknown = null;
                try {
                    body = await response.json();
                } catch {
                    // ignore
                }

                logSuccess(
                    `Route /_test/* terdaftar (HTTP ${response.status}, attempt ${attempt}/${maxAttempts})`,
                );

                // Cek body: `{ok: true, env: 'testing'}`
                if (
                    body &&
                    typeof body === 'object' &&
                    'env' in body &&
                    body.env !== 'testing'
                ) {
                    logWarning(
                        `APP_ENV bukan 'testing' (dapat: ${body.env}). ` +
                            `Route /_test/* mungkin tidak aktif.`,
                    );
                }

                return;
            }

            // 404 — retry
            if (attempt < maxAttempts) {
                logInfo(
                    `  404 (attempt ${attempt}/${maxAttempts}), retry ${intervalMs}ms...`,
                );
                await sleep(intervalMs);
            }
        } catch (error) {
            const err = error as Error;
            lastError = err.message;
            lastStatus = null;

            if (attempt < maxAttempts) {
                logInfo(
                    `  Network error: ${err.message} ` +
                        `(attempt ${attempt}/${maxAttempts}), retry ${intervalMs}ms...`,
                );
                await sleep(intervalMs);
            }
        }
    }

    throw new Error(
        `\n` +
            `═══════════════════════════════════════════════════════════\n` +
            `  HEALTH-CHECK GAGAL — ${shardLabel}\n` +
            `═══════════════════════════════════════════════════════════\n` +
            `\n` +
            `URL:              ${HEALTHCHECK.method} ${url}\n` +
            `Status terakhir:  ${lastStatus ?? 'network error'}\n` +
            `Error terakhir:   ${lastError ?? '-'}\n` +
            `\n` +
            `Kemungkinan penyebab:\n` +
            `  1. APP_ENV bukan 'testing' → route /_test/* tidak terdaftar\n` +
            `  2. Port ${APP.port} dipakai proses lain (zombie php -S)\n` +
            `     → cek: lsof -i:${APP.port} atau ss -tlnp | grep ${APP.port}\n` +
            `     → kill: kill -9 <pid>\n` +
            `  3. Storage shard tidak writable\n` +
            `     → cek: ls -la ${PATHS.storage}\n` +
            `  4. bootstrap/app.php tidak load routes/web-testing.php\n` +
            `     → cek: app()->environment('testing')\n` +
            `\n` +
            `Debug manual:\n` +
            `  curl -i ${url}\n` +
            `═══════════════════════════════════════════════════════════\n`,
    );
}

// ==========================================
// UTILITY
// ==========================================
/**
 * Escape nilai agar aman dipakai di shell command.
 */
function shellEscape(value: string): string {
    return `"${value.replace(/"/g, '\\"')}"`;
}