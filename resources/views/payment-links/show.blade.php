<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <title>Pembayaran {{ $bookingKode }} — SI-CANGKAL</title>
    <style>
        body { margin: 0; font-family: ui-sans-serif, system-ui, -apple-system, sans-serif; background: #f4f6f8; color: #14213d; }
        .wrap { max-width: 540px; margin: 32px auto; padding: 0 16px; }
        .card { background: #fff; border-radius: 18px; padding: 24px; box-shadow: 0 6px 24px rgba(20,33,61,.08); }
        .brand { font-size: 12px; font-weight: 800; letter-spacing: .08em; color: #4361ee; text-transform: uppercase; }
        h1 { font-size: 20px; margin: 8px 0 20px; }
        dl { margin: 0; }
        dt { font-size: 11px; color: #6b7280; text-transform: uppercase; letter-spacing: .04em; margin-top: 14px; }
        dd { margin: 4px 0 0; font-size: 15px; font-weight: 600; }
        .total { font-size: 24px; color: #1d3557; }
        .status { display: inline-block; margin-top: 6px; padding: 4px 10px; border-radius: 999px; background: #eef2ff; color: #3730a3; font-size: 12px; font-weight: 700; }
        .actions { margin-top: 26px; display: grid; gap: 10px; }
        .btn { display: block; text-align: center; padding: 14px 16px; border-radius: 12px; font-weight: 700; text-decoration: none; font-size: 14px; }
        .btn-primary { background: #1d3557; color: #fff; }
        .btn-secondary { background: #fff; color: #1d3557; border: 1px solid #cbd5e1; }
        .note { margin-top: 18px; font-size: 12px; color: #6b7280; line-height: 1.6; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <div class="brand">SI-CANGKAL</div>
        <h1>Pembayaran {{ $bookingKode }}</h1>

        <dl>
            <dt>Paket</dt>
            <dd>{{ $namaPaket }}</dd>

            <dt>Tanggal keberangkatan</dt>
            <dd>{{ $tanggalKeberangkatan }}</dd>

            <dt>Jumlah peserta</dt>
            <dd>{{ $jumlahPeserta }} orang</dd>

            <dt>Pemesan</dt>
            <dd>{{ $namaClient }}</dd>

            <dt>Total pembayaran</dt>
            <dd class="total">Rp {{ number_format($totalHarga, 0, ',', '.') }}</dd>

            <dt>Status</dt>
            <dd><span class="status">{{ $statusBooking }}</span></dd>
        </dl>

        <div class="actions">
            @if($canPay && $checkoutUrl !== '')
                <a class="btn btn-primary" href="{{ $checkoutUrl }}">Bayar dengan Xendit</a>
            @endif
            <a class="btn btn-secondary" href="{{ route('payments.public.invoice', ['token' => $token]) }}">Unduh Invoice PDF</a>
        </div>

        <p class="note">
            Tautan ini berlaku sampai {{ $expiresAt }}. Jangan bagikan tautan ini kepada pihak lain.
            Pembayaran diproses oleh Xendit; bukti pembayaran otomatis diverifikasi oleh sistem.
        </p>
    </div>
</div>
</body>
</html>
