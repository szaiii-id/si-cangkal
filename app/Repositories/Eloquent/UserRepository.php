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
 * - `searchOperator()` diganti `LikeEscaper::operator()` + `LikeEscaper::contains()`
 *   untuk mencegah wildcard injection (S-06).
 * - `paginateMitras()` BARU untuk `Admin\MitraMonitoringController`.
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
    public function findById(int $id): ?User
    {
        return User::find($id);
    }

    /**
     * {@inheritDoc}
     */
    public function createClient(array $data): User
    {
        return User::create([
            'name' => $data['name'],
            'username' => $data['username'] ?? null,
            'email' => $data['email'],
            'password' => $data['password'] ?? null,
            'google_id' => $data['google_id'] ?? null,
            'role' => 'client',
            'must_change_password' => false,
            'email_verified_at' => now(),
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function createUser(array $data): User
    {
        return User::create([
            'name' => $data['name'],
            'username' => $data['username'] ?? null,
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => $data['role'],
            'must_change_password' => $data['must_change_password'] ?? true,
            'email_verified_at' => now(),
        ]);
    }

    /**
     * {@inheritDoc}
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
     * @return LengthAwarePaginator<int, User>
     */
    public function paginateByRoles(
        array $roles,
        int $perPage = 15,
        ?string $search = null,
    ): LengthAwarePaginator {
        $operator = LikeEscaper::operator(DB::connection());

        return User::query()
            ->with('profile')
            ->whereIn('role', $roles)
            ->when($search, function (Builder $query, string $search) use ($operator): void {
                $escaped = LikeEscaper::contains($search);

                $query->where(function (Builder $q) use ($escaped, $operator): void {
                    $q->where('name', $operator, $escaped)
                        ->orWhere('email', $operator, $escaped)
                        ->orWhere('username', $operator, $escaped);
                });
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
     * Perbaikan dari `Admin\MitraMonitoringController@index` yang
     * sebelumnya hardcoded `'ilike'` (tidak portabel) dan tidak
     * escape wildcard.
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
    public function emailExists(string $email, ?int $exceptUserId = null): bool
    {
        return User::where('email', $email)
            ->when($exceptUserId, fn (Builder $q) => $q->where('id', '!=', $exceptUserId))
            ->exists();
    }

    /**
     * {@inheritDoc}
     */
    public function usernameExists(string $username, ?int $exceptUserId = null): bool
    {
        return User::where('username', $username)
            ->when($exceptUserId, fn (Builder $q) => $q->where('id', '!=', $exceptUserId))
            ->exists();
    }

    /**
     * {@inheritDoc}
     */
    public function searchByRole(string $role, string $search, int $limit = 10): Collection
    {
        $operator = LikeEscaper::operator(DB::connection());
        $escaped = LikeEscaper::contains($search);

        return User::query()
            ->where('role', $role)
            ->when($search, function (Builder $query) use ($escaped, $operator): void {
                $query->where(function (Builder $q) use ($escaped, $operator): void {
                    $q->where('name', $operator, $escaped)
                        ->orWhere('email', $operator, $escaped)
                        ->orWhere('username', $operator, $escaped);
                });
            })
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'name', 'email', 'username']);
    }
}