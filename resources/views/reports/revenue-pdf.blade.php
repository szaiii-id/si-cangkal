<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Pendapatan - SI-CANGKAL</title>
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
        
        /* SUMMARY CARDS (Diubah menjadi 3 kolom) */
        .summary-container { 
            width: 100%; 
            margin-bottom: 30px; 
            border-spacing: 10px 0; 
            border-collapse: separate;
        }
        .summary-card { 
            background-color: #f9fafb; 
            border: 1px solid #e5e7eb; 
            border-radius: 12px; 
            padding: 15px 20px; 
            text-align: center; 
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
            font-size: 18px; 
            font-weight: 900; 
            margin: 0; 
        }
        
        .val-gross { color: #111827; }
        .val-refund { color: #dc2626; }
        .val-net { color: #059669; }
        
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
            padding: 10px 12px; 
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
        
        /* BADGE STATUS */
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .badge-success { background-color: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .badge-warning { background-color: #fef3c7; color: #92400e; border: 1px solid #fde68a; }
        .badge-info { background-color: #dbeafe; color: #1e40af; border: 1px solid #bfdbfe; }
        .badge-danger { background-color: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

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
                    <p class="report-title">Laporan Pendapatan</p>
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

    <!-- RINGKASAN (3 KOLOM) -->
    <table class="summary-container">
        <tr>
            <td class="summary-card" style="width: 33%;">
                <p class="summary-label">Pendapatan Kotor</p>
                <p class="summary-value val-gross">Rp {{ number_format($data['gross_revenue'] ?? 0, 0, ',', '.') }}</p>
            </td>
            <td class="summary-card" style="width: 33%;">
                <p class="summary-label">Total Refund</p>
                <p class="summary-value val-refund">- Rp {{ number_format($data['total_refund'] ?? 0, 0, ',', '.') }}</p>
            </td>
            <td class="summary-card" style="width: 33%; border-color: #059669;">
                <p class="summary-label" style="color: #059669;">Pendapatan Bersih</p>
                <p class="summary-value val-net">Rp {{ number_format($data['total_revenue'], 0, ',', '.') }}</p>
            </td>
        </tr>
    </table>

    <!-- TABEL TRANSAKSI -->
    <p class="table-title">Rincian Transaksi</p>
    <table class="data-table">
        <thead>
            <tr>
                <th>Kode</th>
                <th>Tanggal</th>
                <th>Client</th>
                <th>Mitra</th>
                <th>Paket</th>
                <th class="text-right">Total</th>
                <th class="text-center">Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($data['transactions'] as $t)
            <tr>
                <td style="font-family: monospace; font-weight: bold; color: #111827;">{{ $t['kode'] }}</td>
                <td style="color: #6b7280;">{{ \Carbon\Carbon::parse($t['tanggal'])->translatedFormat('d M Y') }}</td>
                <td style="font-weight: bold; color: #374151;">{{ $t['client'] }}</td>
                <td>{{ $t['mitra'] ?? '-' }}</td>
                <td>{{ $t['paket'] }}</td>
                <td class="text-right" style="font-weight: 800; color: #059669;">
                    Rp {{ number_format($t['total'], 0, ',', '.') }}
                </td>
                <td class="text-center">
                    @php
                        $status = strtolower($t['status']);
                        $badgeClass = 'badge-info';
                        if (in_array($status, ['confirmed', 'completed', 'paid'])) $badgeClass = 'badge-success';
                        elseif (in_array($status, ['pending', 'pending_payment'])) $badgeClass = 'badge-warning';
                        elseif (in_array($status, ['cancelled', 'failed'])) $badgeClass = 'badge-danger';
                    @endphp
                    <span class="badge {{ $badgeClass }}">{{ $t['status'] }}</span>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="7" class="text-center" style="color: #9ca3af; padding: 30px;">
                    Tidak ada transaksi pada periode ini.
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