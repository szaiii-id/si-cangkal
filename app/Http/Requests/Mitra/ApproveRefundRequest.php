<?php

namespace App\Http\Requests\Mitra;

use App\Enums\RefundStatus;
use App\Models\Refund;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi persetujuan refund oleh mitra.
 *
 * Menggantikan blok `$request->validate([...])` di
 * `Mitra\RefundController@approve` (S-02, NF-03).
 *
 * Aturan:
 * - Hanya mitra pemilik refund (via booking) yang boleh menyetujui.
 * - Refund harus berstatus `PENDING`.
 * - Catatan opsional (alasan/berita transfer balik).
 *
 * Catatan keamanan:
 * - Kepemilikan dicek via `Refund::mitra_id` DAN `Booking::mitra_id`
 *   (kolom denormalisasi bisa tidak sinkron; lihat `RefundPolicy`).
 * - Pengecekan status `PENDING` di sini mencegah approve ulang refund
 *   yang sudah `SUCCEEDED` (bug halus pada kode lama).
 */
final class ApproveRefundRequest extends FormRequest
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
            'catatan' => ['nullable', 'string', 'min:3', 'max:1000'],
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
            'catatan.string' => 'Catatan harus berupa teks.',
            'catatan.min' => 'Catatan minimal 3 karakter.',
            'catatan.max' => 'Catatan maksimal 1000 karakter.',
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
            'catatan' => 'catatan',
        ];
    }

    /**
     * Validasi tambahan setelah aturan dasar lolos.
     *
     * Menolak approve bila refund sudah final (SUCCEEDED/CANCELLED)
     * atau booking terkait sudah dihapus.
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
                        'catatan',
                        "Refund berstatus '{$refund->status->label()}' tidak bisa disetujui lagi.",
                    );
                }
            },
        ];
    }
}