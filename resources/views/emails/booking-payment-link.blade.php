<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Pembayaran {{ $booking->kode }}</title>
</head>
<body style="margin:0; padding:24px; font-family:ui-sans-serif,system-ui,sans-serif; color:#14213d; background:#f4f6f8;">
    <div style="max-width:560px; margin:0 auto; background:#fff; padding:24px; border-radius:16px;">
        <p style="font-size:12px; font-weight:800; letter-spacing:.08em; color:#4361ee; text-transform:uppercase; margin:0;">SI-CANGKAL</p>
        <h1 style="font-size:20px; margin:8px 0 16px;">Pembayaran booking {{ $booking->kode }}</h1>

        <p style="font-size:14px; line-height:1.6;">
            Halo {{ $booking->client?->name ?? 'Pelanggan' }},
        </p>
        <p style="font-size:14px; line-height:1.6;">
            Mitra telah menyiapkan pembayaran untuk paket <strong>{{ $booking->package?->nama ?? 'Paket Wisata' }}</strong>
            dengan total <strong>Rp {{ number_format((float) $booking->total_harga, 0, ',', '.') }}</strong>.
        </p>

        <p style="margin:22px 0;">
            <a href="{{ $url }}" style="display:inline-block; padding:13px 20px; border-radius:10px; background:#1d3557; color:#ffffff; text-decoration:none; font-weight:700; font-size:14px;">
                Lihat &amp; Bayar Sekarang
            </a>
        </p>

        <p style="font-size:13px; line-height:1.6; color:#4b5563;">
            Jika tombol tidak berfungsi, salin tautan ini ke browser:<br>
            <span style="word-break:break-all;">{{ $url }}</span>
        </p>

        <p style="font-size:13px; color:#4b5563;">
            Invoice terlampir pada email ini. Pembayaran online diverifikasi otomatis oleh sistem.
        </p>
    </div>
</body>
</html>
