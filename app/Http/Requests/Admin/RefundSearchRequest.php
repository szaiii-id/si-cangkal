<?php

namespace App\Http\Requests\Admin;

use App\Enums\RefundStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validasi filter daftar refund di panel admin.
 *
 * Menggantikan pembacaan `$request->input(...)` tanpa validasi di
 * `Admin\RefundController@index` (NF-03, S-02).
 *
 * Validasi memastikan parameter `search` dan `status` tidak membawa
 * payload aneh yang bisa menyebabkan error query atau logika filter
 * yang tidak terduga. `per_page` dibatasi untuk mencegah pagination
 * abuse (mis. meminta 10000 data sekaligus).
 */
final class RefundSearchRequest extends FormRequest
{
    /**
     * Apakah user diizinkan melakukan request ini.
     */
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    /**
     * Aturan validasi.
     *
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'status' => [
                'nullable',
                'string',
                Rule::in(RefundStatus::values()),
            ],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * Pesan error Bahasa Indonesia.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'search.string' => 'Kata kunci pencarian harus berupa teks.',
            'search.max' => 'Kata kunci pencarian maksimal 255 karakter.',
            'status.string' => 'Status harus berupa teks.',
            'status.in' => 'Status refund tidak valid.',
            'per_page.integer' => 'Jumlah per halaman harus berupa angka.',
            'per_page.min' => 'Jumlah per halaman minimal 5.',
            'per_page.max' => 'Jumlah per halaman maksimal 100.',
            'page.integer' => 'Nomor halaman harus berupa angka.',
            'page.min' => 'Nomor halaman minimal 1.',
        ];
    }

    /**
     * Nama atribut untuk pesan error.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'search' => 'kata kunci pencarian',
            'status' => 'status',
            'per_page' => 'jumlah per halaman',
            'page' => 'nomor halaman',
        ];
    }
}
