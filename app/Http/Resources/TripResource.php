<?php

namespace App\Http\Resources;

use App\Models\Booking;
use App\Models\Package;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Representasi trip (instance dari paket yang benar-benar jalan).
 *
 * @mixin Trip
 */
final class TripResource extends JsonResource
{
    /**
     * Bentuk data trip yang dikirim ke frontend.
     *
     * Catatan penting:
     * - Bila days di-eager-load, WAJIB disertai days.trip agar
     *   TripDayResource tidak memicu N+1 saat memanggil
     *   jamMulai(), jamSelesai(), lokasi(), isToday().
     *   Contoh: Trip::with(['days.trip', ...]).
     * - Relasi booking dibatasi ke subset yang dibutuhkan UI trip
     *   (bukan BookingResource utuh) untuk menghindari siklus
     *   BookingResource -> TripResource -> BookingResource.
     * - snapshot_package TIDAK dikirim mentah (besar dan internal);
     *   field turunan snapshot_nama dan snapshot_durasi_hari
     *   disediakan untuk display.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kode' => $this->kode,
            'booking_id' => $this->booking_id,
            'package_id' => $this->package_id,
            'mitra_id' => $this->mitra_id,
            'tanggal_mulai' => $this->tanggal_mulai->format('Y-m-d'),
            'tanggal_selesai' => $this->tanggal_selesai->format('Y-m-d'),
            'jumlah_peserta' => $this->jumlah_peserta,
            'timezone' => $this->timezone,
            'status' => $this->status->value,
            'statusLabel' => $this->status->label(),
            'snapshot_nama' => $this->snapshotNama(),
            'snapshot_durasi_hari' => $this->snapshotDurasiHari(),
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

            'package' => $this->whenLoaded('package', function (): ?array {
                /** @var Package|null $package */
                $package = $this->package;

                if ($package === null) {
                    return null;
                }

                return [
                    'id' => $package->id,
                    'nama' => $package->nama,
                    'kode' => $package->kode,
                    'slug' => $package->slug,
                    'timezone' => $package->timezone,
                    'cover_image' => $package->cover_image,
                ];
            }),

            'booking' => $this->whenLoaded('booking', function (): ?array {
                /** @var Booking|null $booking */
                $booking = $this->booking;

                if ($booking === null) {
                    return null;
                }

                return [
                    'id' => $booking->id,
                    'kode' => $booking->kode,
                    'jumlah_peserta' => $booking->jumlah_peserta,
                    'tanggal_keberangkatan' => $booking->tanggal_keberangkatan->format('Y-m-d'),
                    'status' => $booking->status->value,
                    'client' => $booking->relationLoaded('client') && $booking->client !== null
                        ? [
                            'id' => $booking->client->id,
                            'name' => $booking->client->name,
                            'email' => $booking->client->email,
                        ]
                        : null,
                ];
            }),

            'days' => TripDayResource::collection(
                $this->whenLoaded('days'),
            ),
        ];
    }
}
