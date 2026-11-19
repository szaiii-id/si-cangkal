<?php

namespace App\Http\Resources;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Representasi pembayaran manual pada sebuah booking.
 *
 * @mixin Payment
 */
final class PaymentResource extends JsonResource
{
    /**
     * Bentuk data pembayaran yang dikirim ke frontend.
     *
     * Format snake_case untuk field database (`verified_at`) dan
     * camelCase untuk field turunan (`jumlahRupiah`, `metodeLabel`,
     * `isVerified`, `verifier`) — mengikuti pola `BookingResource`
     * yang sudah berjalan.
     *
     * Relasi `verifier` hanya disertakan bila sudah di-eager-load,
     * menghindari N+1 saat endpoint daftar tidak membutuhkannya.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'jumlah' => (string) $this->jumlah,
            'jumlahRupiah' => $this->jumlahRupiah(),
            'metode' => $this->metode->value,
            'metodeLabel' => $this->metodeLabel(),
            'isVerified' => $this->isVerified(),
            'verified_at' => $this->verified_at?->toIso8601String(),
            'catatan' => $this->catatan,
            'verifier' => $this->whenLoaded('verifier', function (): ?array {
                /** @var User|null $verifier */
                $verifier = $this->verifier;

                if ($verifier === null) {
                    return null;
                }

                return [
                    'id' => $verifier->id,
                    'name' => $verifier->name,
                    'email' => $verifier->email,
                ];
            }),
        ];
    }
}
