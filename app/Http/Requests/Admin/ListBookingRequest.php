<?php

namespace App\Http\Requests\Admin;

use App\Enums\BookingStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', Rule::in(BookingStatus::values())],
            'package_id' => ['nullable', 'integer', 'min:1'],
            'tanggal_dari' => ['nullable', 'date_format:Y-m-d'],
            'tanggal_sampai' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:tanggal_dari'],
            'is_today' => ['nullable', 'in:0,1'],
            'sort' => [
                'nullable',
                'string',
                Rule::in(['created_at', 'tanggal_keberangkatan', 'total_harga', 'status', 'kode']),
            ],
            'direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
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
            'status.in' => 'Status booking tidak valid.',
            'tanggal_dari.date_format' => 'Format tanggal harus YYYY-MM-DD.',
            'tanggal_sampai.date_format' => 'Format tanggal harus YYYY-MM-DD.',
            'tanggal_sampai.after_or_equal' => 'Tanggal sampai harus setelah atau sama dengan tanggal dari.',
            'is_today.in' => 'Filter hari ini tidak valid.',
            'per_page.min' => 'Jumlah per halaman minimal 5.',
            'per_page.max' => 'Jumlah per halaman maksimal 100.',
            'direction.in' => 'Arah sortir harus asc atau desc.',
        ];
    }
}
