# Xendit Payment Gateway Migration Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Mengganti DOKU secara penuh dengan Xendit Test Mode (Payment Session + webhook + refund + payout) tanpa KYC, XenPlatform, atau aktivasi channel, dengan mempertahankan perilaku bisnis `hold → release setelah trip selesai`.

**Architecture:** Satu adapter Xendit baru menggantikan `PaymentGatewayServiceInterface`/DTO DOKU. `PaymentChargeService` membuat Payment Session, webhook terverifikasi `x-callback-token` memfinalisasi booking lewat lock + re-check, dan `DisburseToMitraJob` mengirim dana ke rekening mitra dari `mitra_bank_accounts`. Onboarding mitra tetap murni lokal.

**Tech Stack:** Laravel 13, PHP 8.4, Pest, Inertia v3, Svelte 5 runes, Tailwind v4, PostgreSQL, Bun/Vitest, Playwright.

**Spec:** `docs/superpowers/specs/2026-11-25-xendit-sandbox-instant-payout-design.md`

## Global Constraints

- Semua `env('XENDIT_*')` **hanya** dibaca di `config/payment.php`. Kode lain wajib memakai `config('payment.xendit.*')`.
- Kode/config/env DOKU dihapus total, termasuk `throw` bila `DOKU_ENV !== sandbox`.
- Dilarang memakai `for-user-id`, `with-split-rule`, `/v3/accounts`, `/split_rules`, `/transfers`, `/account_holders`, `/account_verification`, `/v2/invoices`, `/v2/payouts`, Direct Debit, Kredivo, kartu non-3DS, BI SNAP.
- `MitraBankAccount::nomor_rekening` memakai cast `encrypted`; akses model menghasilkan plaintext, jangan dekripsi manual.
- Gunakan `App\Support\Timezone` untuk logika "today/now", bukan `now()` langsung.
- Lock + re-check status di dalam transaksi; notifikasi/event/audit setelah commit; `after_commit=true`.
- Enum backend dan mirror frontend (`resources/js/types`, `resources/js/utils/enums`) harus sinkron.
- User-facing copy dan komentar dalam Bahasa Indonesia.
- Gate sebelum selesai: `bun run check`, `bun run types:check`, `bun run test`, `composer test`, `bun run build`, lalu E2E.

## Review Focus

- Webhook duplikat/gagal urutan: `payment.capture` dan `payment_session.completed` untuk satu payment tidak boleh mengonfirmasi dua kali → uji idempotensi dengan `payment_id` di `WebhookEvent`.
- Amount/reference tidak cocok: webhook dengan `reference_id` atau amount berbeda harus ditolak tanpa mengubah booking → uji di processor.
- Payout double dispatch: retry job tidak boleh mengirim payout kedua → uji idempotency key `payout-{booking->kode}` dan guard `payout_id`.
- Payout gagal `INSUFFICIENT_BALANCE`: harus retry dengan backoff; `INVALID_DESTINATION` tidak di-retry → uji perluasan job.
- Refund setelah payout sukses: tidak boleh memanggil `/v3/refunds`; harus dialihkan ke refund manual mitra → uji guard di `RefundApprovalService`.

---

### Task 1: Konfigurasi, enum, dan mirror frontend

**Files:**

- Modify: `config/payment.php`
- Modify: `app/Enums/PaymentGateway.php`
- Modify: `app/Enums/PaymentMetode.php`
- Modify: `resources/js/utils/enums/payment.ts`
- Modify: `resources/js/types/enum.ts`
- Test: `tests/Feature/Payment/XenditConfigTest.php`, `resources/js/utils/__tests__/payment.test.ts`

**Interfaces:**

- Produces: `config('payment.xendit.*')` dengan kunci `base_url`, `secret_key`, `callback_token`, `payment_api_version` (`2024-11-11`), `payout_api_version` (`2025-09-01`), `instant_payout_enabled`, `platform_fee_percent`, `webhook_path`.

- [ ] **Step 1: Tulis test config gagal** — `expect(config('payment.xendit.base_url'))->toBe('https://api.xendit.co')`, `expect(config('payment.xendit.payment_api_version'))->toBe('2024-11-11')`, `expect(config('payment')->has('doku'))->toBeFalse()`, dan `expect(PaymentGateway::XENDIT->value)->toBe('xendit')`.
- [ ] **Step 2: Jalankan test, pastikan gagal** — `php artisan test tests/Feature/Payment/XenditConfigTest.php`.
- [ ] **Step 3: Implementasi** — ganti isi `config/payment.php` hanya dengan blok `xendit` (semua `env()` XENDIT ada di sini); ganti case `DOKU` menjadi `XENDIT` di kedua enum beserta label/badge; perbarui mirror TS.
- [ ] **Step 4: Jalankan test, pastikan lolos** — Pest + `bun run test resources/js/utils/__tests__/payment.test.ts`.
- [ ] **Step 5: Commit** — `git commit -m "feat(payment): konfigurasi Xendit dan enum gateway"`.

### Task 2: DTO, kontrak, dan adapter Xendit

**Files:**

- Create: `app/Data/Xendit/XenditSessionResult.php`, `XenditWebhookEvent.php`, `XenditRefundResult.php`, `XenditPayoutResult.php`, `XenditStatus.php`
- Create: `app/Services/Payment/Contracts/XenditPaymentServiceInterface.php`
- Create: `app/Services/Payment/Xendit/XenditGatewayService.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Test: `tests/Unit/Payment/XenditGatewayServiceTest.php`

**Interfaces:**

- Produces:
    - `createSession(Booking $booking, int $sequence = 1): XenditSessionResult`
    - `verifyWebhook(string $rawBody, ?string $callbackToken): XenditWebhookEvent`
    - `checkStatus(Booking $booking): XenditStatus`
    - `refund(Payment $payment, Refund $refund): XenditRefundResult`
    - `payout(Booking $booking, Payment $payment): XenditPayoutResult`
    - `getPayout(string $payoutId): XenditPayoutResult`
    - `XenditWebhookEvent` punya `event`, `paymentId`, `payoutId`, `referenceId`, `amount`, `status`, `payload`.

- [ ] **Step 1: Tulis test gagal** — payload `POST /sessions` mengandung `session_type=PAY`, `mode=PAYMENT_LINK`, `country=ID`, `currency=IDR`, `reference_id=booking.kode`; tidak ada `for-user-id`/`with-split-rule`; `verifyWebhook` menolak token salah.
- [ ] **Step 2: Jalankan test, pastikan gagal.**
- [ ] **Step 3: Implementasi DTO readonly dan adapter** — basic auth secret key, header `api-version`, `Http::asJson()`; payout memakai `idempotency-key = payout-{booking->kode}`; semua URL dari config.
- [ ] **Step 4: Bind interface di `AppServiceProvider`** dan hapus binding DOKU/B2B token.
- [ ] **Step 5: Jalankan test, pastikan lolos.**
- [ ] **Step 6: Commit** — `git commit -m "feat(payment): adapter Xendit session, refund, payout"`.

### Task 3: Checkout client dan link mitra

**Files:**

- Modify: `app/Services/Payment/PaymentChargeService.php`
- Modify: `app/Http/Controllers/Client/BookingController.php`
- Modify: `resources/js/api/client.ts`, `resources/js/api/mitra.ts`
- Modify: `resources/js/components/booking/PaymentOptionsCard.svelte`, `resources/js/components/mitra/bookings/PaymentLinkCard.svelte`
- Test: `tests/Feature/Client/BookingPayTest.php`, Vitest komponen terkait

**Interfaces:**

- Consumes: `XenditPaymentServiceInterface::createSession()`.
- Produces: `PaymentChargeService::createForBooking(Booking $booking, ?int $dueMinutes = null): XenditSessionResult`; `Payment` tersimpan dengan `gateway=xendit`, `gateway_request_id=payment_session_id`, `gateway_payload.payment_link_url`.

- [ ] **Step 1: Tulis test gagal** — checkout membuat `Payment` Xendit pending, memakai session yang sudah ada bila masih pending, dan mengembalikan `checkoutUrl`.
- [ ] **Step 2: Jalankan test, pastikan gagal.**
- [ ] **Step 3: Implementasi** — ganti tipe kembali `DokuChargeResult` menjadi `XenditSessionResult`, filter `gateway=xendit`; redirect pakai `Inertia::location()`.
- [ ] **Step 4: Perbarui UI/API wrapper** — `createDokuCharge` → `createXenditCharge`; label "Bayar dengan Xendit"; teks "Dana diteruskan ke rekening mitra setelah pembayaran berhasil".
- [ ] **Step 5: Jalankan Pest + Vitest, pastikan lolos.**
- [ ] **Step 6: Commit** — `git commit -m "feat(payment): checkout Xendit dan kartu pembayaran"`.

### Task 4: Webhook, idempotensi, dan konfirmasi booking

**Files:**

- Create: `app/Jobs/ProcessXenditNotificationJob.php`
- Create: `app/Services/Payment/XenditNotificationProcessor.php`
- Modify: `app/Http/Controllers/PaymentWebhookController.php`
- Modify: `routes/web.php`, `bootstrap/app.php`
- Delete: `app/Jobs/ProcessDokuNotificationJob.php`, `app/Services/Payment/DokuNotificationProcessor.php`
- Test: `tests/Feature/Payment/XenditWebhookTest.php`, `tests/Feature/Payment/ProcessXenditNotificationJobTest.php`

**Interfaces:**

- Consumes: `verifyWebhook()`.
- Produces: `XenditNotificationProcessor::process(XenditWebhookEvent $event): void`; webhook route `POST /payments/xendit/webhook` nama `payments.xendit.webhook`.

- [ ] **Step 1: Tulis test gagal** — token salah → 401; webhook duplikat → job hanya dipantre sekali; `payment.capture` dengan amount/reference cocok → booking `CONFIRMED`, `Payment.paid_at` terisi, `payment_id` tersimpan; amount beda → tidak ada perubahan.
- [ ] **Step 2: Jalankan test, pastikan gagal.**
- [ ] **Step 3: Implementasi controller** — `hash_equals` terhadap `config('payment.xendit.callback_token')`, `WebhookEvent` provider `xendit` dengan request_id `payment_id`/`payout_id`, balas 200, dispatch job; hapus method `subAccount()`.
- [ ] **Step 4: Implementasi processor** — lock `Booking` + `Payment`, re-check amount/reference/status di dalam transaksi, commit lalu notifikasi + `BookingConfirmed`.
- [ ] **Step 5: Ganti route dan CSRF exception**, hapus dua route DOKU.
- [ ] **Step 6: Jalankan test, pastikan lolos.**
- [ ] **Step 7: Commit** — `git commit -m "feat(payment): webhook Xendit terverifikasi dan idempoten"`.

### Task 5: Payout ke rekening mitra

**Files:**

- Create: `app/Jobs/DisburseToMitraJob.php`
- Modify: `app/Services/Trip/TripService.php`
- Delete: `app/Jobs/ReleaseDokuSettlementJob.php`
- Test: `tests/Feature/Payment/DisburseToMitraJobTest.php`, `tests/Feature/Payment/XenditSettlementTest.php`

**Interfaces:**

- Consumes: `XenditPaymentServiceInterface::payout()`/`getPayout()`; `XenditWebhookEvent` untuk `v3_payout.succeeded`.
- Produces: `DisburseToMitraJob(int $bookingId)`; `released_at` saat payout `ACCEPTED`, `settled_at` saat webhook sukses, `gateway_status=SETTLED`.

- [ ] **Step 1: Tulis test gagal** — job tidak mengirim payout bila `payout_id` sudah ada; `INSUFFICIENT_BALANCE` melempar retry; `INVALID_DESTINATION` gagal permanen; webhook sukses mengisi `settled_at` dan notifikasi mitra tepat sekali.
- [ ] **Step 2: Jalankan test, pastikan gagal.**
- [ ] **Step 3: Implementasi job** — ambil rekening primary, `nama_pemilik`, `nomor_rekening`, mapping `nama_penyedia` → channel code; jumlah = `payments.jumlah` dikurangi `platform_fee_percent`; simpan `payout_id` ke payload.
- [ ] **Step 4: Ganti trigger di `TripService::completeTrip()`** dari `ReleaseDokuSettlementJob` ke `DisburseToMitraJob`; flag `instant_payout_enabled` memindah trigger ke webhook payment.
- [ ] **Step 5: Jalankan test, pastikan lolos.**
- [ ] **Step 6: Commit** — `git commit -m "feat(payment): payout Xendit ke rekening mitra"`.

### Task 6: Refund gateway dan fallback manual

**Files:**

- Modify: `app/Services/Refund/RefundApprovalService.php`
- Modify: `app/Services/Refund/RefundInitiationService.php`
- Delete: `app/Jobs/SyncDokuRefundJob.php`
- Test: `tests/Feature/Payment/XenditRefundTest.php`, `tests/Feature/Mitra/RefundValidationTest.php`

**Interfaces:**

- Consumes: `XenditPaymentServiceInterface::refund()` dengan `payment_id`.
- Produces: `RefundInitiationService::completeManuallyAfterPayout(Refund $refund, User $mitra, ?string $catatan): Refund`.

- [ ] **Step 1: Tulis test gagal** — refund PENDING sebelum payout memanggil `/v3/refunds`; setelah ada `payout_id`, tidak memanggil gateway dan refund diselesaikan manual in-place.
- [ ] **Step 2: Jalankan test, pastikan gagal.**
- [ ] **Step 3: Implementasi** — guard `payout_id` di approval; method baru di initiation dengan lock, tanpa `ensureNoActiveRefund()`.
- [ ] **Step 4: Jalankan test, pastikan lolos.**
- [ ] **Step 5: Commit** — `git commit -m "feat(payment): refund Xendit dan fallback manual"`.

### Task 7: Hapus jejak DOKU dan onboarding sub-account

**Files:**

- Modify: `app/Http/Controllers/Mitra/OnboardingController.php`, `app/Http/Controllers/Auth/AuthController.php`
- Modify: `routes/web-testing.php`, `e2e/config.ts`, `e2e/fixtures/seed.ts`
- Delete: `app/Data/Doku/*`, `app/Services/Payment/Doku/*`, `app/Services/Payment/Contracts/PaymentGatewayServiceInterface.php`, `app/Console/Commands/GenerateDokuKeyPair.php`, spec E2E DOKU, test DOKU, `resources/js/routes/payments/doku/*`
- Test: seluruh Pest + Vitest

- [ ] **Step 1: Tulis/perbarui test gagal** — onboarding mitra tidak mendispatch job gateway apa pun; tidak ada referensi `Doku` di `app/`, `routes/`, `resources/js/`.
- [ ] **Step 2: Jalankan test, pastikan gagal.**
- [ ] **Step 3: Hapus kode DOKU** — termasuk `RegisterDokuSubAccountJob::dispatch()` di `OnboardingController::store()`; kolom `doku_*`/`settlement_*` di `mitra_bank_accounts` dibiarkan untuk kompatibilitas data tetapi tidak dipakai.
- [ ] **Step 4: Perbarui test/E2E config** — flag `XENDIT_*` menggantikan `DOKU_*`.
- [ ] **Step 5: Jalankan Pest + Vitest, pastikan lolos.**
- [ ] **Step 6: Commit** — `git commit -m "refactor(payment): hapus integrasi DOKU"`.

### Task 8: E2E, dokumentasi, dan panduan dashboard Xendit

**Files:**

- Create: `e2e/payment/xendit-payment.spec.ts`
- Modify: `e2e/README.md`, `README.md`, `docs/superpowers/specs/...design.md`
- Test: E2E + build

- [ ] **Step 1: Tulis E2E gagal** — `asClient` buat booking → checkout Xendit → `simulate` payment → webhook → `Payment` `SETTLED`, notifikasi mitra muncul; tidak ada `skip`.
- [ ] **Step 2: Jalankan E2E, pastikan gagal.**
- [ ] **Step 3: Implementasi spec** — gunakan fixture yang ada; webhook lewat URL publik/tunnel dari env testing.
- [ ] **Step 4: Tulis panduan dashboard Xendit** — lihat bagian "Yang dilakukan pengguna di dashboard Xendit" di bawah.
- [ ] **Step 5: Jalankan E2E + `bun run build`, pastikan lolos.**
- [ ] **Step 6: Commit** — `git commit -m "test(payment): E2E Xendit dan panduan dashboard"`.

### Task 9: Gate akhir

- [ ] **Step 1:** `bun run check`
- [ ] **Step 2:** `bun run types:check`
- [ ] **Step 3:** `bun run test`
- [ ] **Step 4:** `composer test`
- [ ] **Step 5:** `bun run build`
- [ ] **Step 6:** `bun run test:e2e e2e/payment/xendit-payment.spec.ts --project=chromium`
- [ ] **Step 7:** Review perubahan dengan pembaca independen, lalu laporkan hasil.

## Yang dilakukan pengguna di dashboard Xendit (tanpa KYC, tanpa XenPlatform)

1. Daftar akun Xendit, verifikasi email, isi pertanyaan bisnis dasar → masuk **Test Mode**. Jangan ajukan verifikasi bisnis/KYC.
2. Settings → API Keys → **Generate Secret Key**. Beri permission: `Payment Write`, `Payment Read`, `Payout/Money-out Write`, `Balance/Transaction Read` (sesuai nama yang tersedia di dashboard).
3. Settings → Webhooks → daftarkan URL:
    - Payment Session / Payment event → `https://<host>/payments/xendit/webhook`
    - Payout event → `https://<host>/payments/xendit/webhook` (endpoint yang sama)
    - Salin **Callback Verification Token** (`x-callback-token`).
4. Untuk lokal: jalankan tunnel (mis. ngrok) ke `http://localhost:8000` dan pakai URL-nya sebagai callback URL testing. Docs Xendit menyebut ngrok untuk keperluan ini.
5. Balance → **Top Up** di Test Mode bila saldo kosong; dana simulasi, tidak nyata, tidak bisa ditarik. Bila tidak ada menu top-up, email CS untuk minta dummy balance.
6. Isi `.env` lokal (nilai tidak pernah dimasukkan ke repo): `XENDIT_SECRET_KEY`, `XENDIT_CALLBACK_TOKEN`, `XENDIT_INSTANT_PAYOUT_ENABLED=false`. Semua sudah dibaca lewat `config/payment.php`.
7. Tidak perlu: XenPlatform, sub-account, split rule, KYC document upload, aktivasi channel, approved merchant.
