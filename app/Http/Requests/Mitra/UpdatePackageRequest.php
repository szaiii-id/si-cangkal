<?php

namespace App\Http\Requests\Mitra;

use App\Enums\PackageKategori;
use App\Models\Package;
use App\Support\Timezone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $package = $this->route('package');

        if ($user === null || ! $user->isMitra()) {
            return false;
        }

        if (! $package instanceof Package) {
            return false;
        }

        return $package->mitra_id === $user->id;
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'min:3', 'max:255'],
            'deskripsi' => ['nullable', 'string', 'max:5000'],
            'kategori' => ['required', 'string', Rule::in(PackageKategori::values())],
            'durasi_hari' => ['required', 'integer', 'min:1', 'max:30'],
            'durasi_malam' => ['required', 'integer', 'min:0', 'max:29'],

            'harga_per_orang' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'harga_per_rombongan' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'max_peserta' => ['nullable', 'required_if:harga_per_rombongan,>0', 'integer', 'min:1', 'max:1000'],

            'timezone' => [
                'required', 'string', 'max:50',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if (! Timezone::isValid($value)) {
                        $fail('Timezone tidak valid.');
                    }
                },
            ],
            'cover_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],

            'remove_cover_image' => ['nullable', 'boolean'],

            'days' => ['nullable', 'string'],
            'facilities' => ['nullable', 'string'],
        ];
    }
}
