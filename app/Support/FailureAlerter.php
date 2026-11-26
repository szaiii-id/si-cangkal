<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Kirim notifikasi saat queue job gagal permanen.
 *
 * Telegram bersifat opsional: bila kredensial tidak diisi, cukup audit log.
 * Tidak ada dependency baru — memakai HTTP client bawaan Laravel.
 */
final class FailureAlerter
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function alert(Throwable $exception, string $job, array $context = []): void
    {
        AuditLogger::system('queue.failed', array_merge([
            'job' => $job,
            'error' => $exception->getMessage(),
        ], $context));

        $token = config('services.telegram.bot_token');
        $chatId = config('services.telegram.chat_id');

        if (blank($token) || blank($chatId)) {
            return;
        }

        try {
            Http::timeout(5)
                ->post("https://api.telegram.org/bot{$token}/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => sprintf(
                        "Queue gagal: %s\n%s\n%s",
                        $job,
                        $exception->getMessage(),
                        now()->toIso8601String(),
                    ),
                ])
                ->throw();
        } catch (Throwable $e) {
            Log::warning('FailureAlerter: gagal mengirim notifikasi Telegram', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
