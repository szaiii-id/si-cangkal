// resources/js/types/auth.ts
//
// Re-export `Auth` dan `AuthUser` dari `inertia.ts`.
// `AuthUser` adalah subset user yang dikirim via shared props
// (bukan full `User` model).

export type { Auth, AuthUser } from './inertia';
