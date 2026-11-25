<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Satu pintu untuk semua operasi file hasil upload.
 *
 * Helper ini sengaja tipis: ia tidak menentukan disk mana yang "benar".
 * Pemilihan disk tetap menjadi keputusan service (mis. bukti transfer
 * memakai disk privat, cover paket memakai disk publik). Dengan begitu
 * perpindahan ke R2 pada T11 cukup mengganti konstanta/nama disk di
 * service, tanpa menyentuh kode yang memakainya di mana pun.
 *
 * Semua method mengembalikan/menerima **path relatif** terhadap disk,
 * bukan URL penuh, agar kolom database tetap stabil.
 */
final class UploadStorage
{
    /**
     * Simpan file dan kembalikan path relatif pada disk yang diminta.
     *
     * @param  non-empty-string  $directory
     */
    public static function put(
        string $disk,
        string $directory,
        UploadedFile $file,
        ?string $filename = null,
    ): string {
        $path = Storage::disk($disk)->putFileAs(
            trim($directory, '/'),
            $file,
            $filename ?? self::generateFilename($file),
        );

        if ($path === false) {
            throw new RuntimeException('Gagal menyimpan berkas. Coba lagi.');
        }

        return $path;
    }

    /**
     * URL publik untuk file pada disk yang mendukung url().
     */
    public static function url(string $disk, string $path): string
    {
        return Storage::disk($disk)->url($path);
    }

    /**
     * URL sementara (signed) untuk file privat.
     */
    public static function signedUrl(string $disk, string $path, int $minutes = 5): string
    {
        return Storage::disk($disk)->temporaryUrl($path, now()->addMinutes($minutes));
    }

    public static function exists(string $disk, string $path): bool
    {
        return Storage::disk($disk)->exists($path);
    }

    public static function delete(string $disk, string $path): void
    {
        Storage::disk($disk)->delete($path);
    }

    /**
     * Hapus file hanya setelah transaksi database berhasil di-commit.
     *
     * Di luar transaksi, Laravel langsung menjalankan callback.
     */
    public static function deleteAfterCommit(string $disk, string $path): void
    {
        DB::afterCommit(function () use ($disk, $path): void {
            self::delete($disk, $path);
        });
    }

    /**
     * Baca file dari disk utama; bila tidak ada, coba disk lama.
     *
     * Dipakai saat migrasi ke R2: upload baru masuk disk utama, tetapi
     * bukti lama yang masih berada di disk lokal tetap bisa diunduh.
     */
    public static function readWithFallback(
        string $primaryDisk,
        string $legacyDisk,
        string $path,
    ): ?string {
        $primary = Storage::disk($primaryDisk);

        if ($primary->exists($path)) {
            $content = $primary->get($path);

            if ($content !== null) {
                return $content;
            }
        }

        $legacy = Storage::disk($legacyDisk);

        if (! $legacy->exists($path)) {
            return null;
        }

        return $legacy->get($path);
    }

    /**
     * Nama file acak yang aman untuk dipakai sebagai nama berkas.
     */
    private static function generateFilename(UploadedFile $file): string
    {
        $rawExtension = $file->guessExtension()
            ?: $file->getClientOriginalExtension()
            ?: 'bin';

        $extension = strtolower((string) preg_replace('/[^a-z0-9]/i', '', $rawExtension));

        if ($extension === '') {
            $extension = 'bin';
        }

        return Str::random(40).'.'.$extension;
    }
}
