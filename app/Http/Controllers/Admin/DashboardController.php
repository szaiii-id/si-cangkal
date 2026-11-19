<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Package;
use App\Models\Refund;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class DashboardController extends Controller
{
    public function index(): InertiaResponse
    {
        $totalAdmin = User::where('role', 'admin')->count();
        $totalMitra = User::where('role', 'mitra')->count();
        $totalClient = User::where('role', 'client')->count();

        $totalPackages = Package::count();
        $activePackages = Package::where('status', 'aktif')->count();

        $totalTrips = Trip::count();
        $activeTrips = Trip::where('status', 'active')->count();

        // ==========================================
        // PERHITUNGAN PENDAPATAN (GROSS - REFUND = NET)
        // ==========================================

        // 1. Pendapatan Kotor
        $grossRevenue = (float) Booking::whereIn('status', ['confirmed', 'completed'])->sum('total_harga');

        // 2. Total Refund Sukses
        $totalRefund = (float) Refund::where('status', 'SUCCEEDED')->sum('amount');

        // 3. Pendapatan Bersih (Net)
        $totalRevenue = $grossRevenue - $totalRefund;

        $totalBookings = Booking::count();

        $recentBookings = Booking::with(['client:id,name', 'package:id,nama', 'mitra:id,name'])
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn ($b) => [
                'id' => $b->id,
                'kode' => $b->kode,
                'client' => $b->client->name ?? '-',
                'mitra' => $b->mitra->name ?? '-',
                'paket' => $b->package->nama ?? '-',
                'total' => (float) $b->total_harga,
                'status' => $b->status->value,
                'tanggal' => $b->created_at->format('d M Y'),
            ]);

        $sixMonthsAgo = now()->subMonths(5)->startOfMonth();
        $isSqlite = DB::connection()->getDriverName() === 'sqlite';
        $dateFormat = $isSqlite ? "strftime('%Y-%m', created_at)" : "TO_CHAR(created_at, 'YYYY-MM')";

        // ==========================================
        // GRAFIK PENDAPATAN (GROSS - REFUND = NET)
        // ==========================================

        // 1. Ambil Gross per bulan
        $grossData = Booking::selectRaw("$dateFormat as bulan, SUM(total_harga) as total")
            ->whereIn('status', ['confirmed', 'completed'])
            ->where('created_at', '>=', $sixMonthsAgo)
            ->groupBy('bulan')
            ->pluck('total', 'bulan')
            ->toArray();

        // 2. Ambil Refund per bulan
        $refundData = Refund::selectRaw("$dateFormat as bulan, SUM(amount) as total")
            ->where('status', 'SUCCEEDED')
            ->where('created_at', '>=', $sixMonthsAgo)
            ->groupBy('bulan')
            ->pluck('total', 'bulan')
            ->toArray();

        // 3. Gabungkan (Gross - Refund)
        $monthlyRevenue = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $key = $date->format('Y-m');

            $gross = (float) ($grossData[$key] ?? 0);
            $refund = (float) ($refundData[$key] ?? 0);

            $monthlyRevenue[] = [
                'bulan' => $date->translatedFormat('M'),
                'total' => $gross - $refund, // <-- Nilai Bersih
            ];
        }

        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'users' => [
                    'admin' => $totalAdmin,
                    'mitra' => $totalMitra,
                    'client' => $totalClient,
                ],
                'packages' => [
                    'total' => $totalPackages,
                    'aktif' => $activePackages,
                ],
                'trips' => [
                    'total' => $totalTrips,
                    'aktif' => $activeTrips,
                ],
                'bookings' => [
                    'total' => $totalBookings,
                ],
                'total_revenue' => $totalRevenue, // <-- Nilai Bersih
            ],
            'recent_bookings' => $recentBookings,
            'monthly_revenue' => $monthlyRevenue,
        ]);
    }
}
