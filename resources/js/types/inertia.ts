/**
 * Types untuk Inertia shared props & page.
 *
 * Mirror dari `HandleInertiaRequests::share()`.
 */

import type { AppNotification } from './models';

// ==========================================
// FLASH
// ==========================================

export type Flash = {
    status?: string | null;
    error?: string | null;
    retry_after?: number | null;
    credentials?: Credentials | null;
};

/**
 * Kredensial user yang di-flash setelah admin buat/reset user.
 * Hanya muncul 1x (di halaman berikutnya).
 */
export type Credentials = {
    role: string;
    name: string;
    email: string;
    username: string | null;
    password: string;
};

// ==========================================
// AUTH
// ==========================================

/**
 * User yang di-share via `HandleInertiaRequests`.
 *
 *   Ini BUKAN `User` model lengkap — hanya subset yang dikirim
 * oleh `HandleInertiaRequests::share()['auth']['user']`.
 *
 * Field yang TIDAK ada di sini (mis. `created_at`, `updated_at`,
 * `profile`) sengaja tidak disertakan karena backend tidak mengirimnya
 * via shared props. Kalau butuh data itu, ambil dari endpoint lain
 * (mis. `/profile`).
 */
export type AuthUser = {
    id: string;
    name: string;
    username: string | null;
    email: string;
    role: 'admin' | 'mitra' | 'client';
    timezone: string;
    email_verified_at: string | null;
    has_verified_email: boolean;
    has_fcm_token: boolean;
};

export type Auth = {
    user: AuthUser | null;
};

// ==========================================
// SHARED PROPS
// ==========================================

export type SharedProps = {
    name: string;
    auth: Auth;
    flash: Flash;
    viewer_timezone: string;
    timezone_options: Record<string, string>;
    server_timezone: string;
    unread_notifications: number;
};

// ==========================================
// PAGE PROPS (extended)
// ==========================================

export type PageWithSharedProps = {
    url: string;
    component: string;
    props: SharedProps & Record<string, unknown>;
};

// ==========================================
// RE-EXPORT
// ==========================================

export type { AppNotification };
