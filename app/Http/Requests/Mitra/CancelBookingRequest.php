<?php

namespace App\Http\Requests\Mitra;

use App\Models\Booking;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi pembatalan booking oleh mitra.
 *
 * Menggantikan blok `$request->validate([...])` di
 * `Mitra\BookingController@cancel` (S-02, NF-03).
 *
 * Aturan:
 * - Hanya mitra pemilik booking yang boleh membatalkan.
 * - Alasan wajib, minimal 5 karakter agar client tahu penyebabnya
 *   (dikirim via notifikasi & ditampilkan di detail booking).
 *
 * Catatan status:
 * - Booking yang sudah `completed` atau `cancelled` tidak boleh
 *   dibatalkan lagi. Pengecekan ini ada di `BookingPolicy::cancelByMitra`
 *   dan `BookingService` (via `BookingCancellationService`), bukan di sini,
 *   agar Form Request tetap ramping dan pesan error lebih spesifik.
 */
final class CancelBookingRequest extends FormRequest
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

        /** @var Booking|null $booking */
        $booking = $this->route('booking');

        if (! $booking instanceof Booking) {
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
            'alasan' => ['required', 'string', 'min:5', 'max:1000'],
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
            'alasan.required' => 'Alasan pembatalan wajib diisi.',
            'alasan.string' => 'Alasan pembatalan harus berupa teks.',
            'alasan.min' => 'Alasan pembatalan minimal 5 karakter.',
            'alasan.max' => 'Alasan pembatalan maksimal 1000 karakter.',
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
            'alasan' => 'alasan pembatalan',
        ];
    }
}