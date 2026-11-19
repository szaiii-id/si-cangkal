/**
 * Props halaman AUTH.
 *
 * Mirror dari `App\Http\Controllers\Auth\*` di backend.
 */

// ==========================================
// PASSWORD RESET
// ==========================================

/**
 * `Auth\PasswordResetController@edit`.
 *
 * `email` bisa null kalau user akses direct tanpa query string.
 */
export type AuthResetPasswordProps = {
    token: string;
    email: string | null;
};

// ==========================================
// CHANGE PASSWORD
// ==========================================

/**
 * `Auth\ChangePasswordController@edit`.
 */
export type AuthChangePasswordProps = {
    mustChangePassword: boolean;
};