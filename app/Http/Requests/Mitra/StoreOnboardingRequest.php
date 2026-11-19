<?php

namespace App\Http\Requests\Mitra;

use Illuminate\Foundation\Http\FormRequest;

final class StoreOnboardingRequest extends FormRequest
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
            // === Identitas usaha ===
            'nama_penyedia_usaha' => ['required', 'string', 'min:2', 'max:255'],
            'no_hp' => ['required', 'string', 'max:20', 'regex:/^[0-9+]+$/'],
            'deskripsi' => ['nullable', 'string', 'max:1000'],

            // === Rekening bank / e-wallet ===
            'accounts' => ['required', 'array', 'min:1', 'max:5'],
            'accounts.*.tipe' => ['required', 'string', 'in:bank,ewallet'],
            'accounts.*.nama_penyedia' => ['required', 'string', 'min:2', 'max:50'],
            'accounts.*.nomor_rekening' => ['required', 'string', 'min:4', 'max:50', 'regex:/^[0-9]+$/'],
            'accounts.*.nama_pemilik' => ['required', 'string', 'min:2', 'max:100'],
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
            'nama_penyedia_usaha.required' => 'Nama penyedia usaha wajib diisi.',
            'nama_penyedia_usaha.min' => 'Nama penyedia usaha minimal 2 karakter.',
            'nama_penyedia_usaha.max' => 'Nama penyedia usaha maksimal 255 karakter.',

            'no_hp.required' => 'Nomor HP wajib diisi.',
            'no_hp.max' => 'Nomor HP maksimal 20 karakter.',
            'no_hp.regex' => 'Format nomor HP tidak valid. Gunakan hanya angka atau tanda plus.',

            'deskripsi.max' => 'Deskripsi maksimal 1000 karakter.',

            'accounts.required' => 'Minimal satu rekening harus diisi.',
            'accounts.array' => 'Data rekening tidak valid.',
            'accounts.min' => 'Minimal satu rekening harus diisi.',
            'accounts.max' => 'Maksimal 5 rekening.',

            'accounts.*.tipe.required' => 'Tipe rekening wajib dipilih.',
            'accounts.*.tipe.in' => 'Tipe rekening harus bank atau e-wallet.',

            'accounts.*.nama_penyedia.required' => 'Nama penyedia wajib diisi.',
            'accounts.*.nama_penyedia.min' => 'Nama penyedia minimal 2 karakter.',
            'accounts.*.nama_penyedia.max' => 'Nama penyedia maksimal 50 karakter.',

            'accounts.*.nomor_rekening.required' => 'Nomor rekening wajib diisi.',
            'accounts.*.nomor_rekening.min' => 'Nomor rekening minimal 4 digit.',
            'accounts.*.nomor_rekening.max' => 'Nomor rekening maksimal 50 digit.',
            'accounts.*.nomor_rekening.regex' => 'Nomor rekening hanya boleh berisi angka.',

            'accounts.*.nama_pemilik.required' => 'Nama pemilik rekening wajib diisi.',
            'accounts.*.nama_pemilik.min' => 'Nama pemilik rekening minimal 2 karakter.',
            'accounts.*.nama_pemilik.max' => 'Nama pemilik rekening maksimal 100 karakter.',
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
            'nama_penyedia_usaha' => 'nama penyedia usaha',
            'no_hp' => 'nomor HP',
            'deskripsi' => 'deskripsi',
            'accounts' => 'rekening',
            'accounts.*.tipe' => 'tipe rekening',
            'accounts.*.nama_penyedia' => 'nama penyedia',
            'accounts.*.nomor_rekening' => 'nomor rekening',
            'accounts.*.nama_pemilik' => 'nama pemilik rekening',
        ];
    }
}
