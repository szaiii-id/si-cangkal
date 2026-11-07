// public/firebase-messaging-sw.js

// Import Firebase SDK khusus Service Worker (versi compat)
importScripts('https://www.gstatic.com/firebasejs/10.8.0/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/10.8.0/firebase-messaging-compat.js');

const firebaseConfig = {
    apiKey: "AIzaSyDILEgosZNLhfD3rYR4c53KcVF9XwQG_dM",
    authDomain: "titiktemu-ebed3.firebaseapp.com",
    projectId: "titiktemu-ebed3",
    storageBucket: "titiktemu-ebed3.firebasestorage.app",
    messagingSenderId: "1023650453890",
    appId: "1:1023650453890:web:905677d5f89c87286e979b",
};

firebase.initializeApp(firebaseConfig);
const messaging = firebase.messaging();

// ==========================================
// HANDLER KLIK NOTIFIKASI (Deep Linking)
// ==========================================
self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    // Ambil URL dari data payload, fallback ke /notifications
    let urlToOpen = event.notification.data?.url || '/notifications';

    // Validasi Keamanan: Pastikan URL adalah path internal
    // (mencegah open redirect jika data dimanipulasi)
    if (typeof urlToOpen !== 'string' || !urlToOpen.startsWith('/')) {
        urlToOpen = '/notifications';
    }

    event.waitUntil(
        clients
            .matchAll({ type: 'window', includeUncontrolled: true })
            .then((windowClients) => {
                // 1. Cari tab yang sudah terbuka dengan URL yang sama
                for (const client of windowClients) {
                    if (client.url.includes(urlToOpen) && 'focus' in client) {
                        return client.focus();
                    }
                }
                // 2. Jika tidak ada, buka tab baru
                if (clients.openWindow) {
                    return clients.openWindow(urlToOpen);
                }
            }),
    );
});

