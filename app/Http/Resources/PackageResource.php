<?php

namespace App\Http\Resources;

use App\Models\Package;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Representasi paket wisata.
 *
 * @mixin Package
 */
final class PackageResource extends JsonResource
{
    /**
     * Bentuk data paket yang dikirim ke frontend.
     *
     * Field database tetap snake_case (harga_per_orang,
     * durasi_hari, cover_image) sesuai kontrak frontend.
     * Field turunan memakai camelCase (isRombongan,
     * hargaPerOrangRupiah, days, facilities).
     *
     * Semua relasi memakai whenLoaded agar endpoint yang tidak
     * membutuhkan relasi tertentu (mis. daftar booking) tidak
     * memicu query N+1.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'mitra_id' => $this->mitra_id,
            'kode' => $this->kode,
            'nama' => $this->nama,
            'slug' => $this->slug,
            'deskripsi' => $this->deskripsi,
            'kategori' => $this->kategori->value,
            'durasi_hari' => $this->durasi_hari,
            'durasi_malam' => $this->durasi_malam,
            'harga_per_orang' => (string) $this->harga_per_orang,
            'harga_per_rombongan' => $this->harga_per_rombongan !== null
                ? (string) $this->harga_per_rombongan
                : null,
            'max_peserta' => $this->max_peserta,
            'isRombongan' => $this->isRombongan(),
            'timezone' => $this->timezone,
            'latitude' => $this->latitude !== null ? (string) $this->latitude : null,
            'longitude' => $this->longitude !== null ? (string) $this->longitude : null,
            'cover_image' => $this->cover_image,
            'status' => $this->status->value,
            'created_at' => $this->created_at->toIso8601String(),
            'mitra' => $this->whenLoaded('mitra', function (): ?array {
                /** @var User|null $mitra */
                $mitra = $this->mitra;

                if ($mitra === null) {
                    return null;
                }

                return [
                    'id' => $mitra->id,
                    'name' => $mitra->name,
                    'email' => $mitra->email,
                ];
            }),
            'days' => PackageDayResource::collection(
                $this->whenLoaded('days'),
            ),
            'facilities' => PackageFacilityResource::collection(
                $this->whenLoaded('facilities'),
            ),
        ];
    }
}
