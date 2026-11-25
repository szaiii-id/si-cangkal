<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $booking->kode }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1f2937; }
        h1 { font-size: 20px; margin: 0 0 4px; color: #1d3557; }
        .muted { color: #6b7280; }
        .row { display: flex; justify-content: space-between; margin-top: 16px; }
        table { width: 100%; border-collapse: collapse; margin-top: 18px; }
        th, td { padding: 7px 6px; border-bottom: 1px solid #e5e7eb; text-align: left; }
        th { background: #f3f4f6; font-size: 10px; text-transform: uppercase; }
        .right { text-align: right; }
        .total { font-size: 15px; font-weight: bold; }
        .box { margin-top: 20px; padding: 12px; background: #f9fafb; border: 1px solid #e5e7eb; }
    </style>
</head>
<body>
    <h1>INVOICE PEMBAYARAN</h1>
    <div class="muted">SI-CANGKAL · {{ $booking->kode }}</div>

    <div class="row">
        <div>
            <strong>Ditagihkan kepada</strong><br>
            {{ $clientName }}
            @if($showEmail)
                <br>{{ $clientEmail }}
            @endif
        </div>
        <div class="right">
            <strong>Dari</strong><br>
            {{ $mitraName }}
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Keterangan</th>
                <th class="right">Jumlah</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    {{ $packageName }}<br>
                    <span class="muted">
                        Tanggal berangkat: {{ $booking->tanggal_keberangkatan?->translatedFormat('d F Y') ?? '-' }}
                        · {{ $booking->jumlah_peserta }} peserta
                    </span>
                </td>
                <td class="right">Rp {{ number_format((float) $booking->total_harga, 0, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>

    <div class="row">
        <div class="muted">Status booking: {{ $booking->status->label() }}</div>
        <div class="right total">Total Rp {{ number_format((float) $booking->total_harga, 0, ',', '.') }}</div>
    </div>

    {{-- Daftar peserta dan nomor HP sengaja tidak ditampilkan: invoice --}}
    {{-- ini dibagikan lewat tautan WhatsApp tanpa login. --}}

    <div class="box">
        <strong>Instruksi pembayaran</strong><br>
        Silakan bayar melalui tautan pembayaran yang dikirim mitra. Pembayaran online diverifikasi otomatis.
        Transfer manual tetap dapat digunakan dengan mengunggah bukti transfer pada halaman booking aplikasi.
    </div>
</body>
</html>
