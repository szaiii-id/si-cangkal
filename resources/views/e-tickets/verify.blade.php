<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <title>Verifikasi E-Ticket {{ $tripKode }} | SI-CANGKAL</title>
    <style>
        :root {
            color-scheme: light;
            --ink: #111827;
            --muted: #4b5563;
            --line: #e5e7eb;
            --valid-bg: #ecfdf5;
            --valid-ink: #065f46;
            --valid-line: #a7f3d0;
            --invalid-bg: #fef2f2;
            --invalid-ink: #991b1b;
            --invalid-line: #fecaca;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            padding: 24px 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f3f4f6;
            color: var(--ink);
            font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif;
            line-height: 1.5;
        }

        .card {
            width: 100%;
            max-width: 420px;
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 24px;
            box-shadow: 0 12px 32px rgb(17 24 39 / 8%);
            overflow: hidden;
        }

        .card__header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--line);
        }

        .brand {
            margin: 0 0 4px;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: .12em;
            text-transform: uppercase;
            color: var(--muted);
        }

        h1 {
            margin: 0;
            font-size: 20px;
            line-height: 1.3;
        }

        .status {
            margin: 20px 24px 0;
            padding: 14px 16px;
            border: 1px solid;
            border-radius: 16px;
            font-size: 14px;
            font-weight: 700;
        }

        .status--valid {
            background: var(--valid-bg);
            border-color: var(--valid-line);
            color: var(--valid-ink);
        }

        .status--invalid {
            background: var(--invalid-bg);
            border-color: var(--invalid-line);
            color: var(--invalid-ink);
        }

        .status small {
            display: block;
            margin-top: 2px;
            font-size: 12px;
            font-weight: 500;
        }

        dl {
            margin: 0;
            padding: 20px 24px 24px;
        }

        .row {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            padding: 10px 0;
            border-bottom: 1px solid var(--line);
            font-size: 14px;
        }

        .row:last-child { border-bottom: 0; }

        dt {
            color: var(--muted);
            flex-shrink: 0;
        }

        dd {
            margin: 0;
            font-weight: 700;
            text-align: right;
            overflow-wrap: anywhere;
        }

        .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }

        .row a {
            color: #1d4ed8;
            text-decoration: none;
        }

        .footer {
            padding: 14px 24px 20px;
            border-top: 1px solid var(--line);
            font-size: 11px;
            color: var(--muted);
            text-align: center;
        }
    </style>
</head>
<body>
    <main class="card">
        <header class="card__header">
            <p class="brand">SI-CANGKAL · Verifikasi E-Ticket</p>
            <h1>{{ $namaPaket }}</h1>
        </header>

        @if ($isValid)
            <p class="status status--valid" role="status">
                E-Ticket Berlaku
                <small>Status booking: {{ $statusBooking }}</small>
            </p>
        @else
            <p class="status status--invalid" role="status">
                E-Ticket Tidak Berlaku
                <small>Status booking: {{ $statusBooking }} · Status trip: {{ $statusTrip }}</small>
            </p>
        @endif

        <dl>
            <div class="row">
                <dt>Kode Booking</dt>
                <dd class="mono">{{ $bookingKode }}</dd>
            </div>
            <div class="row">
                <dt>Kode Trip</dt>
                <dd class="mono">{{ $tripKode }}</dd>
            </div>
            <div class="row">
                <dt>Tanggal</dt>
                <dd>{{ $tanggalMulai }} &ndash; {{ $tanggalSelesai }}</dd>
            </div>
            <div class="row">
                <dt>Jumlah Peserta</dt>
                <dd>{{ $jumlahPeserta }} orang</dd>
            </div>
            <div class="row">
                <dt>Nama Client</dt>
                <dd>{{ $namaClient }}</dd>
            </div>
            <div class="row">
                <dt>Lokasi</dt>
                <dd>
                    <a
                        href="https://www.google.com/maps/search/?api=1&query={{ rawurlencode($namaPaket) }}"
                        target="_blank"
                        rel="noopener noreferrer"
                    >Buka di Maps</a>
                </dd>
            </div>
        </dl>

        <footer class="footer">
            Halaman ini hanya menampilkan data verifikasi. Jangan bagikan tautan ini.
        </footer>
    </main>
</body>
</html>
