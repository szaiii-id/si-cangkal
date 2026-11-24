<?php

namespace App\Providers;

use App\Events\BookingConfirmed;
use App\Listeners\CreateTripForBooking;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

/**
 * Registrasi event dan listener aplikasi.
 *
 * Meski Laravel 11+ mendukung auto-discovery, kita mendaftarkan mapping
 * secara eksplisit agar mapping mudah diaudit (siapa mendengarkan event
 * apa) dan tidak bergantung pada konvensi nama folder.
 *
 * Alur untuk BookingConfirmed:
 *
 *     1. CreateTripForBooking (sync) -> buat Trip + TripDay, lalu
 *                                      dispatch GenerateETicketJob.
 *
 * Notifikasi ke client sudah dikirim langsung oleh service terkait
 * (NotificationDispatcher -> in-app + queued FCM job), jadi tidak perlu
 * listener no-op yang hanya menulis log.
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
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
