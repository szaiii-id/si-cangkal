# E2E Test — SI-CANGKAL

Dokumentasi lengkap untuk E2E test menggunakan Playwright.

---

## 📋 Overview

E2E test ini menguji **alur end-to-end** aplikasi SI-CANGKAL dari perspektif user:

- **Auth** — login, register, change password, forgot password
- **Client** — catalog, booking, upload proof, E-Ticket, profile
- **Mitra** — onboarding, package CRUD, verify payment, refund, trip
- **Admin** — user management, booking monitoring, trip monitoring, report

### Stack

| Komponen      | Teknologi                                       |
| ------------- | ----------------------------------------------- |
| **Framework** | Playwright                                      |
| **Bahasa**    | TypeScript                                      |
| **Browser**   | Chromium, Firefox, WebKit (official Playwright) |
| **A11y**      | `@axe-core/playwright`                          |
| **Backend**   | Laravel (PHP)                                   |
| **Database**  | PostgreSQL                                      |
| **Frontend**  | Svelte + Inertia                                |

---

## 📁 Struktur Folder

| Path                                                 | Isi                                                                    |
| ---------------------------------------------------- | ---------------------------------------------------------------------- |
| `fixtures/base.ts`                                   | `test`, `expect`, `asAdmin`, `asMitra`, `asClient`, `seed`, `resetAll` |
| `pages/`                                             | Page object untuk selector bersama                                     |
| `config.ts`, `global-setup.ts`, `global-teardown.ts` | Konfigurasi host, DB sharding, seed, cleanup                           |
| `auth/`, `client/`, `mitra/`, `admin/`               | Spes per peran                                                         |
| `payment/`                                           | Alur checkout/webhook Xendit (butuh `XENDIT_E2E_ENABLED`)              |
| `security/`, `accessibility/`                        | IDOR/rate-limit dan axe scan WCAG AA                                   |

## ▶️ Perintah cepat

```bash
bun run build                 # wajib before E2E (Playwright menyajikan public/build)
bun run test:e2e              # semua browser/project normal
bun run test:e2e e2e/auth/login.spec.ts --project=chromium
bun run test:e2e:shard:4      # 4 shard DB terpisah
bun run test:e2e:rate-limit   # project khusus rate-limit
```

Catatan: host harus `localhost` (bukan `127.0.0.1`) agar support check FCM lolos, dan
PostgreSQL + klien `psql` wajib tersedia.
