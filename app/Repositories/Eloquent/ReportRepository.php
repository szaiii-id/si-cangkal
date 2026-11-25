<?php

namespace App\Repositories\Eloquent;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReportRepository
{
    /**
     * @return array<string, mixed>
     */
    public function getRevenueData(string $startDate, string $endDate): array
    {
        $range = [$startDate.' 00:00:00', $endDate.' 23:59:59'];

        // Sumber kebenaran = ledger Payment. Booking yang sudah direfund
        // berstatus CANCELLED, jadi kalau gross masih dihitung dari booking
        // aktif, pendapatannya hilang tapi refund tetap dikurangi (double count).
        $grossRevenue = $this->grossFromLedger($range);
        $totalRefund = $this->refundFromLedger($range);

        // 3. Hitung Pendapatan Bersih (Net)
        $netRevenue = $grossRevenue - $totalRefund;

        $transactions = Booking::with(['client:id,name', 'package:id,nama', 'mitra:id,name'])
            ->whereIn('status', ['confirmed', 'completed'])
            ->whereBetween('created_at', [$startDate.' 00:00:00', $endDate.' 23:59:59'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($b) => [
                'kode' => $b->kode,
                'tanggal' => $b->created_at->format('Y-m-d'),
                'client' => $b->client->name ?? '-',
                'mitra' => $b->mitra->name ?? '-',
                'paket' => $b->package->nama ?? '-',
                'total' => $b->total_harga,
                'status' => $b->status->value,
            ]);

        return [
            'gross_revenue' => (float) $grossRevenue,
            'total_refund' => (float) $totalRefund,
            'total_revenue' => (float) $netRevenue,
            'transactions' => $transactions,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getMitraPerformanceData(string $startDate, string $endDate): array
    {
        $mitras = User::where('role', 'mitra')
            ->withCount(['trips as total_trip' => function ($q) use ($startDate, $endDate) {
                $q->where('status', 'completed')
                    ->whereBetween('tanggal_mulai', [$startDate, $endDate]);
            }])
            ->withSum(['mitraBookings as total_revenue' => function ($q) use ($startDate, $endDate) {
                $q->whereIn('status', ['confirmed', 'completed'])
                    ->whereBetween('created_at', [$startDate.' 00:00:00', $endDate.' 23:59:59']);
            }], 'total_harga')
            ->orderByDesc('total_revenue')
            ->get()
            ->map(fn ($m) => [
                'id' => $m->id,
                'name' => $m->name,
                'email' => $m->email,
                'total_trip' => $m->total_trip ?? 0,
                'total_revenue' => (float) ($m->total_revenue ?? 0),
            ]);

        return [
            'total_mitra' => $mitras->count(),
            'total_trip_completed' => $mitras->sum('total_trip'),
            'mitras' => $mitras,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getMonthlyRevenue(): array
    {
        $sixMonthsAgo = now()->subMonths(5)->startOfMonth();
        $isSqlite = DB::connection()->getDriverName() === 'sqlite';
        $dateFormat = $isSqlite ? "strftime('%Y-%m', created_at)" : "TO_CHAR(created_at, 'YYYY-MM')";

        // Ledger Payment per bulan (sumber kebenaran).
        $grossData = Payment::query()
            ->selectRaw("$dateFormat as bulan, SUM(jumlah) as total")
            ->where('jumlah', '>', 0)
            ->where('created_at', '>=', $sixMonthsAgo)
            ->groupBy('bulan')
            ->pluck('total', 'bulan')
            ->toArray();

        $refundData = Payment::query()
            ->selectRaw("$dateFormat as bulan, SUM(ABS(jumlah)) as total")
            ->where('jumlah', '<', 0)
            ->where('created_at', '>=', $sixMonthsAgo)
            ->groupBy('bulan')
            ->pluck('total', 'bulan')
            ->toArray();

        // Fallback data lama yang belum punya baris Payment.
        $legacyGross = Booking::selectRaw("$dateFormat as bulan, SUM(total_harga) as total")
            ->whereIn('status', ['confirmed', 'completed'])
            ->where('created_at', '>=', $sixMonthsAgo)
            ->groupBy('bulan')
            ->pluck('total', 'bulan')
            ->toArray();

        $legacyRefund = Refund::selectRaw("$dateFormat as bulan, SUM(amount) as total")
            ->where('status', 'SUCCEEDED')
            ->where('created_at', '>=', $sixMonthsAgo)
            ->groupBy('bulan')
            ->pluck('total', 'bulan')
            ->toArray();

        $result = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $key = $date->format('Y-m');

            $gross = (float) ($grossData[$key] ?? 0);

            if ($gross <= 0) {
                $gross = (float) ($legacyGross[$key] ?? 0);
            }

            $refund = (float) ($refundData[$key] ?? 0);

            if ($refund <= 0) {
                $refund = (float) ($legacyRefund[$key] ?? 0);
            }

            $result[] = [
                'bulan' => $date->translatedFormat('M'),
                'total' => $gross - $refund, // <-- Pendapatan Bersih
            ];
        }

        return $result;
    }

    /**
     * Kredit (pembayaran masuk) dari ledger. Data lama tanpa Payment
     * tetap dibaca dari booking aktif supaya laporan historis tidak kosong.
     *
     * @param  array{0: string, 1: string}  $range
     */
    private function grossFromLedger(array $range): float
    {
        $credits = (float) Payment::query()
            ->where('jumlah', '>', 0)
            ->whereBetween('created_at', $range)
            ->sum('jumlah');

        if ($credits > 0) {
            return $credits;
        }

        return (float) Booking::whereIn('status', ['confirmed', 'completed'])
            ->whereBetween('created_at', $range)
            ->sum('total_harga');
    }

    /**
     * Debit (refund keluar) dari ledger, dengan fallback ke Refund SUCCEEDED.
     *
     * @param  array{0: string, 1: string}  $range
     */
    private function refundFromLedger(array $range): float
    {
        $debits = (float) Payment::query()
            ->where('jumlah', '<', 0)
            ->whereBetween('created_at', $range)
            ->sum(DB::raw('ABS(jumlah)'));

        if ($debits > 0) {
            return $debits;
        }

        return (float) Refund::where('status', 'SUCCEEDED')
            ->whereBetween('created_at', $range)
            ->sum('amount');
    }
}
