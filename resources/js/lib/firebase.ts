import { initializeApp } from 'firebase/app';
import { getMessaging, getToken, onMessage } from 'firebase/messaging';

// Konfigurasi diambil dari .env (Vite)
const firebaseConfig = {
    apiKey: import.meta.env.VITE_FIREBASE_API_KEY,
    authDomain: import.meta.env.VITE_FIREBASE_AUTH_DOMAIN,
    projectId: import.meta.env.VITE_FIREBASE_PROJECT_ID,
    storageBucket: import.meta.env.VITE_FIREBASE_STORAGE_BUCKET,
    messagingSenderId: import.meta.env.VITE_FIREBASE_MESSAGING_SENDER_ID,
    appId: import.meta.env.VITE_FIREBASE_APP_ID,
};

// Inisialisasi Firebase App
const app = initializeApp(firebaseConfig);

/**
 * Inisialisasi Messaging.
 * Cek `typeof window` dan `serviceWorker` agar aman saat SSR.
 */
export const messaging =
    typeof window !== 'undefined' && 'serviceWorker' in navigator
        ? getMessaging(app)
        : null;

/**
 * Cek apakah browser user mendukung FCM Web Push.
 *
 * Syarat:
 * - Berjalan di HTTPS (atau localhost)
 * - Mendukung Service Worker
 * - Mendukung PushManager
 * - Memiliki `Notification` API
 */
export function isFcmSupported(): boolean {
    if (typeof window === 'undefined') return false;

    // Wajib HTTPS (kecuali localhost)
    const isSecure =
        window.location.protocol === 'https:' ||
        window.location.hostname === 'localhost';

    if (!isSecure) return false;

    // Wajib support Service Worker, Push API, dan Notification
    return (
        'serviceWorker' in navigator &&
        'PushManager' in window &&
        'Notification' in window
    );
}

/**
 * Minta izin notifikasi ke user dan dapatkan FCM Token.
 * @returns Token string, atau null jika gagal/ditolak.
 */
export async function requestFcmToken(): Promise<string | null> {
    if (!messaging) {
        console.warn('FCM tidak didukung di browser ini.');
        return null;
    }

    try {
        const permission = await Notification.requestPermission();

        if (permission !== 'granted') {
            console.warn('User menolak izin notifikasi.');
            return null;
        }

        const currentToken = await getToken(messaging, {
            vapidKey: import.meta.env.VITE_FIREBASE_VAPID_KEY,
        });

        if (currentToken) {
            return currentToken;
        }

        console.warn('Gagal mendapatkan token FCM. Pastikan VAPID Key valid.');
        return null;
    } catch (error) {
        console.error('Error saat mengambil token FCM:', error);
        return null;
    }
}

/**
 * Handler jika notifikasi masuk saat aplikasi sedang DIBUKA (foreground).
 */
export function onForegroundMessage(
    callback: (payload: unknown) => void,
): void {
    if (!messaging) return;

    onMessage(messaging, (payload) => {
        callback(payload);
    });
}
