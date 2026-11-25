# Tautan Pembayaran Publik & Invoice — Desain

Tanggal: 2026-11-25
Status: **draft, menunggu persetujuan**

## Intent dan mengapa

Booking manual dibuat oleh mitra untuk pelanggan yang mungkin hanya bisa dihubungi lewat WhatsApp dan tidak punya akun aplikasi. Mitra perlu **satu tautan** yang bisa dibagikan, bisa dibuka tanpa login, berisi instruksi pembayaran dan invoice yang bisa diunduh. Email menjadi pengiriman pelengkap, bukan satu-satunya jalur.

Saat ini tombol mitra menyalin URL checkout Xendit langsung. Itu berfungsi, tapi tidak ada invoice, tidak ada branding, tidak bisa di-expire, dan `expires_at` yang dikembalikan masih mengambil `booking.expired_at` yang bernilai `null` untuk booking manual.

## Goals

- `expires_at` payment link diambil dari `expires_at` Payment Session Xendit, bukan dari `booking.expired_at`.
- Satu capability URL publik per booking yang bisa dibagikan lewat WhatsApp, dibuka tanpa login.
- Invoice PDF bisa diunduh dari halaman publik itu.
- Email otomatis ke `email_pelanggan` berisi tautan yang sama dan lampiran invoice.
- Tautan dicabut setelah pembayaran sukses atau booking dibatalkan.

## Non-goals

- Tidak mengubah alur Payment Session, webhook, payout, refund, atau reconciliation yang sudah berjalan.
- Tidak menambah XenPlatform, sub-account, atau public key kartu.
- Tidak membuat halaman Inertia/Svelte untuk publik — mengikuti pola E-Ticket yang memakai Blade ringan.
- Tidak menampilkan email, nomor HP, atau daftar peserta lengkap di halaman publik.
- Tidak menyimpan URL checkout Xendit mentah di log/audit.

## Anti-goals

- Memakai `booking.id` atau kode booking sebagai token — harus acak dan tidak bisa ditebak.
- Mengirim invoice PDF yang berisi PII lengkap ke halaman yang bisa diindeks.
- Menggantikan WA dengan email sebagai satu-satunya kanal (email bisa gagal/terlewat).
- Membuat link yang tetap aktif setelah pembayaran lunas.

## Constraints

- Laravel 13, DomPDF sudah dipakai (`Barryvdh\DomPDF\Facade\Pdf`), pola penyimpanan mengikuti `ETicketService`.
- Mail default `brevo` dan kredensial tersedia; email tetap harus dianggap best-effort dan dikirim lewat queue dengan `after_commit=true`.
- Halaman publik memakai Blade + header `X-Robots-Tag: noindex, nofollow, noarchive`, `Referrer-Policy: no-referrer`, `Cache-Control: no-store`, dan throttle seperti `e-tickets.verify`.
- Semua tanggal/"hari ini" memakai `App\Support\Timezone`, bukan `now()` langsung.
- User-facing copy dan komentar Bahasa Indonesia.
- Endpoint pembayaran internal tetap memakai `PaymentChargeService` (dedupe session pending).

## Approaches yang dipertimbangkan

1. **Satu URL aplikasi `/bayar/{token}` + invoice + email** — **dipilih**. Satu pesan WA, bisa di-expire, bisa dicabut, PII disamarkan, ada throttle. Perlu tabel token, halaman publik, PDF, dan Mailable.
2. **URL checkout Xendit + file invoice dikirim terpisah** — lebih cepat, tapi dua lampiran, tanpa branding, sulit di-expire, dan tidak ada kontrol akses.
3. **Email saja** — tidak cocok karena pemesan bisa hanya punya WA dan email bisa tidak terbaca.

Pendekatan 1 dipilih karena paling profesional untuk kasus pemesan offline/WA dan memakai pola capability URL yang sudah terbukti di E-Ticket.

## Bagian "bagaimana" yang sudah diputuskan

### Token dan penyimpanan

- Kolom `bookings.sumber` (`aplikasi`/`manual`) untuk menandai asal booking; dipakai sebagai syarat pengiriman email otomatis.
- Tabel baru `booking_payment_links`: `id`, `booking_id` (FK, cascade), `token` (uuid, unique), `expires_at` (nullable), `revoked_at` (nullable), timestamps.
- Token dibuat dengan `Str::uuid()`, bukan dari ID/kode booking.
- Saat link dibuat:
    - bila masih ada payment Xendit `pending` dan token aktif untuk booking itu → **pakai ulang token** yang ada;
    - bila tidak ada → cabut token lama dan buat token baru.
- `expires_at` token diambil dari `data_get($payment->gateway_payload, 'expires_at')`; fallback ke `booking.expired_at` bila tidak ada.
- `Mitra\BookingController::paymentLink()` mengembalikan `url` = `route('payments.public', $token)`, `expires_at` dari sumber di atas, dan `checkout_url` tetap dikirim via notifikasi in-app seperti sekarang.
- Perbaikan `expires_at` juga berlaku untuk endpoint client yang mengembalikan URL checkout.

### Halaman publik

- Route `GET /bayar/{token}` → `PublicPaymentLinkController@show`, nama `payments.public`.
- Route `GET /bayar/{token}/invoice` → `PublicPaymentLinkController@invoice`, nama `payments.public.invoice`.
- Controller menolak 404 bila token tidak ada, sudah dicabut, atau kedaluwarsa.
- Data yang tampil: kode booking, nama paket (dari snapshot package bila ada), tanggal keberangkatan, jumlah peserta, total harga, status, dan nama client yang **disamarkan** (pola `maskName()` milik `PublicETicketController`).
- Tombol:
    - **Bayar dengan Xendit** → URL dari `payments.gateway_payload.payment_link_url`; bila payment tidak lagi `pending`, tombol disembunyikan dan ditampilkan status terkait.
    - **Unduh Invoice PDF** → route invoice.
- Header dan throttle mengikuti `e-tickets.verify` (`throttle:30,1` per IP atau limiter baru `payment-link-view`).
- View: `resources/views/payment-links/show.blade.php`, tanpa Inertia, ringan untuk HP.

### Invoice

- `App\Services\Invoice\InvoiceServiceInterface` + `InvoiceService`.
- `pdfForBooking(Booking $booking): string` mengembalikan output PDF dari view `pdf.invoice` dengan DomPDF, ukuran A4.
- Data invoice: kode booking, tanggal, mitra, client (nama + email), paket, tanggal keberangkatan, jumlah peserta, rincian harga, total, status pembayaran, dan instruksi transfer manual sebagai fallback.
- PDF **dibuat on-demand**, tidak disimpan, supaya tidak ada data basi. `attachData()` dipakai untuk email.
- Nama file: `Invoice-{$booking->kode}.pdf`.

### Email

- Mailable `App\Mail\BookingPaymentLinkMail` dengan view `resources/views/emails/booking-payment-link.blade.php`.
- Isi: sapaan, kode booking, ringkasan, tombol tautan publik, dan lampiran invoice PDF.
- Dikirim dengan `Mail::to($client->email)->queue(...)` setelah transaksi/commit, hanya bila booking manual dan `email_pelanggan` valid.
- Jika mail gagal, catat `Log::warning` dan jangan rollback pembuatan link/WA.
- Notifikasi in-app `payment_link_created` tetap dikirim seperti sekarang.

### Pencabutan token

- `BookingObserver` mencabut token aktif (`revoked_at = now()`) pada transisi status ke `PAYMENT_VERIFIED`, `CONFIRMED`, `IN_PROGRESS`, `COMPLETED`, atau `CANCELLED` — mencakup webhook, verifikasi manual, refund, pembatalan, dan expiry scheduler.
- `PaymentChargeService` merotasi token saat membuat session baru setelah session lama berakhir, dan mencabut token saat webhook `payment_session.expired`.
- Halaman publik mengecek `revoked_at` dan `expires_at` pada setiap request.

## Testing

- Pest: token unik & reuse saat payment masih pending; rotasi saat session lama kedaluwarsa; `expires_at` berasal dari payload Xendit; halaman publik menolak token dicabut/kedaluwarsa; PII tersamarkan; endpoint invoice menghasilkan PDF; email di-queue dengan attachment; token dicabut setelah pembayaran dan pembatalan.
- Vitest: tombol "Salin Tautan Pembayaran" di kartu mitra Memakai URL route baru.
- E2E: buka `/bayar/{token}` tanpa login, klik invoice, pastikan PDF terunduh.

## Keputusan teknis dari builder check

- **Identitas booking manual:** ditambahkan kolom `sumber` (`aplikasi`/`manual`) pada `bookings` lewat migrasi. `createManual()` menulis `manual`, jalur normal menulis `aplikasi`. Tidak memakai heuristic `expired_at === null`.
- **Lifecycle token:** berada di dalam `PaymentChargeService`, bukan di controller, sehingga semua jalur pembuatan session (client dan mitra) memakai token yang sama dan otomatis merotasi bila session lama kedaluwarsa. `payment_session.expired` mencabut token aktif.
- **Endpoint client tetap `Inertia::location()`** — tidak diubah menjadi JSON. `expires_at` hanya dikembalikan oleh endpoint mitra dan dipakai halaman publik.
- **Pencabutan token terpusat:** `BookingObserver` mencabut token aktif setiap `status` berubah ke `PAYMENT_VERIFIED`, `CONFIRMED`, `IN_PROGRESS`, `COMPLETED`, atau `CANCELLED`. Ini mencakup verified manual, refund, pembatalan, dan expiry scheduler tanpa menyentuh tiap service.
- **Email hanya saat token baru dibuat**, bukan pada reuse session pending. Tombol "Kirim Ulang Email" tidak ditambahkan sekarang (link bisa tetap disalin lewat WA).

## Yang diserahkan ke builder

- Detail layout visual halaman publik dan template email.
- Nama limiter (`payment-link-view` vs pakai `throttle:30,1` langsung).
