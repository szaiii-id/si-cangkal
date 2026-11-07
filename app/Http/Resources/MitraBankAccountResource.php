<?php

namespace App\Http\Resources;

use App\Models\MitraBankAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Representasi rekening bank/e-wallet mitra.
 *
 * @mixin MitraBankAccount
 */
final class MitraBankAccountResource extends JsonResource
{
    /**
     * Bentuk data rekening yang dikirim ke frontend.
     *
     * Format camelCase dipertahankan sesuai kontrak yang sudah
     * dikonsumsi frontend Svelte (lihat `BookingResource` sebelum refactor):
     * - `namaPenyedia`   <- `nama_penyedia`
     * - `nomorRekening`  <- `nomor_rekening`
     * - `namaPemilik`    <- `nama_pemilik`
     * - `isPrimary`      <- `is_primary`
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tipe' => $this->tipe,
            'namaPenyedia' => $this->nama_penyedia,
            'nomorRekening' => $this->nomor_rekening,
            'namaPemilik' => $this->nama_pemilik,
            'isPrimary' => (bool) $this->is_primary,
        ];
    }
}