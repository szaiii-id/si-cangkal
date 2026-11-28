# Desain: Primary Key UUID & Koordinat Paket

Tanggal: 2026-11-27

## Intent

Memenuhi permintaan: primary key `users` dan `bookings` diganti dari `id` numerik menjadi UUID (bukan sekadar kolom tambahan), serta menambahkan koordinat `latitude`/`longitude` pada paket agar tautan Google Maps dan file `.ics` menunjukkan lokasi yang presisi.

## Goals

1. `users.id` dan `bookings.id` bertipe UUID string sebagai primary key.
2. Semua foreign key yang merujuk ke keduanya bertipe UUID dan tetap memiliki constraint + index.
3. Koordinat paket tersedia dari form mitra sampai ke halaman publik dan file `.ics`.
4. `.ics` memakai acara all-day, UID stabil, `GEO`, `LOCATION`, dan `URL` Maps.
5. Seluruh gate (Pint, PHPStan, Pest, Vitest, check, types, build, E2E) lulus setelah perubahan.

## Non-goals / anti-goals

- Mengubah PK entitas lain (`packages`, `trips`, `payments`, `refunds`, `reviews`, `notifications`, `webhook_events`, dll.) — tetap integer / UUID seperti semula.
- Menambah API Google Maps berbayar atau SDK — tetap memakai Maps URLs.
- Mengubah alur webhook, status booking, atau logika refund.
- Menyimpan data produksi saat konversi — `migrate:fresh` disetujui pengguna.

## Constraints

- Laravel 13, `HasUuids` menghasilkan UUID v4; model wajib `$keyType = 'string'` dan `$incrementing = false`.
- Semua `migrate:fresh` hanya untuk environment lokalan/testing; produksi tidak terkena.
- Tailwind v4, Svelte 5 runes, Inertia path-based pages.
- Koordinat: `latitude decimal(10,7)`, `longitude decimal(11,7)`, nullable.
- ICS mengikuti RFC 5545: CRLF, lipatan baris 75 oktet, `VALUE=DATE` untuk all-day, `DTEND` eksklusif.

## Keputusan desain

### 1. Schema (ditulis ulang di migrasi yang sudah ada, lalu `migrate:fresh`)

- `users.id` → `$table->uuid('id')->primary()`
- `bookings.id` → `$table->uuid('id')->primary()`
- FK ke users/bookings → `$table->uuid('...')` + `constrained()` + index:
    - `sessions.user_id`, `profiles.user_id`, `packages.mitra_id`, `bookings.user_id`, `bookings.mitra_id`,
    - `trips.booking_id`, `trips.mitra_id`, `payments.booking_id`, `payments.verified_by`,
    - `refunds.booking_id`, `refunds.mitra_id`, `refunds.initiated_by_mitra`, `refunds.proof_uploaded_by`,
    - `booking_participants.booking_id`, `e_tickets.booking_id`, `booking_payment_links.booking_id`,
    - `reviews.booking_id`, `reviews.user_id`, `notifications.user_id`, `mitra_bank_accounts.user_id`.
- `personal_access_tokens` → `uuidMorphs('tokenable')`.
- `packages` → tambah `latitude`, `longitude`.

### 2. Model

- `User`, `Booking`: `use HasUuids`, `$keyType = 'string'`, `$incrementing = false`, docblock `string $id`.
- Model lain: docblock FK `string`, tanpa mengubah PK-nya.
- `Trip::hasMitra()` tidak boleh lagi memakai `> 0` — gunakan cek string kosong/null.

### 3. Backend

- Type hint ID user/booking di repository, service, job, event: `int` → `string`.
- `EmailVerificationRequest`: perbandingan `(int) $route('id')` diganti perbandingan string.
- `routes/web-testing.php`: validasi `booking_id` dari `integer` menjadi `string` dengan `exists:bookings,id`, dan `findOrFail` tanpa `(int)`.
- Form request `StorePackageRequest` / `UpdatePackageRequest`: `latitude`/`longitude` nullable `numeric` + `between`.
- `PackageService`, `PackageController`, `PackageResource`, `BookingResource`, `TripService::buildSnapshot`, `PublicETicketController`: teruskan koordinat.

### 4. Frontend

- Tipe `User.id`, `BookingEloquent.id`, FK terkait, dan `bookingId` di API wrapper: `number` → `string`.
- Form paket Create/Edit: input koordinat (opsional), dikirim lewat FormData.
- `externalLinks.ts`: `googleMapsUrl` menerima koordinat opsional; bila ada, query memakai `lat,lng`.
- `calendar.ts`: bangun ICS all-day + UID stabil dari UUID booking + `GEO` + `URL` Maps + lipatan baris.
- `Client/Bookings/Show.svelte`, `Client/Catalog/Show.svelte`, `e-tickets/verify.blade.php`: pakai koordinat jika tersedia.

### 5. E2E & test

- Page object `ClientBookingPage` / `MitraBookingPage`: `bookingId: string`.
- Helper seed: tipe ID UUID; helper ekstraksi ID dari URL tidak lagi memakai `(\d+)`/`Number()`, melainkan regex UUID.
- Semua mock/test yang memakai ID numerik untuk user/booking disesuaikan.
- `migrate:fresh` dijalankan sebelum seluruh gate.

## Urutan implementasi (tingkat tinggi)

1. Migrasi + model + koreksi type hint backend.
2. Frontend types + E2E types.
3. Koordinat paket (backend, form, resource, seed/factory).
4. Maps + ICS presisi.
5. Gate penuh + E2E.

## Risiko & mitigasi

- **Risiko:** banyak asumsi numerik tersembunyi → mitigasi: PHPStan level 7 + `svelte-check` + seluruh Pest/Vitest/E2E.
- **Risiko:** E2E regex `(\d+)` mengambil angka dari URL UUID → mitigasi: regex UUID eksplisit.
- **Risiko:** `migrate:fresh` menghapus data lokal → mitigasi: hanya di environment lokal/testing, seed ulang.
