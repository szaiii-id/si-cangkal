<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| SCHEDULER — SI-CANGKAL
|--------------------------------------------------------------------------
|
| Semua jadwal task otomatis:
| - Auto-expire booking pending       (setiap jam)
| - Auto-activate trip yang due       (setiap jam)
|
| Setup production:
|   * * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
|
*/

// ==========================================
// AUTO-EXPIRE BOOKING
// ==========================================
Schedule::command('bookings:expire')
    ->hourly()
    ->name('bookings:expire-hourly')
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();

// ==========================================
// AUTO-ACTIVATE TRIP
// ==========================================
Schedule::command('trips:activate')
    ->hourly()
    ->name('trips:activate-hourly')
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();

// ==========================================
// REKONSILIASI XENDIT
// ==========================================
// Webhook bisa terlambat/tidak sampai. Job ini mengecek status payout dan
// refund langsung ke Xendit tiap 5 menit.
Schedule::command('payment:reconcile-xendit')
    ->everyFiveMinutes()
    ->name('payment:reconcile-xendit')
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();
