<?php

namespace App\Http\Requests\Mitra;

use App\Enums\RefundStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validasi filter daftar refund di panel mitra.
 *
 * Konsisten dengan `Admin\RefundSearchRequest`. `per_page` dibatasi
 * untuk mencegah pagination abuse.
 */
final class ListRefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isMitra() === true;
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', Rule::in(RefundStatus::values())],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'search.string' => 'Kata kunci pencarian harus berupa teks.',
            'search.max' => 'Kata kunci pencarian maksimal 255 karakter.',
            'status.in' => 'Status refund tidak valid.',
            'per_page.min' => 'Jumlah per halaman minimal 5.',
            'per_page.max' => 'Jumlah per halaman maksimal 100.',
        ];
    }
}