<?php

namespace App\Http\Requests\Mitra;

use App\Enums\BookingStatus;
use App\Models\Booking;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * Validasi upload bukti transfer oleh mitra (pesanan manual / offline).
 *
 * Menggantikan validasi inline di `Mitra\BookingController@uploadProof`
 * (S-02, S-08: OWASP A01 & A04).
 *
 * Aturan:
 * - Hanya mitra yang boleh upload.
 * - Booking harus miliknya dan berstatus `pending_payment` (S-01).
 * - File harus gambar, MIME diizinkan, ukuran wajar, dan dimensi
 *   dibatasi (S-08).
 *
 * Perbedaan dengan `Client\UploadPaymentProofRequest`:
 * - Hanya pada `authorize()` (mitra, bukan client).
 * - Kepemilikan booking dicek terhadap `mitra_id`, bukan `user_id`.
 * - Pesan error menyebut "mitra" (untuk audit trail).
 */
final class UploadPaymentProofRequest extends FormRequest
{
    /**
     * Apakah user diizinkan melakukan request ini.
     */
    public function authorize(): bool
    {
        return $this->user()?->isMitra() === true;
    }

    /**
     * Aturan validasi.
     *
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'payment_proof' => [
                'required',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
                'dimensions:max_width=4000,max_height=4000',
            ],
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
            'payment_proof.required' => 'Pilih file bukti transfer terlebih dahulu.',
            'payment_proof.file' => 'Bukti transfer harus berupa file.',
            'payment_proof.image' => 'File harus berupa gambar.',
            'payment_proof.mimes' => 'Format harus JPG, JPEG, PNG, atau WEBP.',
            'payment_proof.max' => 'Ukuran maksimal 5MB.',
            'payment_proof.dimensions' => 'Dimensi gambar maksimal 4000 x 4000 piksel.',
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
            'payment_proof' => 'bukti transfer',
        ];
    }

    /**
     * Ambil file bukti transfer sebagai `UploadedFile` (non-null).
     *
     * Method ini aman dipanggil SETELAH `validate()` lolos; aturan
     * `required` pada `payment_proof` menjamin file tersedia.
     */
    public function paymentProof(): UploadedFile
    {
        /** @var UploadedFile $file */
        $file = $this->file('payment_proof');

        return $file;
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
                /** @var Booking|null $booking */
                $booking = $this->route('booking');

                if (! $booking instanceof Booking) {
                    return;
                }

                if ($booking->mitra_id !== $this->user()?->id) {
                    $validator->errors()->add(
                        'payment_proof',
                        'Anda tidak memiliki akses ke booking ini.',
                    );

                    return;
                }

                if ($booking->status !== BookingStatus::PENDING_PAYMENT) {
                    $validator->errors()->add(
                        'payment_proof',
                        'Booking ini sudah tidak dalam status menunggu pembayaran.',
                    );
                }
            },
        ];
    }
}
