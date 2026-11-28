<?php

namespace App\Http\Resources;

use App\Models\Booking;
use App\Models\Package;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Representasi booking (pesanan client untuk sebuah paket wisata).
 *
 * Kontrak field:
 * - Field database memakai snake_case (`tanggal_keberangkatan`,
 *   `total_harga`, `expired_at`, `catatan`).
 * - Field turunan memakai camelCase (`statusLabel`, `has_payment_proof`).
 *
 * Catatan penting:
 * - `payment_proof_path` TIDAK pernah dikirim mentah ke frontend.
 *   FE hanya menerima `has_payment_proof` (boolean). URL download
 *   disusun oleh controller sesuai role (client/mitra/admin) karena
 *   route-nya berbeda. Ini mencegah kebocoran path file privat (S-09).
 * - Relasi `participants`, `payments`, `refunds`, dan
 *   `mitra.bankAccounts` dikirim sebagai **array biasa** (bukan
 *   wrapper `{ data: [...] }`) via `->resolve()`. Ini memastikan FE
 *   bisa langsung iterasi tanpa normalisasi.
 *
 * Wajib eager-load di controller untuk menghindari N+1:
 * - `package` (subset kolom)
 * - `client` (subset kolom)
 * - `mitra.bankAccounts` (jika ingin menampilkan rekening)
 * - `mitra.profile` (jika ingin menampilkan telepon mitra)
 * - `participants`
 * - `payments.verifier`
 * - `refunds`
 *
 * @mixin Booking
 */
final class BookingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kode' => $this->kode,
            'status' => $this->status->value,
            'statusLabel' => $this->status->label(),
            'tanggal_keberangkatan' => $this->tanggal_keberangkatan?->format('Y-m-d'),
            'jumlah_peserta' => $this->jumlah_peserta,
            'total_harga' => (string) $this->total_harga,
            'has_payment_proof' => $this->payment_proof_path !== null && $this->payment_proof_path !== '',
            'payment_reject_reason' => $this->payment_reject_reason,
            'expired_at' => $this->expired_at?->toIso8601String(),
            'catatan' => $this->catatan,
            'created_at' => $this->created_at?->toIso8601String(),

            'package' => $this->whenLoaded('package', fn (): ?array => $this->packagePayload()),
            'client' => $this->whenLoaded('client', fn (): ?array => $this->clientPayload()),
            'mitra' => $this->whenLoaded('mitra', fn (): ?array => $this->mitraPayload()),

            // ->resolve() memaksa Collection jadi array biasa,
            // bukan wrapper `{ "data": [...] }`.
            'participants' => BookingParticipantResource::collection(
                $this->whenLoaded('participants'),
            )->resolve(),

            'payments' => PaymentResource::collection(
                $this->whenLoaded('payments'),
            )->resolve(),

            'refunds' => RefundResource::collection(
                $this->whenLoaded('refunds'),
            )->resolve(),
        ];
    }

    /**
     * Payload ringkas paket wisata.
     *
     * @return array<string, mixed>|null
     */
    private function packagePayload(): ?array
    {
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
            'latitude' => $package->latitude !== null ? (string) $package->latitude : null,
            'longitude' => $package->longitude !== null ? (string) $package->longitude : null,
            'harga_per_orang' => (string) $package->harga_per_orang,
            'harga_per_rombongan' => $package->harga_per_rombongan !== null
                ? (string) $package->harga_per_rombongan
                : null,
            'durasi_hari' => $package->durasi_hari,
            'durasi_malam' => $package->durasi_malam,
            'cover_image' => $package->cover_image,
        ];
    }

    /**
     * Payload ringkas client (pemilik booking).
     *
     * @return array<string, mixed>|null
     */
    private function clientPayload(): ?array
    {
        /** @var User|null $client */
        $client = $this->client;

        if ($client === null) {
            return null;
        }

        return [
            'id' => $client->id,
            'name' => $client->name,
            'email' => $client->email,
            'username' => $client->username,
        ];
    }

    /**
     * Payload ringkas mitra (penyelenggara trip) + rekening bank.
     *
     * `bankAccounts` dan `profile` hanya disertakan bila sudah di-eager-load
     * (`mitra.bankAccounts`, `mitra.profile`), mencegah N+1 saat endpoint daftar
     * tidak membutuhkan data tersebut.
     *
     * @return array<string, mixed>|null
     */
    private function mitraPayload(): ?array
    {
        /** @var User|null $mitra */
        $mitra = $this->mitra;

        if ($mitra === null) {
            return null;
        }

        return [
            'id' => $mitra->id,
            'name' => $mitra->name,
            'telepon' => $mitra->relationLoaded('profile') ? $mitra->profile?->telepon : null,
            'bankAccounts' => $mitra->relationLoaded('bankAccounts')
                ? MitraBankAccountResource::collection($mitra->bankAccounts)->resolve()
                : [],
        ];
    }
}
