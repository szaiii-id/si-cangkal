<?php

namespace App\Http\Requests\Mitra;

use App\Models\Package;
use App\Support\Timezone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Validasi pembuatan pesanan manual oleh mitra.
 *
 * Menggantikan blok `$request->validate([...])` di
 * `Mitra\ManualBookingController@store` (S-02, NF-03).
 *
 * Aturan bisnis yang ditegakkan:
 * - Hanya mitra pemilik paket yang boleh membuat pesanan manual
 *   (authorize()).
 * - Tanggal keberangkatan minimal hari ini (H-0, timezone paket) karena
 *   mitra dapat memastikan kesiapan armada/pemandu secara langsung.
 * - Jumlah peserta >= 1 dan, bila paket rombongan, <= `max_peserta`.
 * - Data peserta harus lengkap sesuai `jumlah_peserta`.
 */
final class StoreManualBookingRequest extends FormRequest
{
    /**
     * Apakah user diizinkan membuat pesanan manual untuk paket ini.
     *
     * Dicek di sini agar error 403 dikembalikan lebih awal, sebelum
     * Form Request memvalidasi isi form (lebih hemat & lebih jelas).
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null || ! $user->isMitra()) {
            return false;
        }

        /** @var Package|null $package */
        $package = $this->route('package');

        if (! $package instanceof Package) {
            return false;
        }

        return $package->mitra_id === $user->id;
    }

    /**
     * Aturan validasi.
     *
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        $package = $this->getPackage();

        $maxPesertaRule = 'max:1000';
        if ($package !== null && $package->isRombongan() && $package->max_peserta !== null) {
            $maxPesertaRule = 'max:'.$package->max_peserta;
        }

        return [
            // === Identitas client (bisa baru, bisa existing) ===
            'nama_pelanggan' => ['required', 'string', 'min:2', 'max:255'],
            'email_pelanggan' => ['required', 'string', 'email', 'max:255'],
            'no_hp_pelanggan' => ['nullable', 'string', 'max:20'],

            // === Info booking ===
            'tanggal_keberangkatan' => [
                'required',
                'date',
                'date_format:Y-m-d',
                'after_or_equal:'.$this->minTanggal($package),
            ],
            'jumlah_peserta' => [
                'required',
                'integer',
                'min:1',
                $maxPesertaRule,
            ],
            'catatan' => ['nullable', 'string', 'max:1000'],

            // === Participants ===
            'participants' => ['required', 'array', 'min:1'],
            'participants.*.nama' => ['required', 'string', 'min:2', 'max:255'],
            'participants.*.umur' => ['nullable', 'integer', 'min:1', 'max:120'],
            'participants.*.no_hp' => ['nullable', 'string', 'max:20'],
            'participants.*.catatan' => ['nullable', 'string', 'max:500'],
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
            'nama_pelanggan.required' => 'Nama pelanggan wajib diisi.',
            'nama_pelanggan.min' => 'Nama pelanggan minimal 2 karakter.',
            'nama_pelanggan.max' => 'Nama pelanggan maksimal 255 karakter.',
            'email_pelanggan.required' => 'Email pelanggan wajib diisi.',
            'email_pelanggan.email' => 'Format email pelanggan tidak valid.',
            'email_pelanggan.max' => 'Email pelanggan maksimal 255 karakter.',
            'no_hp_pelanggan.max' => 'No. HP pelanggan maksimal 20 karakter.',

            'tanggal_keberangkatan.required' => 'Tanggal keberangkatan wajib diisi.',
            'tanggal_keberangkatan.date' => 'Tanggal keberangkatan tidak valid.',
            'tanggal_keberangkatan.date_format' => 'Format tanggal harus YYYY-MM-DD.',
            'tanggal_keberangkatan.after_or_equal' => 'Tanggal keberangkatan tidak boleh sebelum hari ini (timezone paket).',

            'jumlah_peserta.required' => 'Jumlah peserta wajib diisi.',
            'jumlah_peserta.integer' => 'Jumlah peserta harus berupa angka.',
            'jumlah_peserta.min' => 'Jumlah peserta minimal 1 orang.',
            'jumlah_peserta.max' => 'Jumlah peserta melebihi batas maksimal untuk paket ini.',

            'catatan.max' => 'Catatan maksimal 1000 karakter.',

            'participants.required' => 'Data peserta wajib diisi.',
            'participants.array' => 'Data peserta tidak valid.',
            'participants.min' => 'Minimal 1 peserta harus diisi.',
            'participants.*.nama.required' => 'Nama peserta wajib diisi.',
            'participants.*.nama.min' => 'Nama peserta minimal 2 karakter.',
            'participants.*.nama.max' => 'Nama peserta maksimal 255 karakter.',
            'participants.*.umur.integer' => 'Umur harus berupa angka.',
            'participants.*.umur.min' => 'Umur minimal 1 tahun.',
            'participants.*.umur.max' => 'Umur maksimal 120 tahun.',
            'participants.*.no_hp.max' => 'No. HP peserta maksimal 20 karakter.',
            'participants.*.catatan.max' => 'Catatan peserta maksimal 500 karakter.',
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
            'nama_pelanggan' => 'nama pelanggan',
            'email_pelanggan' => 'email pelanggan',
            'no_hp_pelanggan' => 'no. HP pelanggan',
            'tanggal_keberangkatan' => 'tanggal keberangkatan',
            'jumlah_peserta' => 'jumlah peserta',
            'catatan' => 'catatan',
            'participants' => 'data peserta',
            'participants.*.nama' => 'nama peserta',
            'participants.*.umur' => 'umur peserta',
            'participants.*.no_hp' => 'no. HP peserta',
            'participants.*.catatan' => 'catatan peserta',
        ];
    }

    /**
     * Validasi tambahan: jumlah participants harus sesuai jumlah_peserta.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $jumlahPeserta = $this->integer('jumlah_peserta');
                $participants = $this->input('participants', []);
                $participantCount = is_array($participants) ? count($participants) : 0;

                if ($participantCount !== $jumlahPeserta) {
                    $validator->errors()->add(
                        'participants',
                        "Data peserta harus sesuai jumlah peserta ({$jumlahPeserta} orang). ".
                        "Saat ini: {$participantCount} data.",
                    );
                }
            },
        ];
    }

    /**
     * Ambil Package dari route.
     */
    private function getPackage(): ?Package
    {
        $package = $this->route('package');

        return $package instanceof Package ? $package : null;
    }

    /**
     * Hitung tanggal minimal (hari ini / H-0 dari timezone package).
     */
    private function minTanggal(?Package $package): string
    {
        if ($package === null) {
            return now()->format('Y-m-d');
        }

        return Timezone::today($package->timezone)->format('Y-m-d');
    }
}