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
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tipe' => $this->tipe,
            'nama_penyedia' => $this->nama_penyedia,
            'nomor_rekening' => $this->nomor_rekening,
            'nama_pemilik' => $this->nama_pemilik,
            'is_primary' => (bool) $this->is_primary,
        ];
    }
}
