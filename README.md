# SI-CANGKAL

Aplikasi pemesanan paket wisata berbasis web dengan tiga peran: **admin**, **mitra** (penyedia paket), dan **client** (pemesan). Client mencari paket di katalog, melakukan booking dan upload bukti transfer; mitra memverifikasi pembayaran dan menjalankan trip; admin memantau seluruh transaksi dan laporan.

## Arsitektur singkat

```mermaid
flowchart TD
    A[Client: pilih paket di katalog] --> B[Buat booking]
    B --> C[Upload bukti transfer]
    C --> D[Mitra verifikasi pembayaran]
    D --> E[Event BookingConfirmed]
    E --> F[CreateTripForBooking<br/>Trip berstatus scheduled]
    F --> G[GenerateETicketJob]
    F --> H[Scheduler: trip active]
    H --> I[Mitra menandai trip selesai]
    I --> J[Client memberi review: bintang + pesan]
    J --> K[Katalog menampilkan rating & ulasan]
```

Alur request mengikuti lapisan:

`routes/web.php` → Controller per role → FormRequest → Policy → Service → Repository (Contracts + Eloquent) → Model.

Binding repository/service ada di `app/Providers/AppServiceProvider.php`. Auto-discovery event **dimatikan**; `BookingConfirmed` dipetakan eksplisit di `EventServiceProvider` ke `CreateTripForBooking` (sync). Listener itu membuat `Trip` + `TripDay`, lalu mengantrekan `GenerateETicketJob`.

Notifikasi in-app/Push FCM ditangani `NotificationDispatcher` (push diantrekan sebagai `SendPushNotificationJob`), bukan oleh listener event. Semua job antrean memakai `after_commit=true` agar tidak dijalankan sebelum transaksi database selesai.

## Tech stack

| Komponen         | Versi / catatan                                                       |
| ---------------- | --------------------------------------------------------------------- |
| PHP              | ^8.3                                                                  |
| Laravel          | 13                                                                    |
| Inertia.js       | v3                                                                    |
| Svelte           | 5 (runes saja: `$props`, `$state`, `$derived`, `$effect`)             |
| Tailwind CSS     | v4, dikonfigurasi di `resources/css/app.css` (`@theme`)               |
| Frontend tooling | **Vite+** (`vp`), bukan Vite biasa                                    |
| Package manager  | **Bun** (`bun.lock`) — bukan npm/pnpm                                 |
| Database         | **PostgreSQL 16 wajib** (migrasi memakai `jsonb`)                     |
| PDF              | DomPDF (Blade di `resources/views/pdf` dan `resources/views/reports`) |
| Notifikasi       | Firebase Cloud Messaging + tabel `notifications`                      |

## Quick start (lokal)

```bash
composer install
bun install
cp .env.example .env
php artisan key:generate

# Nyalakan PostgreSQL 16 (docker). Jalankan sekali → membuat sicangkal_db
# dan sicangkal_db_testing lewat docker/postgres/init/01-create-testing-db.sql
docker compose up -d pgsql

php artisan migrate
php artisan wayfinder:generate --with-form

# Jalankan semua proses: serve + queue:listen + pail + Vite+
composer dev
```

Aplikasi tersedia di `http://localhost:8000`.

> **Catatan queue:** jalankan queue worker saat mengerjakan E-Ticket atau notifikasi. `composer dev` sudah menjalankannya.

## Environment variables

Sumber kebenaran adalah `.env.example`. Variabel yang paling sering diubah:

| Variabel               | Default                                       | Keterangan                                 |
| ---------------------- | --------------------------------------------- | ------------------------------------------ |
| `APP_NAME`             | `SI-CANGKAL`                                  | Nama aplikasi                              |
| `APP_ENV`              | `local`                                       | `local` / `testing` / `production`         |
| `APP_URL`              | `http://localhost:8000`                       | Harus `localhost` untuk E2E                |
| `APP_TIMEZONE`         | `Asia/Makassar`                               | **Diabaikan**—zona aplikasi hardcode `UTC` |
| `DB_*`                 | `127.0.0.1:5432`, `sicangkal_db`              | Database utama                             |
| `E2E_DB_*`             | `sicangkal_db_testing`                        | Database khusus Playwright                 |
| `BREVO_API_KEY`        | kosong                                        | Pengirim email transaksional               |
| `FIREBASE_CREDENTIALS` | `storage/app/firebase/firebase-adminsdk.json` | Service account FCM (gitignored)           |
| `VITE_FIREBASE_*`      | kosong                                        | Konfigurasi Firebase sisi client           |
| `GOOGLE_CLIENT_*`      | kosong                                        | Login Google OAuth                         |
| `AUTH_THROTTLE_*`      | lihat `.env.example`                          | Batas percobaan login/register/dll.        |
| `XENDIT_*`             | lihat bagian Xendit Test Mode                 | Payment gateway Test Mode                  |

Zona waktu: DB menyimpan **UTC**. Untuk logika "hari ini"/"sekarang" pakai `App\Support\Timezone`, **jangan** `now()` langsung. Timezone user/trip berformat IANA; default `Asia/Makassar`.

### Xendit Test Mode

Aplikasi memakai **Xendit Test Mode (sandbox)**. Semua fitur yang dipakai tersedia **tanpa KYC, tanpa XenPlatform, dan tanpa aktivasi channel**: Payment Session, webhook, refund, dan payout ke rekening mitra.

Semua kode membaca kredensial lewat `config('payment.xendit.*')`. Satu-satunya tempat yang memanggil `env('XENDIT_...')` adalah `config/payment.php`.

**Yang perlu dilakukan di dashboard Xendit:**

1. Daftar akun, verifikasi email, isi pertanyaan bisnis dasar → langsung mendapat akses **Test Mode** dengan dana virtual. **Jangan ajukan verifikasi bisnis/KYC.**
2. Settings → API Keys → **Generate Secret Key**. Beri permission `Payment Write`, `Payment Read`, `Payout/Money-out Write`, dan `Balance/Transaction Read`.
3. Settings → Webhooks → daftarkan URL `https://<host>/payments/xendit/webhook` untuk event Payment, Payment Session, Refund, dan Payout v3 (bisa memakai satu endpoint yang sama). Path alias `/xendit/webhook` juga diterima bila URL sudah terdaftar dengan path itu.
4. Salin **Callback Verification Token** (`x-callback-token`) dari halaman yang sama.
5. Untuk lokal, buka tunnel HTTPS ke `http://localhost:8000` (mis. `ngrok http 8000`) dan pakai URL-nya sebagai callback URL testing. Dokumentasi Xendit menyebut ngrok untuk keperluan ini.
6. Balance → **Top Up** di Test Mode bila saldo kosong. Dana simulasi tidak nyata dan tidak bisa ditarik; bila menu top-up tidak ada, minta dummy balance ke CS Xendit.

Isi `.env` lokal (nilai tidak pernah di-commit):

```dotenv
XENDIT_SECRET_KEY=xnd_development_...
XENDIT_CALLBACK_TOKEN=...
XENDIT_INSTANT_PAYOUT_ENABLED=false
```

Verifikasi konfigurasi:

```bash
php artisan config:show payment
grep -rn "env('XENDIT_" app routes resources tests --include='*.php' --include='*.ts' --include='*.svelte'
```

Perintah `grep` harus menghasilkan **kosong** di luar `config/payment.php`.

Alur pembayaran:

1. Mitra onboarding seperti biasa: profil + rekening tersimpan di database lokal. **Tidak ada request ke Xendit saat mitra daftar.**
2. Client memilih **Bayar dengan Xendit** (atau mitra membuat payment link).
3. Aplikasi membuat Payment Session `POST /sessions`; client diarahkan ke halaman checkout Xendit yang menampilkan QRIS/VA/e-wallet/kartu.
4. Webhook `payment.capture` diverifikasi lewat `x-callback-token`, booking menjadi `confirmed`, E-Ticket dibuat.
5. Dana ditahan sampai trip selesai. Setelah mitra menyelesaikan trip, `DisburseToMitraJob` memanggil `POST /disbursements` ke rekening mitra. Endpoint `/v3/payouts` ditolak untuk bank domestik Indonesia pada sandbox ini, jadi aplikasi memakai endpoint legacy yang masih aktif di Test Mode. Webhook `payout.succeeded` (atau `v3_payout.succeeded`) menandai dana `SETTLED`.
6. Refund sebelum payout memakai `POST /v3/refunds`; setelah dana cair, refund dialihkan ke jalur manual mitra.

Flag `XENDIT_INSTANT_PAYOUT_ENABLED=true` memindah trigger payout ke saat webhook pembayaran sukses — berguna untuk demo, bukan kebijakan produksi.

E2E `e2e/payment/xendit-payment.spec.ts` menguji alur checkout ke halaman Xendit Test Mode, lalu mengirim payload webhook bertanda tangan ke endpoint aplikasi (bukan webhook yang dipicu Xendit). Spec ini **skip** kecuali `XENDIT_E2E_ENABLED=true` dan `XENDIT_CALLBACK_TOKEN` terisi. Untuk pembuktian penuh dengan webhook asli dari Xendit, jalankan manual dengan tunnel aktif dan verifikasi status `SETTLED` di database.

## Testing & quality gate

```bash
# Backend
php artisan test                      #seluruh Pest
php artisan test --filter=BookingControllerTest
composer test                         #config:clear -> Pint -> PHPStan -> Pest

# Frontend
bun run test                          #Vitest (jsdom)
bun run check                         #format + lint (type-aware)
bun run types:check                   #svelte-check — beda dengan composer types:check
composer types:check                  #PHPStan level 7

# E2E — WAJIB build dulu karena Playwright menyajikan public/build
bun run build
bun run test:e2e
bun run test:e2e e2e/auth/login.spec.ts --project=chromium
bun run test:e2e:shard:4
bun run test:e2e:rate-limit

# E2E pembayaran Xendit Test Mode (butuh tunnel + kredensial test)
bun run test:e2e e2e/payment/xendit-payment.spec.ts --project=chromium
```

`bun run check` **bukan** `svelte-check`; keduanya harus dijalankan. Komponen di `resources/js/components/ui/*` dikecualikan dari format/lint Vite+.

Sebelum selesai mengerjakan fitur backend/frontend, jalankan: `bun run check`, `bun run types:check`, `bun run test`, dan `composer test`.

### CI otomatis

Perintah tersebut juga dijalankan GitHub Actions setiap push/PR ke `main`:

- `.github/workflows/tests.yml` — Pint, PHPStan, `bun run check`, `bun run types:check`, Pest (dengan Postgres 16), Vitest, dan audit dependency
- `.github/workflows/e2e.yml` — Playwright chromium/firefox/webkit + rate-limit, lengkap dengan upload report

`.github/dependabot.yml` memantau pembaruan GitHub Actions, Composer, JavaScript/Bun, dan Docker setiap minggu.

### Catatan E2E

- `.env.testing` sudah ter-commit dan dipakai Pest serta E2E.
- Playwright menyalakan `php -S` sendiri di `http://localhost:8000` dan **menolak** server yang sudah berjalan.
- Host harus `localhost`, **bukan** `127.0.0.1` (support check FCM).
- Butuh klien `psql` dan user DB dengan hak `CREATEDB`.
- Butuh file service account Firebase di `storage/app/firebase/` (gitignored; CI menulis dummy).
- `QUEUE_CONNECTION=sync` dipaksakan agar generasi E-Ticket deterministik—jangan diubah.
- WebKit hanya berjalan saat `CI=true`.
- Endpoint `/_test/*` hanya ada saat `APP_ENV=testing`.
- Detail lengkap: [`e2e/README.md`](e2e/README.md).

## Struktur direktori inti

```
app/
├── Enums/                     # status & label (BookingStatus, TripStatus, ...)
├── Http/
│   ├── Controllers/{Admin,Mitra,Client}/
│   ├── Requests/              # FormRequest per aksi
│   └── Resources/             # shape JSON ke frontend
├── Models/
├── Policies/
├── Repositories/
│   ├── Contracts/
│   └── Eloquent/
├── Services/<Domain>/
├── Support/                   # Timezone, LikeEscaper, AuditLogger
└── Providers/
database/
├── migrations/
├── factories/
└── seeders/
resources/
├── js/
│   ├── pages/<Role>/          # halaman Inertia (nama = path)
│   ├── api/                   # wrapper URL manual untuk router
│   ├── utils/enums/           # mirror label/status dari PHP
│   └── types/                 # mirror tipe
├── css/app.css
└── views/{pdf,reports}/       # template DomPDF
routes/web.php
tests/                         # Pest (Feature + Unit)
e2e/                           # Playwright
docker/postgres/init/          # inisialisasi DB testing saat container pertama dibuat
```

Dua aturan frontend yang mudah terlewat:

- Halaman Inertia memakai nama **path-based**, mis. `Inertia::render('Client/Bookings/Show')`.
- File di `resources/js/{actions,routes,wayfinder}` dihasilkan Wayfinder dan **gitignored**—jangan diedit. Regenerate setelah route/controller berubah dengan `php artisan wayfinder:generate --with-form`.

## Deployment singkat

```bash
bun run build
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan migrate --force
```

Pastikan `APP_DEBUG=false`, HTTPS dipaksakan otomatis di production, dan password admin seeder tercatat di kanal log `audit` (atau diatur lewat `ADMIN_PROD_PASSWORD`).
