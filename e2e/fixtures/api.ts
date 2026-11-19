// e2e/fixtures/api.ts
/**
 * HTTP client wrapper untuk endpoint `/_test/*` dan route testing lainnya.
 *
 * Fondasi untuk `seed.ts`, `auth.ts`, dan `test.ts`.
 *
 * Fitur:
 * - CSRF token otomatis (cache di memory)
 * - Retry 3x dengan exponential backoff
 * - Error handling yang jelas (`ApiError`)
 * - Type-safe (`apiGet<T>`, `apiPost<T>`)
 * - Timeout per request (default 10s)
 * - Pakai `page.request.fetch` → share cookie dengan browser context
 *
 * Pemakaian:
 *   import { apiPost, apiGet } from '../fixtures/api';
 *
 *   await apiPost(page, '/_test/login', { email, password });
 *   const data = await apiGet<{ ok: boolean }>(page, '/_test/health');
 */

import type { Page } from '@playwright/test';
import { APP } from '../config';

// ==========================================
// TIPE
// ==========================================

/**
 * Data yang dikirim ke API.
 * - `FormData` → multipart
 * - `object` → JSON
 */
export type ApiData = Record<string, unknown> | FormData;

/**
 * Opsi untuk `apiRequest`.
 */
export type ApiOptions = {
    /**
     * Timeout per request (ms). Default: 10_000.
     */
    timeout?: number;

    /**
     * Retry attempts. Default: 3.
     */
    retries?: number;

    /**
     * Headers tambahan.
     */
    headers?: Record<string, string>;

    /**
     * Kalau `true`, throw error kalau status bukan 2xx.
     * Default: `true`.
     */
    throwOnError?: boolean;
};

/**
 * Error dari API request.
 *
 * Menyimpan context lengkap untuk debugging:
 * - `status`: HTTP status
 * - `url`: URL yang dipanggil
 * - `body`: response body (bisa kosong)
 * - `method`: HTTP method
 */
export class ApiError extends Error {
    constructor(
        public readonly status: number,
        public readonly url: string,
        public readonly body: string,
        public readonly method: string,
    ) {
        super(
            `API ${method} ${url} failed with status ${status}: ${
                body || '(no body)'
            }`,
        );
        this.name = 'ApiError';
    }
}

// ==========================================
// CSRF TOKEN CACHE
// ==========================================

/**
 * Cache CSRF token per page.
 *
 * Token disimpan di memory, tidak query ulang tiap request.
 * Cache dibersihkan kalau ada `ApiError` 419 (token expired).
 */
const csrfCache = new WeakMap<Page, string>();

/**
 * Ambil CSRF token dari cookie `XSRF-TOKEN`.
 *
 * - Kalau sudah di-cache, return cached.
 * - Kalau page masih `about:blank` → navigasi dulu (fix SecurityError).
 * - Kalau cookie belum ada, goto `/` untuk set cookie.
 * - Kalau tetap tidak ada, throw Error.
 */
export async function getCsrfToken(page: Page): Promise<string> {
    const cached = csrfCache.get(page);
    if (cached) return cached;

    // ⚠️ FIX: kalau page masih about:blank, navigasi dulu.
    // Tanpa ini, `page.evaluate()` di about:blank → SecurityError.
    if (page.url() === 'about:blank') {
        await page.goto('/', { waitUntil: 'domcontentloaded' });
    }

    // Coba baca cookie dari halaman saat ini
    const token = await page.evaluate(() => {
        const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
        return match ? decodeURIComponent(match[1]) : '';
    });

    if (token) {
        csrfCache.set(page, token);
        return token;
    }

    // Cookie belum ada — goto `/` untuk set cookie
    await page.goto('/', { waitUntil: 'domcontentloaded' });

    const newToken = await page.evaluate(() => {
        const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
        return match ? decodeURIComponent(match[1]) : '';
    });

    if (!newToken) {
        throw new Error(
            `getCsrfToken: cookie XSRF-TOKEN tidak ditemukan. ` +
                `Cek apakah APP_URL (${APP.url}) dapat diakses.`,
        );
    }

    csrfCache.set(page, newToken);
    return newToken;
}

/**
 * Hapus cache CSRF token (mis. setelah logout).
 */
export function clearCsrfCache(page: Page): void {
    csrfCache.delete(page);
}

// ==========================================
// API REQUEST
// ==========================================

/**
 * Request generik ke API.
 *
 * - Otomatis ambil CSRF token.
 * - Retry 3x dengan exponential backoff.
 * - Throw `ApiError` kalau status bukan 2xx (kecuali `throwOnError: false`).
 * - Pakai `page.request.fetch` → share cookie dengan browser context.
 *
 * @example
 *   const data = await apiRequest<{ ok: boolean }>(page, 'GET', '/_test/health');
 */
export async function apiRequest<T = unknown>(
    page: Page,
    method: 'GET' | 'POST' | 'PUT' | 'DELETE',
    url: string,
    data?: ApiData,
    options: ApiOptions = {},
): Promise<T> {
    const {
        timeout = 10_000,
        retries = 3,
        headers: extraHeaders = {},
        throwOnError = true,
    } = options;

    const fullUrl = url.startsWith('http') ? url : `${APP.url}${url}`;

    let lastError: Error | null = null;

    for (let attempt = 1; attempt <= retries; attempt++) {
        try {
            // Build headers
            const headers: Record<string, string> = {
                Accept: 'application/json',
                ...extraHeaders,
            };

            if (method !== 'GET') {
                headers['X-XSRF-TOKEN'] = await getCsrfToken(page);
            }

            // Build body
            let body: string | FormData | undefined;

            if (data instanceof FormData) {
                body = data;
            } else if (data) {
                body = JSON.stringify(data);
                headers['Content-Type'] = 'application/json';
            }

            // ⚠️ FIX: pakai `page.request.fetch` (bukan `fetch` global).
            // `fetch` global (Node.js) TIDAK share cookie dengan browser
            // context → session tidak tersimpan → login gagal.
            // `page.request.fetch` share cookie → session tersimpan.
            const response = await page.request.fetch(fullUrl, {
                method,
                headers,
                data: body,
                timeout,
                failOnStatusCode: false,
            });

            // Handle error
            if (!response.ok()) {
                const responseBody = await response.text().catch(() => '');

                // CSRF token expired (419) → clear cache, retry
                if (response.status() === 419) {
                    clearCsrfCache(page);

                    if (attempt < retries) {
                        lastError = new ApiError(
                            response.status(),
                            fullUrl,
                            responseBody,
                            method,
                        );
                        await sleep(100 * attempt);
                        continue;
                    }
                }

                if (throwOnError) {
                    throw new ApiError(
                        response.status(),
                        fullUrl,
                        responseBody,
                        method,
                    );
                }

                // throwOnError: false → return body sebagai T
                return responseBody as unknown as T;
            }

            // Parse response
            const contentType = response.headers()['content-type'] ?? '';

            if (contentType.includes('application/json')) {
                return (await response.json()) as T;
            }

            return (await response.text()) as unknown as T;
        } catch (error) {
            // Kalau `ApiError` dan status 4xx selain 419, jangan retry
            if (
                error instanceof ApiError &&
                error.status >= 400 &&
                error.status < 500 &&
                error.status !== 419
            ) {
                throw error;
            }

            lastError = error as Error;

            if (attempt < retries) {
                await sleep(100 * attempt);
            }
        }
    }

    throw lastError ?? new Error(`apiRequest: gagal setelah ${retries} attempt.`);
}

// ==========================================
// CONVENIENCE METHODS
// ==========================================

/**
 * GET request.
 */
export async function apiGet<T = unknown>(
    page: Page,
    url: string,
    options?: ApiOptions,
): Promise<T> {
    return apiRequest<T>(page, 'GET', url, undefined, options);
}

/**
 * POST request.
 */
export async function apiPost<T = unknown>(
    page: Page,
    url: string,
    data?: ApiData,
    options?: ApiOptions,
): Promise<T> {
    return apiRequest<T>(page, 'POST', url, data, options);
}

/**
 * PUT request.
 */
export async function apiPut<T = unknown>(
    page: Page,
    url: string,
    data?: ApiData,
    options?: ApiOptions,
): Promise<T> {
    return apiRequest<T>(page, 'PUT', url, data, options);
}

/**
 * DELETE request.
 */
export async function apiDelete<T = unknown>(
    page: Page,
    url: string,
    data?: ApiData,
    options?: ApiOptions,
): Promise<T> {
    return apiRequest<T>(page, 'DELETE', url, data, options);
}

// ==========================================
// UTILITY
// ==========================================

/**
 * Sleep helper.
 */
function sleep(ms: number): Promise<void> {
    return new Promise((resolve) => setTimeout(resolve, ms));
}