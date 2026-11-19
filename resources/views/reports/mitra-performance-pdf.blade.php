<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Kinerja Mitra - SI-CANGKAL</title>
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
        
        .report-period { 
            font-size: 11px; 
            color: #6b7280; 
            text-align: right; 
            margin: 0; 
        }
        .report-period strong { color: #111827; }
        
        /* SUMMARY CARDS */
        .summary-container { 
            width: 100%; 
            margin-bottom: 30px; 
            border-spacing: 15px 0; 
            border-collapse: separate; 
        }
        .summary-card { 
            background-color: #f9fafb; 
            border: 1px solid #e5e7eb; 
            border-radius: 12px; 
            padding: 20px; 
            text-align: center; 
            width: 50%; 
        }
        .summary-label { 
            font-size: 10px; 
            text-transform: uppercase; 
            letter-spacing: 1px; 
            color: #6b7280; 
            margin: 0 0 8px 0; 
            font-weight: bold;
        }
        .summary-value { 
            font-size: 20px; 
            font-weight: 900; 
            color: #111827; 
            margin: 0; 
        }
        
        /* TABLE */
        .table-title { 
            font-size: 14px; 
            font-weight: 800; 
            color: #111827; 
            margin-bottom: 15px; 
        }
        
        .data-table { 
            width: 100%; 
            border-collapse: collapse; 
            border: 1px solid #e5e7eb; 
            border-radius: 8px;
            overflow: hidden;
        }
        .data-table th, .data-table td { 
            padding: 12px 15px; 
            border-bottom: 1px solid #e5e7eb; 
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
        .data-table tr:last-child td {
            border-bottom: none;
        }
        .data-table tr:nth-child(even) { 
            background-color: #f9fafb; 
        }
        
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        
        .revenue-text {
            font-weight: 800;
            color: #059669; /* Green Text */
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
                    <p class="report-title">Laporan Kinerja Mitra</p>
                </td>
                <td style="text-align: right;">
                    <p class="report-period">Periode Laporan:</p>
                    <p class="report-period">
                        <strong>
                            {{ \Carbon\Carbon::parse($startDate)->translatedFormat('d M Y') }} - 
                            {{ \Carbon\Carbon::parse($endDate)->translatedFormat('d M Y') }}
                        </strong>
                    </p>
                </td>
            </tr>
        </table>
    </div>

    <!-- SUMMARY -->
    <table class="summary-container">
        <tr>
            <td class="summary-card">
                <p class="summary-label">Total Mitra Aktif</p>
                <p class="summary-value">{{ $data['total_mitra'] }} Mitra</p>
            </td>
            <td class="summary-card">
                <p class="summary-label">Total Trip Diselesaikan</p>
                <p class="summary-value">{{ $data['total_trip_completed'] }} Trip</p>
            </td>
        </tr>
    </table>

    <!-- TABEL DATA -->
    <p class="table-title">Peringkat Kinerja Mitra</p>
    <table class="data-table">
        <thead>
            <tr>
                <th class="text-center" style="width: 5%;">No</th>
                <th style="width: 35%;">Nama Mitra</th>
                <th style="width: 30%;">Email</th>
                <th class="text-center" style="width: 10%;">Trip Selesai</th>
                <th class="text-right" style="width: 20%;">Pendapatan</th>
            </tr>
        </thead>
        <tbody>
            @forelse($data['mitras'] as $index => $m)
            <tr>
                <td class="text-center" style="color: #6b7280;">{{ $index + 1 }}</td>
                <td style="font-weight: bold; color: #111827;">{{ $m['name'] }}</td>
                <td style="color: #6b7280;">{{ $m['email'] }}</td>
                <td class="text-center" style="font-weight: bold;">{{ $m['total_trip'] }}</td>
                <td class="text-right revenue-text">
                    Rp {{ number_format($m['total_revenue'], 0, ',', '.') }}
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="5" class="text-center" style="color: #9ca3af; padding: 30px;">
                    Tidak ada data mitra pada periode ini.
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <!-- FOOTER -->
    <div class="footer">
        <p>Dicetak pada: {{ now()->translatedFormat('d F Y, H:i') }} WITA</p>
        <p style="margin-top: 5px;">Laporan ini dibuat secara otomatis oleh Sistem SI-CANGKAL.</p>
    </div>

</body>
</html>