<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Package;
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

        $totalRevenue = (float) Booking::whereIn('status', ['confirmed', 'completed'])->sum('total_harga');
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

        $revenueData = Booking::selectRaw("$dateFormat as bulan, SUM(total_harga) as total")
            ->whereIn('status', ['confirmed', 'completed'])
            ->where('created_at', '>=', $sixMonthsAgo)
            ->groupBy('bulan')
            ->orderBy('bulan')
            ->pluck('total', 'bulan')
            ->toArray();

        $monthlyRevenue = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $key = $date->format('Y-m');
            $monthlyRevenue[] = [
                'bulan' => $date->translatedFormat('M'),
                'total' => (float) ($revenueData[$key] ?? 0),
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
                'total_revenue' => $totalRevenue,
            ],
            'recent_bookings' => $recentBookings,
            'monthly_revenue' => $monthlyRevenue,
        ]);
    }
}