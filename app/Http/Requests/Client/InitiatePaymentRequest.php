<?php

namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;

class InitiatePaymentRequest extends FormRequest
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
            'channel_code' => ['required', 'string', 'max:50'],
            'channel_properties' => ['nullable', 'array'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'channel_code.required' => 'Channel pembayaran wajib dipilih.',
            'channel_properties.array' => 'Format properti channel tidak valid.',
        ];
    }
}