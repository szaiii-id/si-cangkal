<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Support\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Layanan verifikasi email (PRD-AUTH-002).
 *
 * Tanggung jawab:
 * - mengirim ulang notifikasi verifikasi (dengan penanganan kegagalan
 *   SMTP yang elegan, PRD §9);
 * - memvalidasi hash tautan lalu menandai email sebagai terverifikasi;
 * - menulis audit log untuk aksi sensitif (OWASP A09).
 *
 * Controller tetap tipis dan mendelegasikan seluruh logika ke sini.
 */
final class EmailVerificationService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
    ) {}

    /**
     * Kirim (ulang) email verifikasi.
     *
     * @return bool True jika notifikasi berhasil dikirim/dispatch.
     */
    public function send(User $user): bool
    {
        if ($user->hasVerifiedEmail()) {
            return false;
        }

        try {
            $user->sendEmailVerificationNotification();

            AuditLogger::log('auth.email_verification_sent', [
                'actor_id' => $user->id,
                'user_id' => $user->id,
            ]);

            return true;
        } catch (Throwable $e) {
            // PRD §9: kegagalan SMTP tidak boleh menjadi 500.
            // Pesan sengaja tidak menyertakan detail internal.
            Log::error('Gagal mengirim email verifikasi', [
                'user_id' => $user->id,
                'exception' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Verifikasi email user berdasarkan hash pada tautan bertanda tangan.
     *
     * @throws AuthorizationException Jika hash tidak cocok dengan email user.
     */
    public function verify(User $user, string $hash): void
    {
        if ($user->hasVerifiedEmail()) {
            return;
        }

        if (! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            throw new AuthorizationException('Tautan verifikasi tidak valid.');
        }

        $this->users->markEmailAsVerified($user);

        event(new Verified($user));

        AuditLogger::log('auth.email_verified', [
            'actor_id' => $user->id,
            'user_id' => $user->id,
            'ip' => request()->ip(),
        ]);
    }
}
