<?php

namespace App\Http\Requests\Client;

use App\Enums\BookingStatus;
use App\Models\Booking;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

final class UploadPaymentProofRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isClient() === true;
    }

    /**
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
     * Aman dipanggil SETELAH `validate()` lolos — aturan `required`
     * pada `payment_proof` menjamin file tersedia. Cast eksplisit
     * menghilangkan `UploadedFile|array|null` yang dikembalikan
     * `$this->file()` agar memenuhi signature
     * `BookingPaymentService::uploadProof(Booking, UploadedFile)`.
     */
    public function paymentProof(): UploadedFile
    {
        /** @var UploadedFile $file */
        $file = $this->file('payment_proof');

        return $file;
    }

    /**
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
