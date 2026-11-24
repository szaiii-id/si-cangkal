// e2e/global-teardown.ts
/**
 * Global teardown untuk Playwright E2E.
 *
 * Dijalankan SEKALI setelah semua test (per shard).
 *
 * Default: TIDAK hapus storage, TIDAK drop DB.
 *   → supaya bisa debug kalau ada error (log, session, data).
 *
 * Opt-in via env:
 *   - `E2E_KEEP_STORAGE=0` → hapus storage shard setelah run
 *   - `E2E_DROP_DB=1`      → drop database shard setelah run
 *
 * ⚠️ Cleanup TIDAK boleh block test result. Kalau cleanup gagal,
 * log warning tapi jangan throw error.
 */

import { execSync } from 'node:child_process';
import fs from 'node:fs';
import { DB, PATHS, SHARD } from './config';

// ==========================================
// LOGGER
// ==========================================
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
// GLOBAL TEARDOWN
// ==========================================
export default async function globalTeardown(): Promise<void> {
    const shardLabel = SHARD.isSharded
        ? `shard-${SHARD.index}/${SHARD.total}`
        : 'single';

    logSection(`GLOBAL TEARDOWN — ${shardLabel}`);

    // 1. Cleanup storage (opt-in)
    if (process.env.E2E_KEEP_STORAGE === '0') {
        cleanupStorage();
    } else {
        logInfo(`Storage TIDAK dihapus (set E2E_KEEP_STORAGE=0 untuk hapus).`);
    }

    // 2. Drop database (opt-in)
    if (process.env.E2E_DROP_DB === '1') {
        dropDatabase();
    } else {
        logInfo(`Database TIDAK di-drop (set E2E_DROP_DB=1 untuk drop).`);
    }

    logSection(`GLOBAL TEARDOWN SELESAI — ${shardLabel}`);
}

// ==========================================
// 1. CLEANUP STORAGE
// ==========================================
/**
 * Hapus folder storage shard.
 *
 * Berguna untuk CI (hemat disk) atau reset total.
 *
 * ⚠️ Kalau gagal, log warning (jangan throw) — cleanup tidak
 * boleh block test result.
 */
function cleanupStorage(): void {
    logInfo(`Cleanup storage: ${PATHS.storage}`);

    if (!fs.existsSync(PATHS.storage)) {
        logInfo('  Storage tidak ada, skip.');
        return;
    }

    try {
        fs.rmSync(PATHS.storage, { recursive: true, force: true });
        logSuccess('Storage dihapus.');
    } catch (error) {
        logWarning(`Gagal hapus storage: ${(error as Error).message}`);
    }
}

// ==========================================
// 2. DROP DATABASE
// ==========================================
/**
 * Drop database shard.
 *
 * ⚠️ Ini DESTRUKTIF. Hanya jalan kalau `E2E_DROP_DB=1`.
 *
 * Pakai `WITH (FORCE)` (PostgreSQL 13+) untuk disconnect
 * semua koneksi aktif sebelum drop.
 *
 * ⚠️ Kalau gagal, log warning (jangan throw).
 */
function dropDatabase(): void {
    logInfo(`Drop database: ${DB.database}`);

    const basePsql = `PGPASSWORD="${shellEscape(DB.password)}" psql -h ${DB.host} -p ${DB.port} -U ${DB.username} -d postgres`;

    // Cek dulu apakah DB ada
    try {
        const checkSql = `SELECT 1 FROM pg_database WHERE datname = '${DB.database}'`;
        const result = execSync(`${basePsql} -tAc "${checkSql}"`, {
            encoding: 'utf-8',
            stdio: ['ignore', 'pipe', 'pipe'],
        }).trim();

        if (result !== '1') {
            logInfo('  Database tidak ada, skip.');
            return;
        }
    } catch (error) {
        logWarning(
            `Gagal cek database ${DB.database}: ${(error as Error).message}`,
        );
        return;
    }

    // Drop dengan FORCE (PostgreSQL 13+)
    const dropSql = `DROP DATABASE IF EXISTS "${DB.database}" WITH (FORCE)`;

    try {
        execSync(`${basePsql} -c "${dropSql}"`, {
            stdio: 'pipe',
        });
        logSuccess(`Database di-drop: ${DB.database}`);
    } catch (error) {
        // Fallback: kalau FORCE tidak didukung (PostgreSQL < 13),
        // coba drop biasa
        logWarning(
            `Drop dengan FORCE gagal, coba tanpa FORCE: ${(error as Error).message}`,
        );

        try {
            const dropNoForceSql = `DROP DATABASE IF EXISTS "${DB.database}"`;
            execSync(`${basePsql} -c "${dropNoForceSql}"`, {
                stdio: 'pipe',
            });
            logSuccess(`Database di-drop (tanpa FORCE): ${DB.database}`);
        } catch (fallbackError) {
            logWarning(
                `Gagal drop database: ${(fallbackError as Error).message}`,
            );
        }
    }
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
