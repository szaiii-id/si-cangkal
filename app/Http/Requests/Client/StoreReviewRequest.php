<?php

namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;

class StoreReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isClient() === true;
    }

    /**
     * Trim dulu supaya batas 10–1000 karakter benar-benar dihitung
     * setelah pembersihan (AC-5), bukan dari string mentah.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('komentar') && is_string($this->input('komentar'))) {
            $this->merge([
                'komentar' => trim((string) $this->input('komentar')),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'komentar' => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }

    /**
     * Kontrak data yang dikonsumsi ReviewService — supaya tipe tidak
     * meluas jadi array<string, mixed> setelah validated().
     *
     * @return array{rating: int, komentar: string}
     */
    public function reviewData(): array
    {
        $validated = $this->validated();

        return [
            'rating' => (int) $validated['rating'],
            'komentar' => (string) $validated['komentar'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rating.required' => 'Bintang wajib dipilih.',
            'rating.min' => 'Rating minimal 1 bintang.',
            'rating.max' => 'Rating maksimal 5 bintang.',
            'komentar.required' => 'Komentar wajib diisi.',
            'komentar.min' => 'Komentar minimal 10 karakter.',
            'komentar.max' => 'Komentar maksimal 1000 karakter.',
        ];
    }
}
