<?php

namespace App\Http\Resources;

use App\Models\TripDay;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Representasi satu hari itinerary dalam sebuah trip.
 *
 * @mixin TripDay
 */
final class TripDayResource extends JsonResource
{
    /**
     * Bentuk data hari trip yang dikirim ke frontend.
     *
     * Berbeda dengan PackageDayResource:
     * - Field jam_mulai, jam_selesai, lokasi diambil dari
     *   snapshot package (bukan dari PackageDay, yang bisa berubah
     *   setelah trip dibuat).
     * - Field status dan statusLabel disertakan karena setiap
     *   hari trip punya status siklus hidup (pending,
     *   in_progress, completed).
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'trip_id' => $this->trip_id,
            'hari_ke' => $this->hari_ke,
            'tanggal' => $this->tanggal->format('Y-m-d'),
            'judul' => $this->judul,
            'deskripsi' => $this->deskripsi,
            'jam_mulai' => $this->jamMulai(),
            'jam_selesai' => $this->jamSelesai(),
            'lokasi' => $this->lokasi(),
            'status' => $this->status,
            'statusLabel' => $this->statusLabel(),
            'isToday' => $this->isToday(),
            'isPast' => $this->isPast(),
            'isFuture' => $this->isFuture(),
        ];
    }
}
