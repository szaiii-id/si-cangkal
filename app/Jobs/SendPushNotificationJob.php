<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\Notification\Contracts\FirebaseNotificationServiceInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Job: kirim push notification FCM di background.
 *
 * Dipisah dari `NotificationDispatcher` karena panggilan FCM adalah I/O
 * eksternal yang:
 * - Bisa lambat / timeout sehingga tidak boleh menahan request user.
 * - Bisa gagal sementara dan layak di-retry.
 * - Tidak boleh menggagalkan transaksi bisnis (booking/refund).
 *
 * Idempotensi: FCM tidak punya idempotency key. Retry dapat menghasilkan
 * push ganda; ini trade-off yang diterima demi notifikasi yang andal.
 *
 * Catatan: notifikasi in-app (tabel `app_notifications`) tetap dibuat
 * sinkron oleh `NotificationDispatcher`; job ini HANYA mengurus push.
 */
final class SendPushNotificationJob implements ShouldQueue
{
    use Queueable;

    /**
     * Jumlah percobaan maksimum.
     */
    public int $tries = 3;

    /**
     * Timeout per percobaan (detik).
     */
    public int $timeout = 30;

    /**
     * @param  array<string, string>  $data  Data FCM (semua value string).
     */
    public function __construct(
        public readonly string $userId,
        public readonly string $tipe,
        public readonly string $judul,
        public readonly string $pesan,
        public readonly array $data = [],
    ) {}

    /**
     * Backoff antar percobaan (detik).
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 30];
    }

    public function handle(FirebaseNotificationServiceInterface $firebase): void
    {
        // Baca ulang user: token bisa berubah/di-logout antara dispatch
        // dan eksekusi job.
        $user = User::query()->find($this->userId);

        if ($user === null) {
            Log::warning('SendPushNotificationJob: user tidak ditemukan, dilewati', [
                'user_id' => $this->userId,
                'tipe' => $this->tipe,
            ]);

            return;
        }

        $token = trim((string) $user->fcm_token);

        if ($token === '') {
            return;
        }

        try {
            $firebase->sendToDevice(
                deviceToken: $token,
                title: $this->judul,
                body: $this->pesan,
                data: array_merge($this->data, ['tipe' => $this->tipe]),
            );
        } catch (Throwable $e) {
            Log::warning('SendPushNotificationJob: gagal push FCM', [
                'user_id' => $this->userId,
                'tipe' => $this->tipe,
                'error' => $e->getMessage(),
            ]);

            // Lempar ulang agar queue melakukan retry sesuai backoff.
            throw $e;
        }
    }
}
