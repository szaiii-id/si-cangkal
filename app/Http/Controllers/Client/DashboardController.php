<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Resources\PackageResource;
use App\Models\Booking;
use App\Models\Package;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class DashboardController extends Controller
{
    public function index(): InertiaResponse
    {
        // Ambil 4 paket terbaru yang aktif
        $paketBaru = Package::aktif()
            ->with('mitra:id,name')
            ->latest()
            ->take(4)
            ->get();

        // Ambil 4 paket terlaris yang aktif
        $paketLaris = Package::aktif()
            ->with('mitra:id,name')
            ->withCount(['bookings' => function ($query) {
                $query->whereIn('status', ['confirmed', 'in_progress', 'completed']);
            }])
            ->orderByDesc('bookings_count')
            ->take(4)
            ->get();

        // Pengingat jujur: pesanan yang masih menunggu pembayaran.
        $pesananTertunda = Booking::query()
            ->where('user_id', auth()->id())
            ->where('status', 'pending_payment')
            ->with('package:id,nama')
            ->latest()
            ->take(1)
            ->get()
            ->map(fn (Booking $booking): array => [
                'id' => $booking->id,
                'kode' => $booking->kode,
                'nama_paket' => data_get($booking, 'package.nama', 'Paket Wisata'),
                'total_harga' => (string) $booking->total_harga,
            ]);

        return Inertia::render('Client/Dashboard', [
            // Bungkus dengan Resource agar formatnya pasti konsisten
            'paket_baru' => PackageResource::collection($paketBaru)->resolve(),
            'paket_laris' => PackageResource::collection($paketLaris)->resolve(),
            'pesanan_tertunda' => $pesananTertunda,
        ]);
    }
}
