<?php

namespace App\Http\Requests\Mitra;

use App\Models\Booking;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi penolakan bukti pembayaran oleh mitra.
 *
 * Menggantikan blok `$request->validate([...])` di
 * `Mitra\BookingController@rejectPayment` (S-02, NF-03).
 *
 * Aturan:
 * - Hanya mitra pemilik booking yang boleh menolak.
 * - Alasan penolakan wajib, minimal 5 karakter agar tidak sekadar
 *   "salah" atau "no" yang tidak informatif untuk client.
 * - Alasan akan ditampilkan di notifikasi ke client dan disimpan
 *   di kolom `payment_reject_reason` pada tabel `bookings`.
 */
final class RejectPaymentRequest extends FormRequest
{
    /**
     * Apakah user diizinkan melakukan request ini.
     *
     * Cek kepemilikan di sini untuk response 403 yang cepat. Pengecekan
     * status `pending_payment` dilakukan di Policy dan Service, bukan di
     * sini, agar pesan error bisa lebih spesifik saat status tidak sesuai.
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
            'reason' => ['required', 'string', 'min:5', 'max:500'],
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
            'reason.required' => 'Alasan penolakan wajib diisi.',
            'reason.string' => 'Alasan penolakan harus berupa teks.',
            'reason.min' => 'Alasan penolakan minimal 5 karakter.',
            'reason.max' => 'Alasan penolakan maksimal 500 karakter.',
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
            'reason' => 'alasan penolakan',
        ];
    }
}