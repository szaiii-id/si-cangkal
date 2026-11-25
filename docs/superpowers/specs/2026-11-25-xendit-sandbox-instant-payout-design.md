# Migrasi Penuh DOKU → Xendit — Desain

Tanggal: 2026-11-25
Status: **draft, menunggu persetujuan**

## Intent dan mengapa

DOKU akan **dihapus total** dan digantikan Xendit. XenPlatform tidak dipakai karena mensyaratkan aktivasi/KYC; dokumentasi "Testing xenPlatform features" juga menyebut sub-account tidak bisa diaktivasi/diverifikasi di Test Mode.

Yang dipakai adalah fitur Xendit yang **tersedia di Test Mode tanpa KYC, tanpa XenPlatform, tanpa aktivasi channel**: Payment Session untuk menerima pembayaran, webhook untuk konfirmasi, dan Payout/Disbursement untuk mengirim dana ke rekening mitra yang sudah tersimpan di `mitra_bank_accounts`.

Perilaku bisnis yang sudah ada **dipertahankan**: dana ditahan sampai trip dinyatakan selesai oleh mitra, baru dicairkan. Yang berubah hanya gateway-nya. Opsi payout instan tetap tersedia lewat flag bila dibutuhkan untuk demo.

## Goals

- Alur sandbox: onboarding mitra apa adanya → buat paket → client order → bayar via Xendit → webhook → payout ke rekening mitra → notifikasi mitra → UI menampilkan status "dana dikirim".
- Webhook terverifikasi (`x-callback-token`) dan idempoten.
- Payout idempoten (`idempotency-key` terikat kode booking) dan aman di-retry.
- Flag `XENDIT_INSTANT_PAYOUT_ENABLED` hanya mengatur timing payout; bukan penentu gateway.
- E2E nyata untuk payment + payout, bukan spec yang di-skip.

## Non-goals

- Mengubah onboarding mitra, form rekening, atau skema `mitra_bank_accounts`.
- Mengubah perilaku bisnis `hold → release setelah trip selesai` saat gateway diganti.
- Membangun XenPlatform, sub-account, split rule, atau RSA keypair.
- Menghapus transfer manual dan refund manual sebagai fallback.
- Menjanjikan dana sampai di rekening pada detik yang sama dengan pembayaran; settlement gateway tetap memerlukan waktu.

## Anti-goals

- Simulasi yang hanya menandai `released_at` di database tanpa memanggil API payout sungguhan — itu tidak "terlihat real".
- Webhook yang dipercaya tanpa verifikasi token.
- Payout tanpa idempotency key atau tanpa guard status booking/payment.

## Constraints

- Semua `env('XENDIT_*')` **hanya** dibaca di `config/payment.php`. Kode lain memakai `config('payment.xendit.*')`.
- Konfigurasi dan kode DOKU dihapus, termasuk blok yang melempar exception bila `DOKU_ENV !== sandbox`.
- `MitraBankAccount::nomor_rekening` memakai cast `encrypted`; nilai yang dibaca dari model sudah plaintext, jadi tidak ada dekripsi manual.
- Kolom `payments.gateway` bertipe string dengan cast enum PHP; menambah case `xendit` tidak memerlukan migrasi.
- Enum status/label harus dicerminkan di `resources/js/utils/enums` dan `resources/js/types`.
- E2E memakai `QUEUE_CONNECTION=sync`, host `localhost`, dan asset hasil `bun run build`.
- Di Xendit Test Mode, payout dengan payload valid berstatus `SUCCEEDED`; `INSUFFICIENT_BALANCE` adalah failure_code yang boleh di-retry, `INVALID_DESTINATION` tidak.
- Dana pembayaran baru tersedia di saldo setelah settlement. Karena itu payout **tidak dipanggil sinkron di handler webhook**, melainkan lewat job dengan retry/backoff.

## Batasan fitur: daftar putih dan daftar hitam

Supaya tidak ada fitur yang tidak bisa dipakai tanpa KYC/XenPlatform/aktivasi menyusup ke implementasi:

**Daftar putih (boleh dipakai, tersedia di Test Mode):**

- `POST /sessions`, `GET /sessions/{id}`, `POST /sessions/{id}/cancel` — tanpa header `for-user-id` dan tanpa `with-split-rule`; jangan mengisi `allowed_payment_channels` dengan channel yang butuh aktivasi.
- `POST /v3/payment_requests/{id}/simulate` — khusus test mode, untuk E2E.
- `POST /v3/refunds`, `GET /v3/refunds` — tanpa `for-user-id`.
- `POST /v3/payouts`, `GET /v3/payouts/{id}`, `POST /v3/payouts/{id}/cancel` — tanpa `for-user-id`, tanpa `split_rule_id`; butuh permission `MONEY-OUT` pada test secret key dan saldo Test yang cukup (top-up Test instan, dana tidak nyata).
- `GET /balance`, `GET /transactions`, `POST /reports` — rekonsiliasi opsional, tanpa `for-user-id`.
- Webhook masuk: `payment.capture`, `payment_session.completed`, `refund.succeeded/failed`, `v3_payout.succeeded/failed/reversed`; verifikasi `x-callback-token`.

**Daftar hitam (dilarang masuk kode):**

- Semua endpoint XenPlatform: `/v3/accounts`, `/v2/accounts`, `/split_rules`, `/transfers`, `/account_holders`, `/account_verification`, dan webhook account/account-holder/split.
- Header `for-user-id` dan `with-split-rule` di request mana pun.
- Endpoint yang mensyaratkan aktivasi khusus: Direct Debit Indonesia, Kredivo, kartu non-3DS (`skip_three_ds`), BI SNAP.
- API legacy `/v2/invoices` dan `/v2/payouts`.
- Onboarding sub-account ke gateway. `OnboardingController::store()` saat ini memanggil `RegisterDokuSubAccountJob` — panggilan ini **wajib dihapus**. Onboarding mitra tetap hanya menyimpan profil + rekening ke database lokal; tidak ada request ke Xendit saat mitra daftar. Komentar "onboarding XenPlatform" di `AuthController` juga diperbarui.

Endpoints lain yang secara teknis jalan di Test Mode (`/customers`, `/files`, bill payments, disputes) sengaja **tidak dipakai** karena tidak dibutuhkan alur booking.

## Approaches yang dipertimbangkan

1. **XenPlatform sandbox sungguhan** — buat sub-account test, kirim `POST /sessions` dengan header `for-user-id` + `with-split-rule`. Ditolak karena aktivasi/XenPlatform tidak tersedia untuk akun dinas dan sub-account test tidak bisa diverifikasi. Tidak dipilih.
2. **Xendit sebagai satu-satunya gateway** — Payment Session + `POST /v3/refunds` + Payout/Disbursement; DOKU dihapus. **Dipilih.**
3. **Stub DB-only** — cukup update `released_at`/`settled_at`. Dibuang karena tidak ada webhook payout sungguhan dan tidak memenuhi syarat "terlihat real".

Approach 2 dipilih karena seluruh fitur yang dibutuhkan tersedia di Test Mode tanpa KYC/XenPlatform/aktivasi, dan logika bisnis yang sudah ada bisa dipertahankan hampir satu lawan satu.

## Bagian "bagaimana" yang sudah diputuskan

### Konfigurasi dan enum

- `config/payment.php` hanya berisi blok `xendit` (semua `DOKU_*` dihapus):
  - `base_url` = `https://api.xendit.co`
  - `secret_key` = `env('XENDIT_SECRET_KEY')`
  - `callback_token` = `env('XENDIT_CALLBACK_TOKEN')`
  - `payment_api_version` = `2024-11-11`
  - `payout_api_version` = `2025-09-01`
  - `instant_payout_enabled` = `env('XENDIT_INSTANT_PAYOUT_ENABLED', false)`
  - `platform_fee_percent` = `0` (komisi, bila ada, dihitung di aplikasi karena split rule butuh XenPlatform)
- `PaymentGateway::DOKU` diganti `PaymentGateway::XENDIT`; `PaymentMetode::DOKU` diganti `PaymentMetode::XENDIT`. Mirror frontend diperbarui.

### Adapter Xendit (menggantikan kontrak DOKU)

Kontrak dan DTO DOKU dihapus. Dibuat kontrak baru:

`App\Services\Payment\Contracts\XenditPaymentServiceInterface` dengan:

- `createSession(Booking $booking, int $sequence): XenditSessionResult` → `POST /sessions`
- `verifyWebhook(string $rawBody, ?string $callbackToken, string $event): XenditWebhookEvent` → `hash_equals`
- `refund(Payment $payment, Refund $refund): XenditRefundResult` → `POST /v3/refunds` dengan `payment_id` dan header `api-version: 2024-11-11`
- `disburse(Booking $booking, Payment $payment): XenditPayoutResult` → endpoint final diputuskan oleh spike (lihat bawah)
- `getPayout(string $payoutId): XenditPayoutResult` → rekonsiliasi

Implementasi `XenditGatewayService` memakai HTTP client Laravel, basic auth secret key, dan tidak pernah membaca `env()` langsung.

Bind interface di `AppServiceProvider`.

### Checkout

- `PaymentChargeService` tetap satu-satunya entry point. Ia memilih adapter berdasarkan konfigurasi gateway aktif.
- `Payment` yang dibuat untuk Xendit menyimpan:
  - `gateway = xendit`, `metode = xendit`
  - `gateway_order_id = booking.kode`
  - `gateway_request_id = payment_session_id`
  - `gateway_payload` = `payment_link_url`, `payment_session_id`, `payment_request_id`, lalu `payment_id` dari webhook
- `PaymentOptionsCard.svelte` menampilkan label gateway aktif ("Bayar dengan Xendit"), badge QRIS/VA/E-Wallet, dan kalimat "Dana diteruskan ke rekening mitra setelah pembayaran berhasil" — sengaja meniru bahasa produksi.

### Webhook

- Route publik baru `POST /webhooks/xendit`, mengikuti pola endpoint DOKU (throttle, tanpa CSRF, balas cepat).
- `XenditWebhookController`: verifikasi `x-callback-token` dengan `hash_equals`, simpan `WebhookEvent` provider `xendit` (idempotensi), balas 200, lalu dispatch `ProcessXenditNotificationJob`.
- Event yang ditangani: `payment_session.completed`, `payment.capture`, `refund.succeeded`, `refund.failed`, `v3_payout.succeeded`, `v3_payout.failed`, `v3_payout.reversed`.
- `XenditNotificationProcessor`: di dalam transaksi, lock baris `Booking` dan `Payment`, cocokkan `reference_id` dengan `booking.kode` dan amount dengan `payments.jumlah`, lalu ubah payment jadi `SUCCESS` + `paid_at` dan booking jadi `CONFIRMED`. Audit dan `BookingConfirmed::dispatch` dilakukan **setelah commit**.

### Payout instan

- `DisburseToMitraJob` di-dispatch setelah commit dari processor **hanya bila** flag `instant_payout_enabled` aktif.
- Idempotency key = `payout-{$booking->kode}`; `payout_id` disimpan ke `payments.gateway_payload`. Job menolak booking yang sudah punya `payout_id`.
- Rekening diambil dari `mitra->bankAccounts()->where('is_primary', true)`; `nama_pemilik` dan `nomor_rekening` dipakai apa adanya, `nama_penyedia` dipetakan eksplisit ke channel code bank (mis. BCA → `ID_BCA`). Bank yang tidak dikenal membuat job gagal dengan pesan jelas, bukan mengirim payload setengah jadi.
- Jumlah payout = 100% dari `payments.jumlah` karena `platform_fee_percent = 0`. Bila komisi dinas dipungut, nilainya dihitung di aplikasi dan dipotong sebelum payout, lalu dicatat di ledger. Tidak memakai split rule XenPlatform.
- Retry: `INSUFFICIENT_BALANCE` → backoff beberapa kali; `INVALID_DESTINATION`, `ACCOUNT_NAME_MISMATCH`, `TRANSFER_ERROR` → gagal permanen, notifikasi admin.
- Webhook `v3_payout.succeeded` mengisi `released_at`, `settled_at`, `gateway_status = SETTLED`, lalu mengirim notifikasi mitra "Dana diterima". Webhook gagal hanya menulis audit + notifikasi admin, tidak mengubah status booking.

### Refund dan pembatalan (keputusan desain)

- Sebelum payout berstatus `SUCCEEDED`: `RefundApprovalService` memakai `POST /v3/refunds`.
- Sesudah payout `SUCCEEDED`: dana sudah ada di rekening mitra, sehingga refund gateway tidak bisa menariknya kembali. `RefundApprovalService` harus mengecek keberadaan `payout_id` lebih dulu dan mengarahkan ke jalur **refund manual mitra** yang sudah ada di `RefundInitiationService`.
- **Keputusan saya:** bila client sudah mengajukan refund berstatus `PENDING` sebelum payout selesai, refund itu **diselesaikan in-place** lewat method baru `completeManuallyAfterPayout()` di `RefundInitiationService`. Method ini membuka lock `Refund` + `Booking`, mengubah refund menjadi `SUCCEEDED`, menulis `Payment` negatif, membatalkan booking, dan mengirim notifikasi — tanpa memanggil `ensureNoActiveRefund()` karena refund itu memang milik booking yang sama. Mitra tidak perlu menolak lalu mengajukan ulang.
- Ini hanya menyesuaikan simulator; kebijakan produksi tidak diubah.

### Observability dan UI

- `AuditLogger` mencatat `payment_verified`, `payout_requested`, `payout_succeeded`, `payout_failed`.
- Timeline booking menampilkan langkah "Dana dikirim ke mitra" lengkap dengan waktu dari webhook payout, supaya alur terlihat nyata di demo.

## Keputusan lanjutan dari builder check

- **Event yang memfinalisasi pembayaran (keputusan saya):** `payment.capture` adalah sumber kebenaran karena membawa `payment_id`, `payment_request_id`, `reference_id`, `channel_code`, dan `status: SUCCEEDED`. `payment_session.completed` hanya dipakai sebagai sinyal awal/rekonsiliasi; bila keduanya tiba untuk satu pembayaran, yang kedua diabaikan lewat idempotensi. `WebhookEvent.request_id` memakai `payment_id` untuk event payment dan `payout_id` untuk event payout. `payment_id` disimpan ke `payments.gateway_payload` dan dipakai `POST /v3/refunds`.
- **Status payout (keputusan saya):** response `POST` payout selalu `ACCEPTED`, bukan terminal. Job menyimpan `payout_id`, `released_at` (waktu payout diterima Xendit), dan `gateway_status = PAYOUT_REQUESTED`. Hanya webhook `v3_payout.succeeded` yang mengisi `settled_at`, mengubah status jadi `SETTLED`, dan mengirim notifikasi "Dana diterima". `getPayout()` dipakai sebagai rekonsiliasi bila webhook tidak datang; notifikasi mitra dikirim tepat sekali lewat guard status.
- **Refund dengan pengajuan sudah PENDING (keputusan saya):** lihat bagian Refund — refund diselesaikan in-place, bukan ditolak dan dibuat ulang.

## Testing

- **Pest**: payload & verifikasi webhook, idempotensi processor, guard amount/reference, idempotensi & retry job, guard refund setelah payout.
- **Vitest**: label dan teks kartu pembayaran mengikuti gateway aktif.
- **E2E**: spec baru `e2e/payment/xendit-instant-payout.spec.ts` — `asClient` membuat booking, membuka checkout, simulasi pembayaran test mode, menunggu webhook, lalu memverifikasi `Payment` berstatus `SETTLED`, notifikasi mitra muncul, dan timeline menampilkan "Dana dikirim". Tidak ada `skip`.
- **Gate**: `bun run check`, `bun run types:check`, `bun run test`, `composer test`, `bun run build`, lalu E2E.

## Fakta sandbox tanpa KYC (audit dokumentasi 2026-11-25)

Sumber: halaman `create-account`, `your-dashboard`, `api-keys`, `payments-integration-setup`, `test-scenarios-payouts`, `testing-xenplatform-features`, dan Xendit Help Center artikel 13440335901849 / 14831808077337 / 4407678167449.

- Signup + verifikasi email + pertanyaan bisnis dasar sudah memberi akses langsung ke **Test Mode dengan dana virtual**. KYC/business verification hanya membuka Live Mode.
- Akun baru mulai dengan **nol secret key**; test secret key harus di-generate sendiri dengan permission `Write` untuk money-out dan `Read` untuk GET.
- "All payment channels are available by default in test mode." Pengecualian: sebagian flow dibatasi partner (KYC/regional), misalnya Direct Debit Indonesia butuh bantuan CS dan Kredivo UAT dilakukan di Live.
- Help Center secara eksplisit mencantumkan saat KYC masih tertunda: *"Generate API Key in Test mode dashboard for simulation purposes"*, *"Simulate payments using the Test API key"*, *"Simulate transactions (Money-In) via Invoice/Payment Link"*, *"Simulate Single Disbursement, Batch Disbursement, & Payout (Money-Out)"*, dan *"Download the Report of your testing transactions"*.
- Simulasi payout test mode: payload valid → `SUCCEEDED`; `account_number` tertentu memicu failure code tertentu; `INSUFFICIENT_BALANCE` bila nominal melebihi saldo. Top-up Test Mode langsung terisi, dana tidak nyata dan tidak bisa ditarik; bila saldo kosong bisa minta dummy balance ke CS.
- Endpoint test-only: `POST /v3/payment_requests/{id}/simulate` mengembalikan `PENDING` lalu hasil lewat webhook; `POST /v1/disputes/simulate` (maks 5 per 24 jam, butuh payment settled).
- Docs webhook Xendit menyebut ngrok secara eksplisit untuk menerima webhook saat development. Jadi E2E dengan webhook sungguhan memungkinkan tanpa endpoint suntikan.
- XenPlatform: master wajib mengaktifkan fitur; sub-account Test langsung berstatus `LIVE` tanpa verifikasi, tetapi login/aktivasi/verifikasi sub-account dan webhook "Account Updated" tidak tersedia di Test Mode. Sesuai keputusan pengguna, jalur ini tidak dipakai.

Konsekuensi: error 403 "source currency has not been activated" di halaman API payout berlaku untuk konteks Live; tidak bertentangan dengan kemampuan simulasi payout di Test Mode yang ditegaskan Help Center.

## Keputusan pengguna yang sudah ditetapkan

- Xendit **menggantikan DOKU secara penuh**; tidak ada kartu DOKU lagi di UI.
- Sumber webhook E2E memakai webhook sungguhan dari Xendit Test Mode dengan tunnel (ngrok disebut eksplisit di dokumentasi).

## Diserahkan ke builder

- Hasil **spike** terhadap sandbox: menentukan apakah payout domestic IDR memakai `POST /v3/payouts` (dengan field `recipient` lengkap) atau fallback `POST /disbursements` yang memakai `channel_code` seperti `ID_BCA`. Keputusan diambil dari curl nyata, bukan dari asumsi.
- Detail pemetaan `nama_penyedia` → channel code bank untuk bank yang didukung.
- Nilai `platform_fee_percent` produksi bila komisi dinas akhirnya dipungut di luar split rule.
- Penamaan final label gateway di UI.
- Strukturisasi DTO bila ternyata perlu disesuaikan dengan gaya DTO yang ada.
