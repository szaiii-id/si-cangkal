<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * Notifikasi verifikasi email (PRD-AUTH-002 §7.2).
 *
 * Catatan implementasi:
 * - `ShouldQueue`: pengiriman tidak boleh memblokir response HTTP
 *   registrasi (PRD §6.4). Pada shared-hosting tanpa worker daemon,
 *   set `QUEUE_CONNECTION=sync` atau jalankan
 *   `php artisan queue:work --stop-when-empty` lewat cron.
 * - `$afterCommit`: aman bila notifikasi dikirim di dalam transaksi DB.
 * - Template markdown otomatis menghasilkan versi HTML dan plain-text
 *   (Laravel Mail Markdown), memenuhi syarat anti-spam PRD §6.4.
 */
final class VerifyEmailNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        // Pastikan notifikasi baru dikirim setelah transaksi DB commit.
        $this->afterCommit();
    }

    /**
     * Channel pengiriman.
     *
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Representasi email verifikasi.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $expiresIn = (int) config('auth.verification.expire', 60);

        /** @var User $notifiable */
        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes($expiresIn),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ],
        );

        return (new MailMessage)
            ->subject('Verifikasi Alamat Email Anda - '.config('app.name'))
            ->markdown('mail.verify-email', [
                'name' => $notifiable->name,
                'url' => $url,
                'expiresIn' => $expiresIn,
                'appName' => config('app.name'),
            ]);
    }
}
