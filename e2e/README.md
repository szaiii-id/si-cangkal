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
