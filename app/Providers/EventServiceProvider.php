<?php

namespace App\Providers;

use App\Events\BookingConfirmed;
use App\Listeners\CreateTripForBooking;
use App\Listeners\GenerateETicketListener;
use App\Listeners\NotifyClientListener;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

/**
 * Registrasi event dan listener aplikasi.
 *
 * Meski Laravel 11+ mendukung auto-discovery, kita mendaftarkan mapping
 * secara eksplisit agar:
 * - Urutan listener dapat dijamin (Trip harus dibuat sebelum E-Ticket).
 * - Mapping mudah diaudit (siapa mendengarkan event apa).
 * - Perubahan listener tidak bergantung pada konvensi nama folder.
 *
 * Alur untuk BookingConfirmed (urut dari atas ke bawah):
 *
 *     1. CreateTripForBooking    (sync)   -> buat Trip + TripDay
 *     2. GenerateETicketListener (queue)  -> dispatch GenerateETicketJob
 *     3. NotifyClientListener    (queue)  -> email + audit DB
 *
 * Listener 2 & 3 berjalan paralel di queue masing-masing setelah
 * listener 1 selesai, karena dispatcher memproses listener sinkron
 * lebih dulu, baru mengantrekan yang async.
 */
final class EventServiceProvider extends ServiceProvider
{
    /**
     * Mapping event ke listener.
     *
     * @var array<class-string, list<class-string>>
     */
    protected $listen = [
        BookingConfirmed::class => [
            CreateTripForBooking::class,
            GenerateETicketListener::class,
            NotifyClientListener::class,
        ],
    ];

    /**
     * Daftarkan service apa pun untuk aplikasi.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap service aplikasi.
     */
    public function boot(): void
    {
        parent::boot();
    }

    /**
     * Tentukan apakah event dan listener harus ditemukan secara otomatis.
     *
     * Dimatikan karena kita mendaftarkan mapping eksplisit di atas.
     * Auto-discovery tetap berjalan untuk event/listener lain yang
     * mengikuti konvensi, tetapi tidak untuk BookingConfirmed (yang
     * didaftarkan manual). Untuk kesederhanaan dan prediktabilitas,
     * kita matikan sepenuhnya.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}