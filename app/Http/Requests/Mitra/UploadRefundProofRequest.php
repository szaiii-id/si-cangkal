<?php

namespace App\Http\Requests\Mitra;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * Validasi bukti transfer refund manual oleh mitra.
 *
 * Otorisasi kepemilikan dan status refund dilakukan oleh RefundPolicy
 * di controller, bukan di sini.
 */
final class UploadRefundProofRequest extends FormRequest
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
            'refund_proof' => [
                'required',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
                'dimensions:max_width=4000,max_height=4000',
            ],
            'catatan' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'refund_proof.required' => 'Pilih file bukti transfer terlebih dahulu.',
            'refund_proof.file' => 'Bukti transfer harus berupa file.',
            'refund_proof.image' => 'File harus berupa gambar.',
            'refund_proof.mimes' => 'Format harus JPG, JPEG, PNG, atau WEBP.',
            'refund_proof.max' => 'Ukuran maksimal 5MB.',
            'refund_proof.dimensions' => 'Dimensi gambar maksimal 4000 x 4000 piksel.',
            'catatan.max' => 'Catatan maksimal 1000 karakter.',
        ];
    }

    public function proofFile(): UploadedFile
    {
        /** @var UploadedFile $file */
        $file = $this->file('refund_proof');

        return $file;
    }
}
