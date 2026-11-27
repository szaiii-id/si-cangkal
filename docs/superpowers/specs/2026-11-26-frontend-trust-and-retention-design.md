# Desain: Antarmuka client yang lebih jelas, ramah, dan bikin ingin kembali

Tanggal: 2026-11-26

## Intent

Pengguna client merasa alur pembayaran membingungkan (form unggah bukti dan rekening tampil sebelum metode dipilih), navigation bar bawah berwarna hitam polos, katalog tidak menampilkan rating, logo mitra tidak pernah dipakai, dan ikon panah dianggap mengganggu. Selain itu pengguna ingin merasa diajak kembali memakai aplikasi tanpa merasa dimanipulasi.

## Goals

1. Bottom bar client memakai warna brand dengan kontras yang memenuhi WCAG AA.
2. Kartu paket di katalog menampilkan bintang dan jumlah ulasan.
3. Kartu penyelenggara menampilkan logo mitra, dengan inisial sebagai cadangan.
4. Pembayaran online menjadi opsi utama; transfer tetap tersedia sebagai opsi kedua dan baru membuka rekening + form bukti setelah dipilih.
5. Tidak ada istilah teknis pada teks yang terlihat pengguna.
6. Panah dekoratif dihapus dari tombol aksi.
7. Retensi memakai sinyal yang sudah ada, bukan trik.

## Non-goals / anti-goals

- Tidak menambah email win-back, poin, streak, atau rekomendasi otomatis (itu pendekatan B, tidak dipilih).
- Tidak membuat urgensi palsu, menyembunyikan opsi, memakai teks rasa bersalah, atau pola manipulatif lain.
- Tidak mengubah alur backend pembayaran, webhook, atau status booking.
- Tidak mengubah warna solid admin/mitra secara menyeluruh.

## Constraints

- Svelte 5 runes, Inertia pages path-based, Tailwind v4 `@theme` di `resources/css/app.css`.
- Teks ramah: `text-<warna>-text` di atas putih/transparan; `bg-<warna>-dark` + teks putih untuk isian solid.
- Axe scan berjalan di E2E; perubahan warna wajib lolos kontras.
- Selektorn E2E dan unit test yang menyebut label lama harus ikut diperbarui.

## Approaches

- **A (dipilih):** Perbaikan inti + retensi jujur berbasis data yang sudah ada. Cocok dengan kebiasaan proyek (Laravel built-in, tanpa over-engineering), risiko rendah, tidak menambah infrastruktur.
- **B (tidak dipilih):** Mesin retensi lebih dalam — email otomatis, poin/streak, rekomendasi personal. Lebih banyak kerja backend dan berisiko terkesan memaksa.

Keputusan pengguna: transfer **tetap disediakan** sebagai opsi kedua; bayar online **lebih menonjol**; **tidak ada bahasa teknis** di tampilan.

## Detail implementasi

### 1. Bottom bar client

- `resources/js/components/layout/ClientLayout.svelte:182-221`: pill aktif memakai `bg-pink-dark text-white`, items nonaktif `text-gray-600` dengan hover `bg-pink/10`.
- Perbaiki peta warna di L24–30: kuncinya sekarang `Beranda`, `Katalog`, `Pesanan`, `Profil`, padahal label asli di `resources/js/utils/menus.ts:87-108` adalah `Dashboard`, `Katalog Paket`, `Booking Saya`, `Profile Saya` — sehingga fallback yang selalu terpakai. Samakan kuncinya.
- Pertahankan `aria-current`, fokus keyboard, dan label yang tersembunyi di mobile seperti sekarang.

### 2. Rating di katalog

- `app/Http/Controllers/Client/CatalogController.php:24-41`: kirim statistik rating per paket secara bulk (hindari N+1) ke halaman index.
- `app/Repositories/Eloquent/ReviewRepository.php:39-53`: pakai query statistik yang sudah ada sebagai dasar.
- `resources/js/pages/Client/Catalog/Index.svelte:160-224`: tambahkan `<StarRating value={...} readonly size="sm" />`, angka rata-rata, dan jumlah ulasan pada tiap kartu. Tanpa ulasan → tidak menampilkan bintang.
- Perbarui `resources/js/types/props/client.ts:138`.

### 3. Logo mitra

- `resources/js/pages/Client/Catalog/Show.svelte:286-288`: ganti lingkaran inisial dengan `<img>` memakai `logo_url` bila tersedia, inisial sebagai fallback bila kosong atau gagal dimuat.
- Pastikan `logo_url` ikut terkirim lewat resource paket; `CatalogController.php:57-62` sudah eager-load `mitra.profile`.
- `app/Models/Profile.php:61-68` tidak diubah.

### 4. Alur pembayaran

- `resources/js/components/booking/PaymentOptionsCard.svelte`: tambahkan state pilihan (`online`/`transfer`) dengan runes. Opsi online lebih besar, berborder/gradasi brand, label "Cepat"; opsi transfer lebih tenang dan berlabel "Pilih Transfer".
- Pilihan diangkat ke `Client/Bookings/Show.svelte` memakai prop bindable/callback (Svelte 5), sehingga blok "Konfirmasi Pembayaran", `UploadProofForm` (L329–347), dan "Rekening Tujuan Transfer" (L349+) hanya tampil setelah transfer dipilih — kecuali sudah ada bukti atau bukti ditolak, maka Transfer otomatis terpilih agar pengguna melihat statusnya.
- Opsi online hanya menampilkan satu tombol "Bayar Sekarang"; tidak menampilkan rekening maupun form bukti.

### 5. Bahasa tanpa teknis

Ganti pada teks yang terlihat pengguna (komentar kode boleh tetap teknis):

| Lama                     | Baru                                        |
| ------------------------ | ------------------------------------------- |
| Bayar dengan Xendit      | Bayar Online                                |
| QRIS · VA · E-WALLET     | Dari aplikasi bank atau dompet digital Anda |
| E-Ticket langsung dibuat | Tiket perjalanan langsung tersedia          |
| Transfer Manual          | Transfer ke Rekening Mitra                  |
| Lihat Rekening Mitra     | Pilih Transfer                              |
| Unggah bukti transfer    | Kirim foto bukti transfer                   |
| E-Ticket (label lain)    | Tiket Perjalanan                            |

Sapu juga `resources/js/pages/Client/**`, `resources/js/components/booking/**`, dan `resources/js/components/layout/**` untuk kata `Xendit`, `E-Ticket`, `QRIS`, `VA`, `E-WALLET`, `upload`, `gateway`, `webhook`, `token`, `URL`.

### 6. Panah

- Hapus SVG panah kanan dari tombol aksi di `Client/Catalog/Index.svelte:213-220`, `Client/Catalog/Show.svelte:336-343`, `Client/Dashboard.svelte:43-46`, `Client/Bookings/Index.svelte:280-299`, `Client/Notifications/Index.svelte:202-220`, `StatCard.svelte:53`, serta "Detail →"/"Lihat Semua →" di komponen dashboard admin/mitra.
- Panah pada pagination dan panah "Kembali" dipertahankan (menyatakan arah nyata, bukan dekorasi). Keputusan ini saya tetapkan.

### 7. Retensi jujur

- `Client/Dashboard.svelte`: bila ada booking `PENDING_PAYMENT`, tampilkan kartu ringan "Lanjutkan pesanan Anda" yang menuju detail booking. Tidak ada hitungan mundur palsu atau teks mendesak.
- Countdown pembayaran yang sudah ada, badge "BARU"/"Best Seller", dan notifikasi tetap dipakai apa adanya.

## Yang tersisa untuk implementasi

- Urutan pengerjaan dan penyesuaian test akan ditulis di rencana kerja setelah spek ini disetujui.
- Perlu verifikasi apakah `logo_url` sudah ikut di resource paket; bila belum, tambahkan di resource (bukan mengubah model).
- Selektorn E2E/unit yang memakai label lama perlu diperbarui bersamaan.
