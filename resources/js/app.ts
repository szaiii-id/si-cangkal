import { createInertiaApp, router } from '@inertiajs/svelte';
import { showToast } from '@/utils/toast.svelte';
import { onForegroundMessage } from '@/utils/firebase';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

/**
 * Nama cookie yang menyimpan timezone viewer.
 *
 * HARUS SINKRON dengan `HandleInertiaRequests::VIEWER_TZ_COOKIE`.
 */
const VIEWER_TZ_COOKIE = 'viewer_timezone';

/**
 * Set cookie `viewer_timezone` berdasarkan timezone browser.
 */
function syncViewerTimezoneCookie(): void {
    try {
        const tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
        if (!tz) return;

        const encoded = encodeURIComponent(tz);

        const existing = document.cookie
            .split('; ')
            .find((row) => row.startsWith(`${VIEWER_TZ_COOKIE}=`))
            ?.split('=')[1];

        if (existing === encoded) return;

        const maxAge = 60 * 60 * 24 * 365;
        const secure =
            window.location.protocol === 'https:' ? '; Secure' : '';

        document.cookie =
            [
                `${VIEWER_TZ_COOKIE}=${encoded}`,
                `max-age=${maxAge}`,
                'path=/',
                'SameSite=Lax',
            ].join('; ') + secure;
    } catch {
        // Abaikan — server akan fallback
    }
}

/**
 * Global error handler Inertia.
 */
function setupGlobalErrorHandler(): void {
    router.on('error', (event) => {
        const errors = event.detail.errors;

        if (errors?.rate_limit) {
            showGlobalAlert(errors.rate_limit, 'warning');
        }
    });
}

/**
 * Handler notifikasi FCM saat user sedang membuka aplikasi (foreground).
 * Menampilkan toast, bukan notifikasi OS (karena tab sudah aktif).
 */
function setupForegroundFcmHandler(): void {
    onForegroundMessage((payload) => {
        const p = payload as {
            notification?: { title?: string; body?: string };
            data?: { url?: string };
        };

        const title = p.notification?.title ?? 'Notifikasi Baru';
        const body = p.notification?.body ?? '';

        showToast(`${title}: ${body}`, 'info');
    });
}

/**
 * Tampilkan notifikasi global via toast.
 */
function showGlobalAlert(
    message: string,
    variant: 'warning' | 'error' | 'success' | 'info' = 'warning',
): void {
    showToast(message, variant);
}

// ==========================================
// BOOTSTRAP
// ==========================================

syncViewerTimezoneCookie();
setupGlobalErrorHandler();
setupForegroundFcmHandler();

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    progress: {
        color: '#4B5563',
    },
});