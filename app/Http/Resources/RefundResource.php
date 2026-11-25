<?php

namespace App\Http\Resources;

use App\Models\Refund;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Representasi refund manual pada sebuah booking.
 *
 * @mixin Refund
 */
final class RefundResource extends JsonResource
{
    /**
     * Bentuk data refund yang dikirim ke frontend.
     *
     * Field database tetap snake_case (amount, failure_code,
     * created_at, catatan) mengikuti kontrak BookingResource
     * yang sudah berjalan. Field turunan memakai camelCase
     * (amountRupiah, reasonLabel, statusLabel, isFinal).
     *
     * Relasi mitra dan mitraInitiator hanya disertakan bila
     * di-eager-load - menghindari N+1 di daftar refund admin.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'booking_id' => $this->booking_id,
            'mitra_id' => $this->mitra_id,
            'amount' => (string) $this->amount,
            'amountRupiah' => $this->amountRupiah(),
            'currency' => $this->currency,
            'reason' => $this->reason->value,
            'reasonLabel' => $this->reasonLabel(),
            'status' => $this->status->value,
            'statusLabel' => $this->statusLabel(),
            'isFinal' => $this->isFinal(),
            'failure_code' => $this->failure_code,
            'refund_fee_amount' => $this->refund_fee_amount !== null
                ? (string) $this->refund_fee_amount
                : null,
            'refundFeeRupiah' => $this->refundFeeRupiah(),
            'catatan' => $this->catatan,
            // Monitoring admin: hanya indikator, bukan aksi verifikasi.
            'hasProof' => $this->proof_path !== null && $this->proof_path !== '',
            'proof_uploaded_at' => $this->proof_uploaded_at?->toIso8601String(),
            'proof_uploaded_by' => $this->proof_uploaded_by,
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
            'mitraInitiator' => $this->whenLoaded('mitraInitiator', function (): ?array {
                /** @var User|null $initiator */
                $initiator = $this->mitraInitiator;

                if ($initiator === null) {
                    return null;
                }

                return [
                    'id' => $initiator->id,
                    'name' => $initiator->name,
                    'email' => $initiator->email,
                ];
            }),
            'booking' => $this->whenLoaded('booking', function (): ?array {
                $booking = $this->booking;

                if ($booking === null) {
                    return null;
                }

                return [
                    'id' => $booking->id,
                    'kode' => $booking->kode,
                    'total_harga' => (string) $booking->total_harga,
                    'status' => $booking->status->value,
                ];
            }),
        ];
    }
}
