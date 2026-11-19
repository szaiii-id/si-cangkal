<?php

namespace App\Http\Requests\Mitra;

use App\Enums\PackageFacilityTipe;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePackageFacilityRequest extends FormRequest
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
            'tipe' => ['required', 'string', Rule::in(PackageFacilityTipe::values())],
            'nama' => ['required', 'string', 'min:2', 'max:255'],
        ];
    }
}
