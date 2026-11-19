<?php

namespace App\Http\Resources;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * Representasi user (admin, mitra, client).
 *
 * @mixin User
 *
 * @property-read int $id
 * @property-read string $name
 * @property-read string|null $username
 * @property-read string $email
 * @property-read string $role
 * @property-read string|null $timezone
 * @property-read bool $must_change_password
 * @property-read Carbon|null $email_verified_at
 * @property-read Carbon|null $created_at
 * @property-read Profile|null $profile
 */
final class UserResource extends JsonResource
{
    /**
     * Bentuk data user yang dikirim ke frontend.
     *
     * Keamanan:
     * - `password`, `remember_token`, `google_id`, `fcm_token` TIDAK pernah
     *   dikirim, meskipun model sudah punya `$hidden`. Ini adalah
     *   defense in depth: kontrak Resource eksplisit, tidak bergantung pada
     *   `$hidden` model.
     *
     * Relasi `profile` & `bankAccounts` hanya disertakan bila di-eager-load,
     * menghindari N+1 di daftar user admin.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->email,
            'role' => $this->role,
            'timezone' => $this->timezone,
            'must_change_password' => (bool) $this->must_change_password,
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),

            'profile' => $this->whenLoaded('profile', function (): ?array {
                /** @var Profile|null $profile */
                $profile = $this->profile;

                if ($profile === null) {
                    return null;
                }

                return [
                    'id' => $profile->id,
                    'tipe' => $profile->tipe,
                    'nama_penyedia_usaha' => $profile->nama_penyedia_usaha,
                    'nomor_identitas' => $profile->nomor_identitas,
                    'alamat' => $profile->alamat,
                    'telepon' => $profile->telepon,
                    'provinsi' => $profile->provinsi,
                    'kota' => $profile->kota,
                    'kode_pos' => $profile->kode_pos,
                    'logo' => $profile->logo,
                    'deskripsi' => $profile->deskripsi,
                ];
            }),

            // FIX: gunakan 3-arg whenLoaded (value, callback, default).
            // Versi lama `MitraBankAccountResource::collection($this->whenLoaded(...))`
            // menyebabkan "Call to a member function first() on null" karena
            // MissingValue diteruskan ke ResourceCollection constructor.
            //
            // Default `[]` memastikan client (yang tidak punya bank_accounts)
            // tidak crash dan mendapat array kosong.
            'bank_accounts' => $this->whenLoaded(
                'bankAccounts',
                fn (): array => MitraBankAccountResource::collection($this->bankAccounts)->resolve(),
                fn (): array => [],
            ),
        ];
    }
}
