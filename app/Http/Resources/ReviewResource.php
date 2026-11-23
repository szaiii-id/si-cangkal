<?php

namespace App\Http\Resources;

use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Review
 */
class ReviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rating' => $this->rating,
            'komentar' => $this->komentar,
            'created_at' => $this->created_at->toIso8601String(),
            'client' => $this->whenLoaded('client', fn (): ?array => $this->clientPayload()),
        ];
    }

    /**
     * Privasi (AC-10): nama depan saja. Dilakukan di server supaya
     * tidak bergantung pada kerapian frontend saat memakai API.
     *
     * @return array{nama: string}|null
     */
    private function clientPayload(): ?array
    {
        if ($this->client === null) {
            return null;
        }

        return [
            'nama' => explode(' ', trim($this->client->name))[0],
        ];
    }
}
