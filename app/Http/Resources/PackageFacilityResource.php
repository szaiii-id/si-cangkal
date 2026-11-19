<?php

namespace App\Http\Resources;

use App\Models\PackageFacility;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Representasi fasilitas include/exclude pada paket wisata.
 *
 * @mixin PackageFacility
 */
final class PackageFacilityResource extends JsonResource
{
    /**
     * Bentuk data fasilitas yang dikirim ke frontend.
     *
     * Field database tetap snake_case (`urutan`) sesuai kontrak
     * frontend. Field turunan memakai camelCase (`tipeLabel`)
     * sesuai pola resource lain.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'package_id' => $this->package_id,
            'tipe' => $this->tipe->value,
            'tipeLabel' => $this->tipeLabel(),
            'nama' => $this->nama,
            'urutan' => $this->urutan,
        ];
    }
}
