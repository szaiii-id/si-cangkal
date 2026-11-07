<?php

namespace App\Http\Requests\Mitra;

use App\Enums\RefundStatus;
use App\Models\Refund;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi penolakan refund oleh mitra.
 *
 * Menggantikan blok `$request->validate([...])` di
 * `Mitra\RefundController@reject` (S-02, NF-03).
 *
 * Aturan:
 * - Hanya mitra pemilik refund (via booking) yang boleh menolak.
 * - Refund harus berstatus `PENDING`.
 * - Alasan penolakan wajib, minimal 5 karakter, agar client tahu
 *   penyebab penolakan (dikirim via notifikasi & tersimpan di
 *   kolom `catatan` refund).
 *
 * Catatan keamanan:
 * - Pengecekan status `PENDING` di sini mencegah penolakan ulang
 *   refund yang sudah final.
 * - Kepemilikan dicek via `Refund::mitra_id` dan `Booking::mitra_id`
 *   (denormalisasi bisa tidak sinkron).
 */
final class RejectRefundRequest extends FormRequest
{
    /**
     * Apakah user diizinkan melakukan request ini.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null || ! $user->isMitra()) {
            return false;
        }

        /** @var Refund|null $refund */
        $refund = $this->route('refund');

        if (! $refund instanceof Refund) {
            return false;
        }

        if ($refund->mitra_id !== $user->id) {
            return false;
        }

        $booking = $refund->booking;

        if ($booking === null) {
            return false;
        }

        return $booking->mitra_id === $user->id;
    }

    /**
     * Aturan validasi.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'alasan_penolakan' => ['required', 'string', 'min:5', 'max:1000'],
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
            'alasan_penolakan.required' => 'Alasan penolakan wajib diisi.',
            'alasan_penolakan.string' => 'Alasan penolakan harus berupa teks.',
            'alasan_penolakan.min' => 'Alasan penolakan minimal 5 karakter.',
            'alasan_penolakan.max' => 'Alasan penolakan maksimal 1000 karakter.',
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
            'alasan_penolakan' => 'alasan penolakan',
        ];
    }

    /**
     * Validasi tambahan setelah aturan dasar lolos.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function ($validator): void {
                /** @var Refund|null $refund */
                $refund = $this->route('refund');

                if (! $refund instanceof Refund) {
                    return;
                }

                if ($refund->status !== RefundStatus::PENDING) {
                    $validator->errors()->add(
                        'alasan_penolakan',
                        "Refund berstatus '{$refund->status->label()}' tidak bisa ditolak lagi.",
                    );
                }
            },
        ];
    }
}