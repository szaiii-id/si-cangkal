# Tautan Pembayaran Publik & Invoice Implementation Plan

> **Status: ARSIP — as-built.** Fitur ini sudah diimplementasikan. Checklist di bawah
> sengaja dibiarkan apa adanya sebagai catatan historis dan bukan TODO aktif.
> Rencana otoritatif saat ini: `/home/z-code/.opencode/plan/2026-11-25-si-cangkal-upgrade.md`.

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Memberi booking manual satu tautan publik berkirim-WA yang bisa dibuka tanpa login, berisi tombol bayar Xendit dan invoice PDF yang bisa diunduh, dengan email otomatis sebagai pelengkap.

**Architecture:** Token UUID di tabel terpisah menjadi capability URL, dibuat/dirotasi di `PaymentChargeService` sehingga semua jalur checkout konsisten. Halaman publik memakai Blade ringan seperti E-Ticket, PII disamarkan, dimatikan saat booking berubah status lewat observer. Invoice dibuat on-demand dengan DomPDF; email dikirim via Mailable yang memakai `attachData()`.

**Tech Stack:** Laravel 13, PostgreSQL, Pest, DomPDF, Blade, Inertia/Svelte untuk UI mitra, Brevo mailer.

**Spec:** `docs/superpowers/specs/2026-11-25-public-payment-link-invoice-design.md`

## Global Constraints

- `expires_at` tautan berasal dari `payments.gateway_payload.expires_at` (Payment Session Xendit), bukan `booking.expired_at`.
- Token harus UUID acak, unik, tidak memakai `booking.id`/kode.
- Halaman publik tanpa login, PII disamarkan, header `noindex, nofollow, noarchive`, `no-referrer`, `no-store`, throttle.
- Email hanya saat token baru dibuat; dikirim lewat queue dengan `after_commit=true`.
- Tidak mengubah endpoint client `POST /client/bookings/{booking}/pay` (`Inertia::location()`).
- Pola E-Ticket/Report Service dipakai untuk PDF; `App\Support\Timezone` untuk tanggal.
- Copy dan komentar Bahasa Indonesia.

## Review Focus

- Booking normal tidak boleh ikut dapat email otomatis → uji `sumber` = `aplikasi` tidak mengirim mail.
- Token tidak boleh tetap aktif setelah lunas/batal → uji observer mencabut pada tiap status akhir.
- Session expired tidak boleh meninggalkan token yang menunjuk ke sesi basi → uji rotasi saat `payment_session.expired`.
- Halaman publik tidak boleh membocorkan email/HP/daftar peserta → uji assertions teks tersamarkan.
- Klik berulang mitra tidak boleh mengirim email duplikat → uji reuse token tidak meng-queue mail.

---

### Task 1: Schema & model

**Files:**

- Create: `database/migrations/2026_11_25_000001_add_sumber_to_bookings_table.php`
- Create: `database/migrations/2026_11_25_000002_create_booking_payment_links_table.php`
- Create: `app/Models/BookingPaymentLink.php`
- Modify: `app/Models/Booking.php`
- Test: `tests/Feature/Payment/BookingPaymentLinkSchemaTest.php`

**Interfaces:**

- Produces: `BookingPaymentLink` dengan `token`, `booking_id`, `expires_at`, `revoked_at`, relasi `booking()`, scope `active()`.
- Produces: `bookings.sumber` (`aplikasi`|`manual`).

- [ ] **Step 1: Tulis test gagal** — schema memiliki kolom `sumber`, tabel `booking_payment_links` dengan `token` unique; `BookingPaymentLink::active()` hanya mengembalikan baris dengan `revoked_at` null dan `expires_at` di masa depan.
- [ ] **Step 2: Jalankan test, pastikan gagal.**
- [ ] **Step 3: Buat dua migrasi dan model** — `sumber` default `aplikasi`; tabel dengan FK `bookings` cascade; cast `expires_at`/`revoked_at` datetime.
- [ ] **Step 4: Jalankan test, pastikan lolos.**
- [ ] **Step 5: Commit** — `git commit -m "feat(payment): schema tautan pembayaran publik"`.

### Task 2: Lifecycle token di PaymentChargeService

**Files:**

- Create: `app/Services/Payment/BookingPaymentLinkService.php`
- Modify: `app/Services/Payment/PaymentChargeService.php`
- Modify: `app/Http/Controllers/Mitra/BookingController.php`
- Modify: `app/Services/Booking/BookingCreationService.php`
- Test: `tests/Feature/Payment/BookingPaymentLinkServiceTest.php`, `tests/Feature/Client/BookingPayTest.php`

**Interfaces:**

- Consumes: `XenditSessionResult` (punya `payload`, `checkoutUrl`, `sessionId`).
- Produces: `BookingPaymentLinkService::forBooking(Booking $booking, XenditSessionResult $session): BookingPaymentLink` (reuse token aktif atau rotasi), `::revokeActive(Booking $booking): void`, `::findByToken(string $token): ?BookingPaymentLink`.
- Produces: `Mitra\BookingController::paymentLink()` mengembalikan `{url, expires_at, checkout_url}`.

- [ ] **Step 1: Tulis test gagal** — dua kali `createForBooking()` dengan payment pending menghasilkan satu token; `expires_at` diambil dari `session.payload.expires_at`; `createManual()` menulis `sumber = manual`.
- [ ] **Step 2: Jalankan test, pastikan gagal.**
- [ ] **Step 3: Implementasi service** — cari token aktif yang payment-nya masih pending; bila ada pakai ulang; bila tidak, revoke lama dan buat UUID baru.
- [ ] **Step 4: Panggil service di `PaymentChargeService`** setelah session berhasil dibuat; perbarui response mitra; jangan ubah controller client.
- [ ] **Step 5: Jalankan test, pastikan lolos.**
- [ ] **Step 6: Commit** — `git commit -m "feat(payment): token publik dan expires_at session"`.

### Task 3: Halaman publik

**Files:**

- Create: `app/Http/Controllers/PublicPaymentLinkController.php`
- Create: `resources/views/payment-links/show.blade.php`
- Modify: `routes/web.php`, `bootstrap/app.php`
- Test: `tests/Feature/Payment/PublicPaymentLinkTest.php`

**Interfaces:**

- Consumes: `BookingPaymentLinkService::findByToken()`.
- Produces: `GET /payments/{token}` nama `payments.public` (Blade `payment-links.show`).

- [ ] **Step 1: Tulis test gagal** — token tidak ditemukan/dicabut/kedaluwarsa → 404; token aktif → 200, menampilkan kode booking dan nama client tersamarkan, **tidak** mengandung emailclient atau daftar peserta; header `noindex` hadir.
- [ ] **Step 2: Jalankan test, pastikan gagal.**
- [ ] **Step 3: Implementasi controller dan view** — baca payment pending terbaru, tombol bayar hanya bila `gateway_status = pending`; pakai `maskName()` seperti `PublicETicketController`; header keamanan; throttle `throttle:30,1`.
- [ ] **Step 4: Daftarkan route publik + CSRF exception bila perlu**, regenerate Wayfinder.
- [ ] **Step 5: Jalankan test, pastikan lolos.**
- [ ] **Step 6: Commit** — `git commit -m "feat(payment): halaman pembayaran publik"`.

### Task 4: Invoice PDF

**Files:**

- Create: `app/Services/Invoice/Contracts/InvoiceServiceInterface.php`
- Create: `app/Services/Invoice/InvoiceService.php`
- Create: `resources/views/pdf/invoice.blade.php`
- Modify: `app/Http/Controllers/PublicPaymentLinkController.php`, `routes/web.php`, `app/Providers/AppServiceProvider.php`
- Test: `tests/Feature/Payment/InvoiceTest.php`

**Interfaces:**

- Produces: `InvoiceService::pdfForBooking(Booking $booking): string` (output PDF), `::fileName(Booking $booking): string` → `Invoice-{kode}.pdf`.
- Produces: `GET /payments/{token}/invoice` nama `payments.public.invoice`.

- [ ] **Step 1: Tulis test gagal** — endpoint mengembalikan `application/pdf`, konten diawali `%PDF`, nama file sesuai kode, dan token dicabut → 404.
- [ ] **Step 2: Jalankan test, pastikan gagal.**
- [ ] **Step 3: Implementasi service + view** — DomPDF A4, data booking/package/participants/payments, rincian total, instruksi transfer manual sebagai fallback. Tidak menyimpan file.
- [ ] **Step 4: Bind interface, tambah route dan method controller** dengan header unduhan + `no-store`.
- [ ] **Step 5: Jalankan test, pastikan lolos.**
- [ ] **Step 6: Commit** — `git commit -m "feat(payment): invoice PDF booking"`.

### Task 5: Email otomatis

**Files:**

- Create: `app/Mail/BookingPaymentLinkMail.php`
- Create: `resources/views/emails/booking-payment-link.blade.php`
- Modify: `app/Services/Payment/BookingPaymentLinkService.php`
- Test: `tests/Feature/Payment/BookingPaymentLinkMailTest.php`

**Interfaces:**

- Produces: `BookingPaymentLinkMail` dengan `$url`, `$booking`, `$invoicePdf`; `attachData($invoicePdf, fileName(), ['mime' => 'application/pdf'])`.

- [ ] **Step 1: Tulis test gagal** — `Mail::fake()`, token baru untuk booking `manual` meng-queue satu mail; reuse token tidak mengirim mail; booking `aplikasi` tidak mengirim mail.
- [ ] **Step 2: Jalankan test, pastikan gagal.**
- [ ] **Step 3: Implementasi Mailable + view + pemanggilan** di service hanya saat token dibuat, dalam transaksi setelah commit (atau `after_commit` pada job/queue).
- [ ] **Step 4: Jalankan test, pastikan lolos.**
- [ ] **Step 5: Commit** — `git commit -m "feat(payment): email otomatis link dan invoice"`.

### Task 6: Pencabutan terpusat via observer

**Files:**

- Create: `app/Observers/BookingObserver.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Test: `tests/Feature/Payment/BookingPaymentLinkRevocationTest.php`

**Interfaces:**

- Consumes: `BookingPaymentLinkService::revokeActive()`.

- [ ] **Step 1: Tulis test gagal** — setiap transisi ke `PAYMENT_VERIFIED`, `CONFIRMED`, `IN_PROGRESS`, `COMPLETED`, `CANCELLED` mencabut token; transisi ke `PENDING_PAYMENT` tidak.
- [ ] **Step 2: Jalankan test, pastikan gagal.**
- [ ] **Step 3: Implementasi observer** pada event `updated` dengan `wasChanged('status')`, daftarkan di `AppServiceProvider::boot()`.
- [ ] **Step 4: Jalankan test, pastikan lolos.**
- [ ] **Step 5: Commit** — `git commit -m "feat(payment): cabut tautan saat booking berubah status"`.

### Task 7: UI mitra & gate akhir

**Files:**

- Modify: `resources/js/components/mitra/bookings/PaymentLinkCard.svelte`
- Modify: `resources/js/api/mitra.ts`
- Test: Vitest komponen, seluruh gate

**Interfaces:**

- Consumes: `Mitra\BookingController::paymentLink()` yang mengembalikan `url` (route publik) dan `expires_at`.

- [ ] **Step 1: Tulis test gagal** — kartu menampilkan tombol "Salin Tautan Pembayaran" yang menyalin `url` dari API dan menampilkan `expires_at`.
- [ ] **Step 2: Jalankan test, pastikan gagal.**
- [ ] **Step 3: Perbarui UI/API wrapper** — teks label disesuaikan; tombol lama "Buat & Salin Link" diganti/diperjelas; `navigator.clipboard` atau helper yang sudah ada.
- [ ] **Step 4: Jalankan gate** — `composer test`, `bun run check`, `bun run types:check`, `bun run test`, `bun run build`.
- [ ] **Step 5: Commit** — `git commit -m "feat(payment): UI salin tautan pembayaran"`.
- [ ] **Step 6: Review独立 branch** dengan subagent reviewer, perbaiki temuan, lalu laporkan hasil.
