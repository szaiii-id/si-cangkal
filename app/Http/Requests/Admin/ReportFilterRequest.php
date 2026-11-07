<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi filter periode laporan (revenue, kinerja mitra).
 *
 * Mencegah error 500 saat input `start_date`/`end_date` tidak valid
 * (mis. string acak, format salah, atau rentang terbalik).
 *
 * Default: awal bulan berjalan sampai akhir bulan berjalan, jika
 * parameter tidak disediakan.
 */
final class ReportFilterRequest extends FormRequest
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
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'start_date.date_format' => 'Format tanggal mulai harus YYYY-MM-DD.',
            'end_date.date_format' => 'Format tanggal akhir harus YYYY-MM-DD.',
            'end_date.after_or_equal' => 'Tanggal akhir harus setelah atau sama dengan tanggal mulai.',
        ];
    }

    /**
     * Rentang periode yang sudah divalidasi, dengan fallback ke bulan berjalan.
     *
     * @return array{start_date: string, end_date: string}
     */
    public function period(): array
    {
        $validated = $this->validated();

        return [
            'start_date' => $validated['start_date']
                ?? now()->startOfMonth()->format('Y-m-d'),
            'end_date' => $validated['end_date']
                ?? now()->endOfMonth()->format('Y-m-d'),
        ];
    }
}