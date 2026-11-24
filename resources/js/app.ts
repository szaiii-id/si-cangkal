import { createInertiaApp, router } from '@inertiajs/svelte';
import { showToast } from '@/utils/toast.svelte';
import { onForegroundMessage } from '@/lib/firebase';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';
const VIEWER_TZ_COOKIE = 'viewer_timezone';

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
        const secure = window.location.protocol === 'https:' ? '; Secure' : '';
        document.cookie =
            [
                `${VIEWER_TZ_COOKIE}=${encoded}`,
                `max-age=${maxAge}`,
                'path=/',
                'SameSite=Lax',
            ].join('; ') + secure;
    } catch {}
}

function setupGlobalErrorHandler(): void {
    // 1. Tangkap error validasi standar (422) dari Laravel
    router.on('error', (event: any) => {
        const errors = event.detail?.errors;
        if (errors?.rate_limit) {
            showGlobalAlert(errors.rate_limit, 'warning');
        }
    });

    // 2. Tangkap error HTTP 429 (Rate Limit) yang bukan 422
    // TypeScript kadang tidak mengenali 'invalid' di versi ini, jadi kita bypass dengan 'as any'
    (router as any).on('invalid', (event: any) => {
        // Cegah Inertia menampilkan modal error merah penuh layar
        event.preventDefault();

        const status = event.detail?.response?.status;

        // Jika Rate Limit (429)
        if (status === 429) {
            let message =
                'Terlalu banyak percobaan. Silakan tunggu beberapa saat.';

            // Jika response JSON memiliki pesan/errors (sesuai ValidationException 429 kita)
            if (event.detail.response.data?.errors) {
                const firstErrorKey = Object.keys(
                    event.detail.response.data.errors,
                )[0];
                message =
                    event.detail.response.data.errors[firstErrorKey][0] ||
                    message;
            } else if (event.detail.response.data?.message) {
                message = event.detail.response.data.message;
            }

            showGlobalAlert(message, 'warning');
        }
    });
}

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

if ('serviceWorker' in navigator) {
    navigator.serviceWorker
        .register('/firebase-messaging-sw.js')
        .then((registration) => {
            console.log('FCM Service Worker registered:', registration.scope);
        })
        .catch((error) => {
            console.error('FCM Service Worker registration failed:', error);
        });
}

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    progress: {
        color: '#4B5563',
    },
});
