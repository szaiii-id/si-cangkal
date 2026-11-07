<?php

namespace App\Http\Controllers\Mitra;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Package;
use App\Models\Trip;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class DashboardController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $mitraId = $request->user()->id;

        // 1. Statistik Paket
        $packageCounts = Package::where('mitra_id', $mitraId)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        // 2. Statistik Trip
        $tripCounts = Trip::where('mitra_id', $mitraId)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        // 3. Statistik Booking & Revenue
        $totalRevenue = (float) Booking::where('mitra_id', $mitraId)
            ->whereIn('status', ['confirmed', 'completed'])
            ->sum('total_harga');

        $bookingCounts = Booking::where('mitra_id', $mitraId)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        // 4. Booking Terbaru
        $recentBookings = Booking::where('mitra_id', $mitraId)
            ->with(['client:id,name', 'package:id,nama'])
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn ($b) => [
                'id' => $b->id,
                'kode' => $b->kode,
                'client' => $b->client->name ?? '-',
                'paket' => $b->package->nama ?? '-',
                'total' => (float) $b->total_harga,
                'status' => $b->status->value,
                'tanggal' => $b->created_at->format('d M Y'),
            ]);

        return Inertia::render('Mitra/Dashboard', [
            'stats' => [
                'packages' => [
                    'draft' => $packageCounts['draft'] ?? 0,
                    'aktif' => $packageCounts['aktif'] ?? 0,
                    'arsip' => $packageCounts['arsip'] ?? 0,
                    'total' => array_sum($packageCounts),
                ],
                'trips' => [
                    'scheduled' => $tripCounts['scheduled'] ?? 0,
                    'active' => $tripCounts['active'] ?? 0,
                    'completed' => $tripCounts['completed'] ?? 0,
                    'total' => array_sum($tripCounts),
                ],
                'bookings' => [
                    'total' => array_sum($bookingCounts),
                    'pending' => ($bookingCounts['pending_payment'] ?? 0) + ($bookingCounts['payment_verified'] ?? 0),
                    'confirmed' => $bookingCounts['confirmed'] ?? 0,
                ],
                'total_revenue' => $totalRevenue,
            ],
            'recent_bookings' => $recentBookings,
        ]);
    }
}