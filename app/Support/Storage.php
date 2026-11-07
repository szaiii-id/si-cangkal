<?php

namespace App\Support;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage as StorageFacade;

/**
 * Type-safe wrapper untuk Storage facade.
 *
 * Mengembalikan `FilesystemAdapter` (bukan interface `Filesystem`)
 * agar method seperti `download()`, `url()`, `temporaryUrl()`
 * tersedia di Intelephense / PHPStan.
 *
 * Pemakaian:
 *
 *     use App\Support\Storage;
 *
 *     $disk = Storage::disk(BookingPaymentService::PROOF_DISK);
 *     return $disk->download($path, $filename);
 *
 * @see \Illuminate\Filesystem\FilesystemAdapter
 */
final class Storage
{
    /**
     * Ambil disk dengan tipe konkret `FilesystemAdapter`.
     */
    public static function disk(string $name): FilesystemAdapter
    {
        /** @var FilesystemAdapter $disk */
        $disk = StorageFacade::disk($name);

        return $disk;
    }
}