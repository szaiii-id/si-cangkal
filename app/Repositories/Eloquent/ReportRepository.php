<?php

namespace App\Repositories\Eloquent;

use App\Models\Booking;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReportRepository
{
    /**
     * @return array<string, mixed>
     */
    public function getRevenueData(string $startDate, string $endDate): array
    {
        $totalRevenue = Booking::whereIn('status', ['confirmed', 'completed'])
            ->whereBetween('created_at', [$startDate.' 00:00:00', $endDate.' 23:59:59'])
            ->sum('total_harga');

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
            'total_revenue' => $totalRevenue,
            'transactions' => $transactions,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getMitraPerformanceData(string $startDate, string $endDate): array
    {
        // ✅ Kinerja mitra sekarang dihitung dari Trip & Booking (bukan honor)
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
        $dateFormat = $isSqlite
            ? "strftime('%Y-%m', created_at)"
            : "TO_CHAR(created_at, 'YYYY-MM')";

        $data = Booking::selectRaw("$dateFormat as bulan, SUM(total_harga) as total")
            ->whereIn('status', ['confirmed', 'completed'])
            ->where('created_at', '>=', $sixMonthsAgo)
            ->groupBy('bulan')
            ->orderBy('bulan')
            ->pluck('total', 'bulan')
            ->toArray();

        $result = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $key = $date->format('Y-m');
            $result[] = [
                'bulan' => $date->translatedFormat('M'),
                'total' => (float) ($data[$key] ?? 0),
            ];
        }

        return $result;
    }
}