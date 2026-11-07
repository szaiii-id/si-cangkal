// resources/js/types/index.ts

// ==========================================
// AUTH — explicit re-export, exclude duplikat
// ==========================================
// `auth.ts` sudah re-export `Auth` & `AuthUser` dari `inertia.ts`,
// jadi kita ambil dari `auth.ts` saja. Ini mencegah duplikasi
// dengan `export * from './inertia'` di bawah.
export type { Auth, AuthUser } from './auth';

// ==========================================
// SISA — wildcard (tidak ada konflik)
// ==========================================
export * from './enum';
export * from './filter';

// ✅ Hati-hati: `inertia.ts` punya `Auth` & `AuthUser` juga.
// Kita ambil field lainnya secara eksplisit untuk hindari duplikasi.
export type {
    Flash,
    Credentials,
    SharedProps,
    PageWithSharedProps,
} from './inertia';

export * from './models';
export * from './pagination';