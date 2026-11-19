<?php

namespace App\Services\User;

use App\Models\Profile;
use App\Models\User;
use App\Repositories\Contracts\ProfileRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Notification\Contracts\NotificationDispatcherInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UserService
{
    public function __construct(
        protected UserRepositoryInterface $userRepository,
        protected ProfileRepositoryInterface $profileRepository,
        protected NotificationDispatcherInterface $notificationDispatcher,
    ) {}

    /**
     * Buat user baru dengan role admin.
     *
     * @param  array<string, mixed>  $data
     * @return array{user: User, plain_password: string}
     *
     * @throws ValidationException
     */
    public function createAdmin(array $data): array
    {
        $this->validateUnique($data);

        $plainPassword = $data['password'] ?? $this->generatePassword();

        $user = DB::transaction(function () use ($data, $plainPassword) {
            $user = $this->userRepository->createUser([
                'name' => $data['name'],
                'username' => $data['username'] ?? null,
                'email' => $data['email'],
                'password' => $plainPassword,
                'role' => 'admin',
                'must_change_password' => true,
            ]);

            $this->profileRepository->createForUser($user, [
                'tipe' => 'admin',
                'nama_penyedia_usaha' => $data['nama_penyedia_usaha'] ?? null,
                'nomor_identitas' => $data['nomor_identitas'] ?? null,
                'alamat' => $data['alamat'] ?? null,
                'telepon' => $data['telepon'] ?? null,
                'provinsi' => $data['provinsi'] ?? null,
                'kota' => $data['kota'] ?? null,
                'kode_pos' => $data['kode_pos'] ?? null,
                'logo' => $data['logo'] ?? null,
                'deskripsi' => $data['deskripsi'] ?? null,
            ]);

            return $user;
        });

        //  Notifikasi ke user baru (di luar transaksi)
        $this->notifyNewAccount($user, $plainPassword);

        Log::info('User admin baru dibuat', [
            'user_id' => $user->id,
            'email' => $user->email,
        ]);

        return [
            'user' => $user,
            'plain_password' => $plainPassword,
        ];
    }

    /**
     * Update data user (dan profile jika ada).
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $profileData
     *
     * @throws ValidationException
     */
    public function updateUser(User $user, array $data, array $profileData = []): User
    {
        $this->validateUnique($data, $user->id);

        return DB::transaction(function () use ($user, $data, $profileData) {
            $this->userRepository->updateUser($user, [
                'name' => $data['name'] ?? $user->name,
                'username' => $data['username'] ?? $user->username,
                'email' => $data['email'] ?? $user->email,
            ]);

            $user->load('profile');

            if (! empty($profileData)) {
                /** @var Profile|null $profile */
                $profile = $user->profile;

                if ($profile instanceof Profile) {
                    $this->profileRepository->updateProfile($profile, $profileData);
                } else {
                    $this->profileRepository->createForUser($user, array_merge(
                        ['tipe' => $user->role],
                        $profileData
                    ));
                }
            }

            return $user->refresh()->load('profile');
        });
    }

    /**
     * Hapus user (cascade profile).
     */
    public function deleteUser(User $user): void
    {
        DB::transaction(function () use ($user) {
            $this->userRepository->deleteUser($user);

            Log::info('User dihapus oleh admin', [
                'user_id' => $user->id,
                'email' => $user->email,
                'role' => $user->role,
                'admin_id' => auth()->id(),
            ]);
        });
    }

    /**
     * Reset password user, return password baru (plain text).
     *
     * @return array{user: User, plain_password: string}
     */
    public function resetPassword(User $user): array
    {
        $plainPassword = $this->generatePassword();

        $user->update([
            'password' => $plainPassword,
            'must_change_password' => true,
        ]);

        Log::info('Password user direset oleh admin', [
            'user_id' => $user->id,
            'email' => $user->email,
        ]);

        return [
            'user' => $user->refresh(),
            'plain_password' => $plainPassword,
        ];
    }

    /**
     * Ambil daftar user berdasarkan role (untuk admin).
     *
     * @param  array<int, string>  $roles
     * @return LengthAwarePaginator<int, User>
     */
    public function paginateByRoles(
        array $roles,
        int $perPage = 15,
        ?string $search = null,
    ): LengthAwarePaginator {
        return $this->userRepository->paginateByRoles($roles, $perPage, $search);
    }

    /**
     * Ambil daftar client (untuk admin, read-only).
     *
     * @return LengthAwarePaginator<int, User>
     */
    public function paginateClients(
        int $perPage = 15,
        ?string $search = null,
    ): LengthAwarePaginator {
        return $this->userRepository->paginateClients($perPage, $search);
    }

    // ==========================================
    // NOTIFICATION HELPERS
    // ==========================================

    /**
     * Notifikasi ke user baru bahwa akunnya sudah dibuat.
     *
     *   Password TIDAK dikirim via notifikasi (keamanan).
     * Admin harus menyampaikan password via jalur aman (WhatsApp/SMS).
     */
    private function notifyNewAccount(User $user, string $plainPassword): void
    {
        // Tentukan URL dashboard berdasarkan role
        $dashboardUrl = match ($user->role) {
            'admin' => '/admin/dashboard',
            'mitra' => '/mitra/dashboard',
            default => '/client/dashboard',
        };

        $this->notificationDispatcher->send(
            user: $user,
            tipe: 'account_created',
            judul: 'Akun Anda Telah Dibuat',
            pesan: 'Akun Anda sudah aktif. Silakan login dan segera ganti password Anda.',
            data: [
                'url' => $dashboardUrl,
            ],
        );
    }

    /**
     * Generate password acak yang aman.
     */
    protected function generatePassword(int $length = 12): string
    {
        return Str::password($length, symbols: true);
    }

    /**
     * Validasi unique email & username.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    protected function validateUnique(array $data, ?int $exceptUserId = null): void
    {
        if (isset($data['email']) && $this->userRepository->emailExists($data['email'], $exceptUserId)) {
            throw ValidationException::withMessages([
                'email' => ['Email sudah digunakan.'],
            ]);
        }

        if (! empty($data['username']) && $this->userRepository->usernameExists($data['username'], $exceptUserId)) {
            throw ValidationException::withMessages([
                'username' => ['Username sudah digunakan.'],
            ]);
        }
    }
}
