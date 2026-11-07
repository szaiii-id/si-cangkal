<?php

namespace App\Http\Requests\Client;

use App\Enums\BookingStatus;
use App\Models\Booking;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * Validasi upload bukti transfer oleh client.
 *
 * Menggantikan validasi inline di `Client\BookingController@uploadProof`
 * (S-02, S-08: OWASP A01 & A04).
 *
 * Aturan:
 * - Hanya client yang boleh upload.
 * - Booking harus miliknya dan berstatus `pending_payment` (S-01).
 * - File harus gambar, MIME diizinkan, ukuran wajar, dan dimensi
 *   dibatasi (S-08: mencegah decompression bomb / image bomb).
 *
 * Catatan: validasi status booking juga tersedia di `BookingPolicy::uploadProof`.
 * FormRequest memvalidasinya lebih awal agar error 422 dikembalikan sebelum
 * request menyentuh Service atau Policy. Keduanya adalah "defense in depth",
 * bukan duplikasi yang tidak perlu.
 */
final class UploadPaymentProofRequest extends FormRequest
{
    /**
     * Apakah user diizinkan melakukan request ini.
     *
     * Cek role dilakukan di sini (bukan di Policy) karena Policy menerima
     * Booking sebagai argumen dan dipanggil belakangan di controller.
     * FormRequest hanya memvalidasi "apakah aktor adalah client".
     */
    public function authorize(): bool
    {
        return $this->user()?->isClient() === true;
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
     * Cast eksplisit menghilangkan `UploadedFile|array|null` yang
     * dikembalikan `$this->file()` agar memenuhi signature
     * `BookingPaymentService::uploadProof(Booking, UploadedFile)`.
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
     * Memastikan booking pada route adalah milik client dan berstatus
     * `pending_payment`. Pengecekan ini duplikat dengan Policy, tetapi
     * menghasilkan response 422 yang lebih cepat dan informatif
     * (bukan 403 dari Policy).
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

                if ($booking->user_id !== $this->user()?->id) {
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