<?php

namespace App\Http\Resources;

use App\Models\PackageDay;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Representasi satu hari itinerary dalam paket wisata.
 *
 * @mixin PackageDay
 */
final class PackageDayResource extends JsonResource
{
    /**
     * Bentuk data hari yang dikirim ke frontend.
     *
     * Field database tetap snake_case (`hari_ke`, `jam_mulai`,
     * `jam_selesai`) sesuai kontrak frontend Svelte saat ini.
     * Field turunan memakai camelCase (`jamMulaiShort`,
     * `jamSelesaiShort`) untuk kenyamanan display tanpa
     * memaksa frontend memotong string.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'package_id' => $this->package_id,
            'hari_ke' => $this->hari_ke,
            'judul' => $this->judul,
            'deskripsi' => $this->deskripsi,
            'jam_mulai' => $this->jam_mulai,
            'jam_selesai' => $this->jam_selesai,
            'jamMulaiShort' => $this->jamMulaiShort(),
            'jamSelesaiShort' => $this->jamSelesaiShort(),
            'lokasi' => $this->lokasi,
        ];
    }
}
