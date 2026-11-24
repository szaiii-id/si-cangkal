<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * Buat keypair RSA untuk otentikasi B2B DOKU (Sub Account V2).
 *
 * Private key dipakai aplikasi untuk menandatangani request token.
 * Public key wajib di-upload ke dashboard DOKU (Integrations > API Keys).
 */
final class GenerateDokuKeyPair extends Command
{
    protected $signature = 'doku:generate-keypair {--force : Timpa keypair yang sudah ada}';

    protected $description = 'Buat keypair RSA untuk token B2B DOKU';

    public function handle(): int
    {
        $directory = storage_path('app/doku');
        $privatePath = $directory.'/private.pem';
        $publicPath = $directory.'/public.pem';

        if ((File::exists($privatePath) || File::exists($publicPath)) && ! $this->option('force')) {
            $this->error('Keypair sudah ada. Gunakan --force untuk menimpanya.');

            return self::FAILURE;
        }

        $resource = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        if ($resource === false) {
            throw new RuntimeException('Gagal membuat private key RSA.');
        }

        openssl_pkey_export($resource, $privateKey);
        $details = openssl_pkey_get_details($resource);

        if (! is_array($details) || ! isset($details['key'])) {
            throw new RuntimeException('Gagal mengekstrak public key RSA.');
        }

        File::ensureDirectoryExists($directory, 0700);
        File::put($privatePath, $privateKey);
        File::put($publicPath, $details['key']);
        chmod($privatePath, 0600);

        $this->info('Keypair DOKU berhasil dibuat:');
        $this->line('  Private: '.$privatePath);
        $this->line('  Public : '.$publicPath);
        $this->warn('Upload public.pem ke dashboard DOKU > Integrations > API Keys.');
        $this->warn('Set DOKU_PRIVATE_KEY_PATH di .env ke path private.pem.');

        return self::SUCCESS;
    }
}
