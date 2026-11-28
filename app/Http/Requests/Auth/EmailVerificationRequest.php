<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi tautan verifikasi email.
 *
 * Signature URL sudah diverifikasi middleware `signed`; FormRequest ini
 * menambah lapisan kedua: memastikan `id` cocok dengan user yang sedang
 * login dan `hash` adalah SHA-1 dari email user tersebut.
 */
final class EmailVerificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user instanceof User) {
            return false;
        }

        // User yang sudah terverifikasi tetap boleh lanjut; controller
        // akan mengarahkannya ke dashboard (bukan 403).
        if ($user->hasVerifiedEmail()) {
            return true;
        }

        return (string) $this->route('id') === (string) $user->getKey()
            && hash_equals(
                sha1($user->getEmailForVerification()),
                (string) $this->route('hash'),
            );
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [];
    }
}
