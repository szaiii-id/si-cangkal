<?php

namespace App\Http\Resources;

use App\Models\BookingParticipant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Representasi peserta dalam sebuah booking.
 *
 * @mixin BookingParticipant
 */
final class BookingParticipantResource extends JsonResource
{
    /**
     * Bentuk data peserta yang dikirim ke frontend.
     *
     * Format snake_case dipertahankan untuk field database
     * (`no_hp`, `catatan`) karena frontend Svelte saat ini
     * mengonsumsi `participant.no_hp` secara langsung.
     *
     * Field turunan:
     * - `umurLabel` mempermudah tampilan ("35 thn" / "-") tanpa
     *   frontend perlu logika null-handling.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nama' => $this->nama,
            'umur' => $this->umur,
            'umurLabel' => $this->umurLabel(),
            'no_hp' => $this->no_hp,
            'catatan' => $this->catatan,
            'has_catatan' => $this->hasCatatan(),
        ];
    }
}