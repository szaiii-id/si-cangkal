<?php

namespace App\Services\User;

use App\Models\Profile;
use App\Models\User;
use App\Repositories\Contracts\ProfileRepositoryInterface;
use App\Support\UploadStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ProfileService
{
    /**
     * Ekstensi file yang diizinkan untuk logo.
     *
     * SECURITY: SVG sengaja TIDAK dimasukkan karena SVG dapat
     * menyertakan <script> dan event handler → stored XSS.
     *
     * @var list<string>
     */
    private const ALLOWED_LOGO_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    /**
     * MIME type yang diizinkan untuk logo.
     *
     * @var list<string>
     */
    private const ALLOWED_LOGO_MIMES = [
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    public function __construct(
        protected ProfileRepositoryInterface $profileRepository,
    ) {}

    /**
     * Update profile user (termasuk upload logo jika ada).
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function updateProfile(User $user, array $data): Profile
    {
        if (! $user->profile) {
            throw ValidationException::withMessages([
                'profile' => ['Profile tidak ditemukan untuk user ini.'],
            ]);
        }

        // Handle upload logo
        if (isset($data['logo']) && $data['logo'] instanceof UploadedFile) {
            $data['logo'] = $this->uploadLogo($user, $data['logo']);
        } else {
            unset($data['logo']);
        }

        // Filter field yang boleh diupdate
        $allowedFields = [
            'nama_penyedia_usaha',
            'nomor_identitas',
            'alamat',
            'telepon',
            'provinsi',
            'kota',
            'kode_pos',
            'logo',
            'deskripsi',
        ];

        $filtered = array_intersect_key($data, array_flip($allowedFields));

        //  FIX PHPStan: `$user->profile` sudah di-narrow ke Profile
        // (bukan Model) karena return type method profile() = HasOne<Profile, $this>
        // dan sudah di-check `if (! $user->profile)` di atas.
        /** @var Profile $profile */
        $profile = $user->profile;

        return $this->profileRepository->updateProfile($profile, $filtered);
    }

    /**
     * Update rekening bank mitra.
     *
     * Menghapus semua rekening lama dan menyimpan yang baru.
     * Dipisahkan dari Controller agar sesuai pola Service-Repository (SOC).
     *
     * @param  array<int, array<string, mixed>>  $accounts
     */
    public function updateBankAccounts(User $mitra, array $accounts): void
    {
        DB::transaction(function () use ($mitra, $accounts): void {
            // Hapus semua rekening lama
            $mitra->bankAccounts()->delete();

            // Simpan rekening baru
            foreach ($accounts as $index => $account) {
                $mitra->bankAccounts()->create([
                    'tipe' => $account['tipe'],
                    'nama_penyedia' => $account['nama_penyedia'],
                    'nomor_rekening' => $account['nomor_rekening'],
                    'nama_pemilik' => $account['nama_pemilik'],
                    // Rekening pertama otomatis menjadi utama
                    'is_primary' => $index === 0,
                ]);
            }
        });

        // Audit log perubahan data finansial
        Log::info('Mitra memperbarui data rekening bank', [
            'user_id' => $mitra->id,
            'total_rekening' => count($accounts),
        ]);
    }

    /**
     * Upload logo mitra, return path relatif.
     *
     * SECURITY: Validasi dilakukan di 2 layer:
     *   1. FormRequest (HTTP layer) — validasi format & ukuran
     *   2. Method ini (business logic) — defense in depth, jika FormRequest di-bypass
     *
     * @throws ValidationException
     */
    public function uploadLogo(User $user, UploadedFile $file): string
    {
        // === Defense in depth layer 2: validasi ekstensi ===
        $extension = strtolower((string) $file->getClientOriginalExtension());

        if (! in_array($extension, self::ALLOWED_LOGO_EXTENSIONS, true)) {
            Log::warning('Logo upload ditolak: ekstensi tidak diizinkan', [
                'user_id' => $user->id,
                'extension' => $extension,
                'original' => $file->getClientOriginalName(),
            ]);

            throw ValidationException::withMessages([
                'logo' => ['Format logo tidak diizinkan.'],
            ]);
        }

        // === Defense in depth layer 3: validasi MIME asli ===
        $mimeType = (string) $file->getMimeType();

        if (! in_array($mimeType, self::ALLOWED_LOGO_MIMES, true)) {
            Log::warning('Logo upload ditolak: MIME type tidak diizinkan', [
                'user_id' => $user->id,
                'mime' => $mimeType,
                'original' => $file->getClientOriginalName(),
            ]);

            throw ValidationException::withMessages([
                'logo' => ['Tipe file logo tidak valid.'],
            ]);
        }

        // === Hapus logo lama jika ada ===
        //  FIX PHPStan: `$user->profile` di-narrow ke `Profile|null` via
        // docblock @var + null-safe operator `?->logo`.
        /** @var Profile|null $profile */
        $profile = $user->profile;
        $oldLogo = $profile?->logo;

        if ($oldLogo !== null && $oldLogo !== '') {
            UploadStorage::deleteAfterCommit(config('upload.public_disk'), $oldLogo);
        }

        // === Generate nama file dari server (jangan trust client) ===
        $filename = sprintf(
            '%d_%s.%s',
            $user->id,
            now()->format('YmdHis'),
            $extension
        );

        try {
            $path = UploadStorage::put(config('upload.public_disk'), 'logos', $file, $filename);
        } catch (RuntimeException) {
            Log::error('Logo mitra gagal disimpan', [
                'user_id' => $user->id,
                'filename' => $filename,
            ]);

            throw ValidationException::withMessages([
                'logo' => ['Gagal menyimpan logo. Coba lagi.'],
            ]);
        }

        // === Audit trail ===
        Log::info('Logo mitra berhasil diupload', [
            'user_id' => $user->id,
            'filename' => $filename,
            'size' => $file->getSize(),
            'mime' => $mimeType,
            'old_logo' => $oldLogo,
        ]);

        return $path;
    }
}
