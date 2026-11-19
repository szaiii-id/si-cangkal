<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Daftar Peserta Perjalanan - {{ $trip->kode }}</title>
    <style>
        @page { margin: 40px; }
        body { 
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; 
            font-size: 11px; 
            color: #374151; 
            line-height: 1.5;
        }
        
        /* HEADER */
        .header { 
            border-bottom: 2px solid #B81A6A; /* Pink Dark */
            padding-bottom: 20px; 
            margin-bottom: 30px; 
        }
        .header-table { width: 100%; border: none; }
        .header-table td { border: none; padding: 0; vertical-align: bottom; }
        
        .company-name { 
            font-size: 28px; 
            font-weight: 900; 
            color: #111827; 
            margin: 0; 
            letter-spacing: -1px;
        }
        .company-name span { color: #B81A6A; }
        
        .report-title { 
            font-size: 14px; 
            font-weight: bold; 
            color: #6b7280; 
            margin: 5px 0 0 0; 
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .trip-code { 
            font-size: 14px; 
            font-family: monospace;
            font-weight: bold;
            color: #B81A6A; 
            text-align: right; 
            margin: 0; 
        }

        /* SECTION TITLE */
        .section-title { 
            font-size: 13px; 
            font-weight: 800; 
            color: #111827; 
            margin: 30px 0 15px 0; 
            padding-bottom: 8px;
            border-bottom: 2px solid #f3f4f6;
            display: flex;
            align-items: center;
        }
        .section-title::before {
            content: '';
            display: inline-block;
            width: 4px;
            height: 16px;
            background-color: #B81A6A;
            margin-right: 10px;
            border-radius: 2px;
        }

        /* INFO GRID */
        .info-table { 
            width: 100%; 
            margin-bottom: 20px; 
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            border-collapse: collapse;
        }
        .info-table td { 
            padding: 12px 15px; 
            border-bottom: 1px solid #e5e7eb;
            vertical-align: top; 
        }
        .info-table tr:last-child td {
            border-bottom: none;
        }
        .info-table td:first-child { 
            font-weight: bold; 
            color: #6b7280;
            width: 30%; 
            text-transform: uppercase;
            font-size: 10px;
            letter-spacing: 0.5px;
        }
        .info-table td:last-child {
            font-weight: bold;
            color: #111827;
        }

        /* TABLE DATA */
        .data-table { 
            width: 100%; 
            border-collapse: collapse; 
            border: 1px solid #e5e7eb; 
            border-radius: 8px;
            overflow: hidden;
        }
        .data-table th, .data-table td { 
            padding: 10px 12px; 
            border-bottom: 1px solid #e5e7eb; 
            border-right: 1px solid #e5e7eb;
        }
        .data-table th { 
            background-color: #f3f4f6; 
            color: #4b5563; 
            font-weight: bold; 
            font-size: 10px; 
            text-transform: uppercase; 
            letter-spacing: 1px;
            text-align: left; 
            border-bottom: 2px solid #e5e7eb;
        }
        .data-table tr:last-child td { border-bottom: none; }
        .data-table tr:nth-child(even) { background-color: #f9fafb; }

        /* KEGIATAN CARDS */
        .day-card {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            margin-bottom: 15px;
            overflow: hidden;
        }
        .day-header {
            background-color: #f3f4f6;
            padding: 10px 15px;
            font-weight: 800;
            color: #111827;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
        }
        .day-header span {
            font-weight: normal;
            color: #6b7280;
        }
        .day-body {
            padding: 15px;
        }
        .day-body p {
            margin: 0 0 8px 0;
        }
        .day-body p:last-child {
            margin-bottom: 0;
        }
        .day-label {
            font-weight: bold;
            color: #B81A6A;
            display: inline-block;
            width: 80px;
        }

        /* FOOTER */
        .footer { 
            margin-top: 40px; 
            text-align: center; 
            font-size: 10px; 
            color: #9ca3af; 
            border-top: 1px solid #e5e7eb; 
            padding-top: 20px; 
        }
    </style>
</head>
<body>

    <!-- HEADER -->
    <div class="header">
        <table class="header-table">
            <tr>
                <td>
                    <h1 class="company-name">SI-CANGKAL<span>.</span></h1>
                    <p class="report-title">Daftar Peserta & Rencana Perjalanan</p>
                </td>
                <td style="text-align: right;">
                    <p class="trip-code">{{ $trip->kode }}</p>
                </td>
            </tr>
        </table>
    </div>

    <!-- INFORMASI PERJALANAN -->
    <div class="section-title">Informasi Perjalanan</div>
    <table class="info-table">
        <tr>
            <td>Nama Paket</td>
            <td>{{ $trip->package->nama ?? 'Paket sudah tidak tersedia' }}</td>
        </tr>
        <tr>
            <td>Tanggal Pelaksanaan</td>
            <td>{{ $trip->tanggal_mulai->format('d F Y') }} - {{ $trip->tanggal_selesai->format('d F Y') }}</td>
        </tr>
        <tr>
            <td>Status Perjalanan</td>
            <td>
                <span style="background-color: #dbeafe; color: #1e40af; padding: 3px 8px; border-radius: 12px; font-size: 10px;">
                    {{ strtoupper($trip->status->value ?? $trip->status) }}
                </span>
            </td>
        </tr>
        <tr>
            <td>Nama Agen Travel</td>
            <td>{{ $trip->mitra->name ?? '-' }}</td>
        </tr>
        <tr>
            <td>Total Peserta</td>
            <td>{{ $trip->booking->participants->count() ?? 0 }} Orang</td>
        </tr>
    </table>

    <!-- DAFTAR PESERTA -->
    <div class="section-title">Daftar Peserta</div>
    <table class="data-table">
        <thead>
            <tr>
                <th class="text-center" style="width: 5%;">No</th>
                <th style="width: 30%;">Nama Lengkap</th>
                <th class="text-center" style="width: 10%;">Umur</th>
                <th style="width: 25%;">No. HP</th>
                <th style="width: 30%;">Catatan</th>
            </tr>
        </thead>
        <tbody>
            @forelse($trip->booking->participants as $index => $p)
                <tr>
                    <td class="text-center" style="color: #6b7280;">{{ $index + 1 }}</td>
                    <td style="font-weight: bold; color: #111827;">{{ $p->nama }}</td>
                    <td class="text-center">{{ $p->umur ?? '-' }}</td>
                    <td style="font-family: monospace;">{{ $p->no_hp ?? '-' }}</td>
                    <td style="color: #6b7280; font-size: 10px;">{{ $p->catatan ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="text-center" style="color: #9ca3af; padding: 20px;">
                        Belum ada peserta yang terdaftar.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- RENCANA PERJALANAN -->
    <div class="section-title">Rencana Perjalanan</div>
    
    @forelse($trip->days as $day)
        <div class="day-card">
            <div class="day-header">
                Hari ke-{{ $day->hari_ke }}
                <span>{{ $day->tanggal->format('d F Y') }}</span>
            </div>
            <div class="day-body">
                <p>
                    <span class="day-label">Kegiatan</span>
                    <strong style="color: #111827;">{{ $day->judul }}</strong>
                </p>
                @if($day->deskripsi)
                    <p>
                        <span class="day-label">Catatan</span>
                        <span style="color: #4b5563;">{{ $day->deskripsi }}</span>
                    </p>
                @endif
            </div>
        </div>
    @empty
        <p style="color: #6b7280; font-style: italic;">Rencana perjalanan belum tersedia.</p>
    @endforelse

    <!-- FOOTER -->
    <div class="footer">
        <p>Dicetak pada: {{ now()->translatedFormat('d F Y, H:i') }} WITA</p>
        <p style="margin-top: 5px;">Dokumen ini dicetak secara otomatis oleh Sistem SI-CANGKAL.</p>
    </div>

</body>
</html>