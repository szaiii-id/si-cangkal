<?php

namespace App\Jobs;

use App\Models\MitraBankAccount;
use App\Services\Payment\Doku\DokuSubAccountService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Daftarkan sub-account DOKU untuk rekening utama mitra.
 *
 * Job sengaja idempoten: bila rekening sudah punya profileId, tidak ada
 * request lagi ke DOKU.
 */
final class RegisterDokuSubAccountJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly int $bankAccountId,
    ) {}

    public function handle(DokuSubAccountService $service): void
    {
        $account = MitraBankAccount::query()->find($this->bankAccountId);

        if ($account === null || $account->doku_profile_id !== null) {
            return;
        }

        // Lingkungan tanpa kredensial Sub Account (lokal/test) tidak boleh
        // menggagalkan onboarding mitra.
        if ((string) config('payment.doku.private_key_path') === '') {
            Log::warning('RegisterDokuSubAccountJob: dilewati, private key DOKU belum dikonfigurasi.', [
                'bank_account_id' => $this->bankAccountId,
            ]);

            return;
        }

        $service->register($account);
    }
}
