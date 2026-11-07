<?php

namespace App\Http\Requests\Mitra;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi pembaruan rekening bank mitra.
 *
 * Fitur ini melindungi aksi sensitif (mengubah rekening tujuan transfer)
 * dengan mewajibkan verifikasi password akun saat ini.
 */
final class UpdateBankAccountRequest extends FormRequest
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
            // === Verifikasi Keamanan ===
            // Wajib diisi dan harus cocok dengan password user yang sedang login
            'password' => ['required', 'string', 'current_password'],

            // === Data Rekening (Array) ===
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
            'password.required' => 'Password konfirmasi wajib diisi.',
            'password.current_password' => 'Password yang Anda masukkan salah.',

            'accounts.required' => 'Data rekening tidak boleh kosong.',
            'accounts.array' => 'Format data rekening tidak valid.',
            'accounts.min' => 'Minimal 1 rekening harus diisi.',
            'accounts.max' => 'Maksimal 5 rekening.',

            'accounts.*.tipe.required' => 'Tipe rekening wajib dipilih.',
            'accounts.*.tipe.in' => 'Tipe rekening harus bank atau e-wallet.',

            'accounts.*.nama_penyedia.required' => 'Nama bank/e-wallet wajib diisi.',
            'accounts.*.nama_penyedia.min' => 'Nama bank/e-wallet minimal 2 karakter.',
            'accounts.*.nama_penyedia.max' => 'Nama bank/e-wallet maksimal 50 karakter.',

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
            'password' => 'password konfirmasi',
            'accounts' => 'rekening',
            'accounts.*.tipe' => 'tipe rekening',
            'accounts.*.nama_penyedia' => 'nama penyedia',
            'accounts.*.nomor_rekening' => 'nomor rekening',
            'accounts.*.nama_pemilik' => 'nama pemilik rekening',
        ];
    }
}