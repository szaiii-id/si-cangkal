<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>E-Ticket {{ $booking->kode }} - SI-CANGKAL</title>
    <style>
        @page {
            margin: 0;
            padding: 0;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #232323;
            margin: 0;
            padding: 40px;
            background-color: #ffffff;
        }
        /* Header */
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #f3f4f6;
            padding-bottom: 20px;
        }
        .header h1 {
            font-size: 32px;
            margin: 0;
            color: #232323;
            font-weight: 800;
            letter-spacing: -1px;
        }
        .header h1 span {
            color: #B81A6A; /* Pink Dark */
        }
        .header p {
            font-size: 12px;
            color: #6b7280;
            margin: 5px 0 0 0;
            text-transform: uppercase;
            letter-spacing: 2px;
            font-weight: bold;
        }

        /* Tiket Card */
        .ticket-card {
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            background-color: #ffffff;
            overflow: hidden;
            position: relative;
            margin-bottom: 20px;
        }
        
        /* Bagian Atas Tiket (Warna) */
        .ticket-header {
            background-color: #B81A6A;
            padding: 15px 25px;
            color: #ffffff;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .ticket-header .code {
            font-size: 20px;
            font-weight: 800;
            letter-spacing: 2px;
            font-family: monospace;
        }
        .ticket-header .status {
            background-color: rgba(255, 255, 255, 0.2);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
        }

        /* Garis Putus-putus Pemisah */
        .dashed-line {
            border-top: 2px dashed #e5e7eb;
            margin: 0;
        }

        /* Body Tiket */
        .ticket-body {
            padding: 25px;
        }

        /* Grid Info */
        .info-grid {
            width: 100%;
            margin-bottom: 20px;
        }
        .info-grid td {
            padding-bottom: 15px;
            vertical-align: top;
        }
        .label {
            font-size: 10px;
            color: #9ca3af;
            text-transform: uppercase;
            font-weight: 800;
            letter-spacing: 1px;
            margin-bottom: 4px;
            display: block;
        }
        .value {
            font-size: 14px;
            font-weight: bold;
            color: #111827;
        }

        /* QR Section */
        .qr-section {
            text-align: center;
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
        }
        .qr-section img {
            width: 180px;
            height: 180px;
            display: block;
            margin: 0 auto;
        }
        .qr-section p {
            font-size: 11px;
            color: #6b7280;
            margin: 10px 0 0 0;
            font-weight: bold;
        }

        /* Tabel Peserta */
        .participants-title {
            font-size: 14px;
            font-weight: 800;
            color: #111827;
            margin-bottom: 12px;
            border-bottom: 2px solid #f3f4f6;
            padding-bottom: 8px;
        }
        table.participants {
            width: 100%;
            font-size: 12px;
            border-collapse: collapse;
        }
        table.participants th {
            text-align: left;
            font-size: 10px;
            color: #6b7280;
            text-transform: uppercase;
            padding-bottom: 8px;
            border-bottom: 1px solid #e5e7eb;
            font-weight: bold;
        }
        table.participants td {
            padding: 10px 0;
            border-bottom: 1px solid #f3f4f6;
            color: #374151;
        }
        table.participants tr:last-child td {
            border-bottom: none;
        }

        /* Footer */
        .footer {
            text-align: center;
            margin-top: 40px;
            font-size: 10px;
            color: #9ca3af;
        }
        .footer strong {
            color: #111827;
        }
    </style>
</head>
<body>

    <!-- HEADER -->
    <div class="header">
        <h1>SI-CANGKAL<span>.</span></h1>
        <p>E-Ticket Perjalanan Wisata</p>
    </div>

    <!-- KARTU TIKET -->
    <div class="ticket-card">
        
        <!-- Bagian Atas (Pink) -->
        <div class="ticket-header">
            <div class="code">{{ $booking->kode }}</div>
            <div class="status">CONFIRMED</div>
        </div>

        <div class="dashed-line"></div>

        <!-- Bagian Isi -->
        <div class="ticket-body">
            
            <!-- Grid Informasi Utama -->
            <table class="info-grid">
                <tr>
                    <td style="width: 50%;">
                        <span class="label">Nama Paket</span>
                        <span class="value">{{ $trip->snapshotNama() }}</span>
                    </td>
                    <td style="width: 50%;">
                        <span class="label">Kode Trip</span>
                        <span class="value" style="font-family: monospace;">{{ $trip->kode }}</span>
                    </td>
                </tr>
                <tr>
                    <td>
                        <span class="label">Pemesan</span>
                        <span class="value">{{ $booking->client?->name ?? '-' }}</span>
                    </td>
                    <td>
                        <span class="label">Jumlah Peserta</span>
                        <span class="value">{{ $booking->jumlah_peserta }} Orang</span>
                    </td>
                </tr>
                <tr>
                    <td colspan="2">
                        <span class="label">Tanggal Keberangkatan</span>
                        <span class="value">
                            {{ $trip->tanggal_mulai->format('d F Y') }} s/d {{ $trip->tanggal_selesai->format('d F Y') }}
                        </span>
                    </td>
                </tr>
            </table>

            <!-- Area QR Code -->
            <div class="qr-section">
                <img src="data:image/png;base64,{{ base64_encode(file_get_contents($qrPath)) }}" alt="QR Code">
                <p>Tunjukkan QR Code ini kepada Mitra saat Check-in</p>
            </div>

            <!-- Daftar Peserta -->
            @if($booking->participants && $booking->participants->count())
                <div class="participants-title">Daftar Peserta</div>
                <table class="participants">
                    <thead>
                        <tr>
                            <th style="width: 10%;">No</th>
                            <th style="width: 40%;">Nama</th>
                            <th style="width: 20%;">Umur</th>
                            <th style="width: 30%;">No. HP</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($booking->participants as $i => $p)
                            <tr>
                                <td>{{ $i + 1 }}</td>
                                <td style="font-weight: bold; color: #111827;">{{ $p->nama }}</td>
                                <td>{{ $p->umur ?? '-' }}</td>
                                <td>{{ $p->no_hp ?? '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

        </div>
    </div>

    <!-- FOOTER -->
    <div class="footer">
        <p>Terima kasih telah merencanakan perjalanan Anda bersama <strong>SI-CANGKAL</strong>.</p>
        <p style="margin-top: 5px;">Dokumen ini digenerate secara otomatis. UUID: {{ $uuid }}</p>
    </div>

</body>
</html>