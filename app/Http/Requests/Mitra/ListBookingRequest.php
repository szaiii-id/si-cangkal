<?php

namespace App\Http\Requests\Mitra;

use App\Enums\BookingStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validasi filter daftar booking di panel mitra.
 *
 * Konsisten dengan `Admin\ListBookingRequest`. Perbedaan:
 * - Tidak ada `package_id` & `tanggal_dari/sampai` (mitra fokus
 *   ke booking yang ditujukan kepadanya).
 * - Scope `mitra_id` di-inject oleh controller, bukan dari request.
 */
final class ListBookingRequest extends FormRequest
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
            'status' => ['nullable', 'string', Rule::in(BookingStatus::values())],
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
            'per_page.min' => 'Jumlah per halaman minimal 5.',
            'per_page.max' => 'Jumlah per halaman maksimal 100.',
            'direction.in' => 'Arah sortir harus asc atau desc.',
        ];
    }
}
