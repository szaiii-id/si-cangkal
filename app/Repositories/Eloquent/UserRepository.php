<?php

namespace App\Repositories\Eloquent;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Support\LikeEscaper;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Repository user.
 *
 * Perubahan dari versi lama:
 * - searchOperator() diganti LikeEscaper::operator() + LikeEscaper::contains()
 *   untuk mencegah wildcard injection (S-06).
 * - paginateMitras() BARU untuk Admin\MitraMonitoringController.
 * - Semua query pencarian kini memakai whereRaw(... ESCAPE '\') agar
 *   karakter %, _, \ benar-benar di-escape - bukan dianggap
 *   wildcard oleh database (BUG FIX).
 * - createClient() dan createUser() TIDAK lagi otomatis menandai email
 *   terverifikasi. Gunakan parameter $verified = true hanya untuk akun
 *   yang emailnya sudah dijamin valid (Google SSO / dibuat admin).
 *   Pendaftaran manual wajib melewati alur verifikasi email.
 * - markEmailAsVerified() memakai forceFill karena kolom tersebut
 *   tidak ada di $fillable (mencegah mass-assignment).
 */
final class UserRepository implements UserRepositoryInterface
{
    /**
     * {@inheritDoc}
     */
    public function findByEmailOrUsername(string $identitas): ?User
    {
        return User::where('email', $identitas)
            ->orWhere('username', $identitas)
            ->first();
    }

    /**
     * {@inheritDoc}
     */
    public function findByGoogleId(string $googleId): ?User
    {
        return User::where('google_id', $googleId)->first();
    }

    /**
     * {@inheritDoc}
     */
    public function findByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    /**
     * {@inheritDoc}
     */
    public function findById(string $id): ?User
    {
        return User::find($id);
    }

    /**
     * {@inheritDoc}
     *
     * Catatan:
     * email_verified_at tidak ada di $fillable sehingga hanya diisi
     * lewat markEmailAsVerified() (forceFill). Ini mencegah
     * mass-assignment dari input user (celah keamanan jika user bisa
     * set field ini sendiri untuk bypass verifikasi email).
     *
     * @param  array<string, mixed>  $data
     */
    public function createClient(array $data, bool $verified = false): User
    {
        $user = User::create([
            'name' => $data['name'],
            'username' => $data['username'] ?? null,
            'email' => $data['email'],
            'password' => $data['password'] ?? null,
            'google_id' => $data['google_id'] ?? null,
            'role' => 'client',
            'must_change_password' => false,
        ]);

        if ($verified) {
            $user->markEmailAsVerified();
        }

        return $user;
    }

    /**
     * {@inheritDoc}
     *
     * Catatan: lihat createClient() - email_verified_at hanya diisi
     * via markEmailAsVerified() untuk alasan keamanan.
     *
     * @param  array<string, mixed>  $data
     */
    public function createUser(array $data, bool $verified = false): User
    {
        $user = User::create([
            'name' => $data['name'],
            'username' => $data['username'] ?? null,
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => $data['role'],
            'must_change_password' => $data['must_change_password'] ?? true,
        ]);

        if ($verified) {
            $user->markEmailAsVerified();
        }

        return $user;
    }

    /**
     * {@inheritDoc}
     */
    public function markEmailAsVerified(User $user): User
    {
        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        return $user->refresh();
    }

    /**
     * {@inheritDoc}
     *
     * @param  array<string, mixed>  $data
     */
    public function updateUser(User $user, array $data): User
    {
        $user->update($data);

        return $user->refresh();
    }

    /**
     * {@inheritDoc}
     */
    public function updatePassword(User $user, string $plainPassword): User
    {
        $user->update([
            'password' => $plainPassword,
            'must_change_password' => false,
        ]);

        return $user->refresh();
    }

    /**
     * {@inheritDoc}
     */
    public function deleteUser(User $user): bool
    {
        return (bool) $user->delete();
    }

    /**
     * {@inheritDoc}
     *
     * @param  array<int, string>  $roles
     * @return LengthAwarePaginator<int, User>
     */
    public function paginateByRoles(
        array $roles,
        int $perPage = 15,
        ?string $search = null,
    ): LengthAwarePaginator {
        return User::query()
            ->with('profile')
            ->whereIn('role', $roles)
            ->when($search, function (Builder $query, string $search): void {
                $this->applyLikeSearch($query, $search);
            })
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * {@inheritDoc}
     *
     * @return LengthAwarePaginator<int, User>
     */
    public function paginateClients(
        int $perPage = 15,
        ?string $search = null,
    ): LengthAwarePaginator {
        return $this->paginateByRoles(['client'], $perPage, $search);
    }

    /**
     * Daftar mitra (untuk panel admin).
     *
     * @return LengthAwarePaginator<int, User>
     */
    public function paginateMitras(int $perPage = 15, ?string $search = null): LengthAwarePaginator
    {
        return $this->paginateByRoles(['mitra'], $perPage, $search);
    }

    /**
     * {@inheritDoc}
     */
    public function emailExists(string $email, ?string $exceptUserId = null): bool
    {
        return User::where('email', $email)
            ->when($exceptUserId, fn (Builder $q) => $q->where('id', '!=', $exceptUserId))
            ->exists();
    }

    /**
     * {@inheritDoc}
     */
    public function usernameExists(string $username, ?string $exceptUserId = null): bool
    {
        return User::where('username', $username)
            ->when($exceptUserId, fn (Builder $q) => $q->where('id', '!=', $exceptUserId))
            ->exists();
    }

    /**
     * {@inheritDoc}
     *
     * @return Collection<int, User>
     */
    public function searchByRole(string $role, string $search, int $limit = 10): Collection
    {
        return User::query()
            ->where('role', $role)
            ->when($search, function (Builder $query, string $search): void {
                $this->applyLikeSearch($query, $search);
            })
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'name', 'email', 'username']);
    }

    /**
     * Terapkan filter LIKE ke kolom name, email, username dengan
     * escape wildcard yang benar (via whereRaw + ESCAPE).
     *
     * Mengapa harus whereRaw dan bukan where('col', 'like', $pattern)?
     * Karena where() TIDAK menambahkan klausa ESCAPE '\', sehingga
     * karakter \ di dalam pattern akan dianggap literal dan escape
     * menjadi tidak efektif - input % dan _ tetap berfungsi sebagai
     * wildcard (celah injection + bug UX).
     *
     * @param  Builder<*>  $query
     */
    private function applyLikeSearch(Builder $query, string $search): void
    {
        $operator = LikeEscaper::operator(DB::connection());
        $pattern = LikeEscaper::contains($search);
        $escape = LikeEscaper::ESCAPE_CHAR;

        /** @var literal-string $sqlName */
        $sqlName = "name {$operator} ? ESCAPE '{$escape}'";

        /** @var literal-string $sqlEmail */
        $sqlEmail = "email {$operator} ? ESCAPE '{$escape}'";

        /** @var literal-string $sqlUsername */
        $sqlUsername = "username {$operator} ? ESCAPE '{$escape}'";

        $query->where(function (Builder $q) use ($pattern, $sqlName, $sqlEmail, $sqlUsername): void {
            $q->whereRaw($sqlName, [$pattern])
                ->orWhereRaw($sqlEmail, [$pattern])
                ->orWhereRaw($sqlUsername, [$pattern]);
        });
    }
}
