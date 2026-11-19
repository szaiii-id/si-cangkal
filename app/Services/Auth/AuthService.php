<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Repositories\Contracts\ProfileRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Contracts\User as SocialiteUser;

class AuthService
{
    protected UserRepositoryInterface $userRepository;

    public function __construct(
        UserRepositoryInterface $userRepository,
        protected ProfileRepositoryInterface $profileRepository,
    ) {
        $this->userRepository = $userRepository;
    }

    /**
     * Logika untuk mendaftarkan Client baru.
     *
     * @param  array<string, mixed>  $data
     */
    public function registerClient(array $data): User
    {
        $user = $this->userRepository->createClient($data);

        Auth::login($user);

        return $user;
    }

    /**
     * Logika untuk mendaftarkan Mitra baru.
     *
     * Mitra daftar mandiri → role 'mitra' → profile dibuat kosong
     * (diisi nanti di /mitra/onboarding) → langsung login.
     *
     * @param  array<string, mixed>  $data
     */
    public function registerMitra(array $data): User
    {
        $user = $this->userRepository->createUser([
            'name' => $data['name'],
            'username' => $data['username'] ?? null,
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => 'mitra',
            'must_change_password' => false,
        ]);

        $this->profileRepository->createForUser($user, [
            'tipe' => 'mitra',
        ]);

        Auth::login($user);

        return $user;
    }

    /**
     * Logika Dual-Login (Username / Email).
     *
     * @throws ValidationException
     */
    public function login(string $identitas, string $password, bool $remember = false): User
    {
        $user = $this->userRepository->findByEmailOrUsername($identitas);

        if (! $user || ! Hash::check($password, $user->password)) {
            Log::warning('=== AUTH SERVICE: LOGIN REJECTED ===', [
                'reason' => ! $user ? 'user_not_found' : 'password_mismatch',
                'identitas_input' => $identitas,
            ]);

            throw ValidationException::withMessages([
                'identitas' => ['Kredensial yang diberikan tidak cocok dengan data kami.'],
            ]);
        }

        Auth::login($user, $remember);

        return $user;
    }

    /**
     * Logika untuk Login menggunakan Google (SSO).
     */
    public function handleGoogleLogin(SocialiteUser $googleUser): User
    {
        $user = $this->userRepository->findByGoogleId($googleUser->getId());

        if (! $user) {
            $user = $this->userRepository->findByEmail($googleUser->getEmail());

            if ($user) {
                $user->update(['google_id' => $googleUser->getId()]);
            } else {
                $email = (string) $googleUser->getEmail();
                $usernameLokal = explode('@', $email)[0].rand(100, 999);

                $user = $this->userRepository->createClient([
                    'name' => $googleUser->getName(),
                    'email' => $email,
                    'username' => $usernameLokal,
                    'google_id' => $googleUser->getId(),
                    'password' => null,
                ]);
            }
        }

        Auth::login($user);

        return $user;
    }

    /**
     * Tentukan URL redirect setelah login berdasarkan role & status wajib ganti password.
     *
     * @return array{url: string, must_change_password: bool}
     */
    public function resolveRedirectAfterLogin(User $user): array
    {
        // Prioritas: wajib ganti password
        if ($user->must_change_password) {
            return [
                'url' => route('password.change'),
                'must_change_password' => true,
            ];
        }

        return [
            'url' => $this->resolveDashboardUrl($user),
            'must_change_password' => false,
        ];
    }

    /**
     * Resolve URL dashboard berdasarkan role user.
     *
     * Menggunakan guard `in_array` sebelum `match` untuk dua alasan:
     * 1. **Keamanan data** — jika `role` berisi nilai tak terduga
     *    (data legacy, corrupt, atau role baru yang lupa di-handle),
     *    user akan di-redirect ke login alih-alih mengalami
     *    `UnhandledMatchError` (500 error).
     * 2. **PHPStan** — guard `in_array` mempersempit tipe `role` ke
     *    union literal `'admin'|'mitra'|'client'`, sehingga `match`
     *    tanpa `default` tidak dianggap "always true" oleh PHPStan.
     */
    private function resolveDashboardUrl(User $user): string
    {
        if (! in_array($user->role, ['admin', 'mitra', 'client'], true)) {
            Log::warning('=== AUTH: UNKNOWN ROLE ===', [
                'user_id' => $user->id,
                'role' => $user->role,
            ]);

            return route('login');
        }

        return match ($user->role) {
            'admin' => route('admin.dashboard'),
            'mitra' => route('mitra.dashboard'),
            'client' => route('client.dashboard'),
        };
    }

    /**
     * Logika Logout & menghapus sesi beserta cookie "remember me".
     *
     * Menghapus cookie remember_web_* sangat penting karena
     * `Auth::logout()` dan `session()->invalidate()` TIDAK
     * menghapus cookie tersebut. Tanpa penghapusan, browser akan
     * auto-login ulang dari cookie, menyebabkan redirect loop
     * setelah logout.
     */
    public function logout(): void
    {
        Auth::logout();

        request()->session()->invalidate();
        request()->session()->regenerateToken();

        $this->forgetRememberCookie();
    }

    /**
     * Hapus semua cookie "remember me" yang di-set oleh Laravel.
     *
     * Laravel menamai cookie remember sebagai:
     *   remember_<guard>_<sha1(provider_class)>
     *
     * Alih-alih menghitung hash, kita iterasi semua cookie yang
     * diawali "remember_" agar aman untuk berbagai guard/provider.
     */
    private function forgetRememberCookie(): void
    {
        foreach (request()->cookies->all() as $name => $value) {
            if (str_starts_with($name, 'remember_')) {
                Cookie::queue(Cookie::forget($name));
            }
        }
    }
}
