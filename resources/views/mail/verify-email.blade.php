@component('mail::message')
# Halo, {{ $name }}

Terima kasih telah mendaftar di **{{ $appName }}**.

Klik tombol di bawah ini untuk memverifikasi alamat email Anda. Setelah terverifikasi, Anda dapat mulai menjelajahi dan memesan paket wisata.

@component('mail::button', ['url' => $url, 'color' => 'primary'])
Verifikasi Email Sekarang
@endcomponent

Tautan verifikasi ini berlaku selama **{{ $expiresIn }} menit**. Jika tombol di atas tidak berfungsi, salin dan tempel tautan berikut ke browser Anda:

{{ $url }}

Jika Anda tidak merasa mendaftar di {{ $appName }}, abaikan email ini. Tidak ada akun yang akan aktif tanpa verifikasi dari Anda.

Salam,
Tim {{ $appName }}
@endcomponent
