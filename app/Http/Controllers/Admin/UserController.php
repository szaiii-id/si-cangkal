<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateUserRequest;
use App\Http\Requests\Admin\ListUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\User\UserService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class UserController extends Controller
{
    public function __construct(
        protected UserService $userService,
    ) {}

    public function index(ListUserRequest $request): InertiaResponse
    {
        $users = $this->userService->paginateByRoles(
            roles: ['admin', 'mitra'],
            perPage: (int) $request->validated('per_page', 15),
            search: $request->validated('search'),
        );

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'filters' => [
                'search' => $request->validated('search'),
                'per_page' => (int) $request->validated('per_page', 15),
            ],
        ]);
    }

    /**
     * Detail user (Mitra/Admin).
     */
    public function show(User $user): InertiaResponse
    {
        abort_if($user->role === 'client', 404);

        $user->load(['profile', 'bankAccounts']);

        return Inertia::render('Admin/Users/Show', [
            'user' => (new UserResource($user))->resolve(),
        ]);
    }

    public function create(): InertiaResponse
    {
        return Inertia::render('Admin/Users/Create');
    }

    public function store(CreateUserRequest $request): RedirectResponse
    {
        $this->userService->createAdmin($request->validated());

        return redirect()
            ->route('admin.users.index')
            ->with('status', 'User dibuat. Tautan pengaturan password telah dikirim ke emailnya.');
    }

    public function edit(User $user): InertiaResponse
    {
        abort_if($user->role === 'client', 404);

        $user->load('profile');

        return Inertia::render('Admin/Users/Edit', [
            'user' => $user,
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        abort_if($user->role === 'client', 404);

        $this->userService->updateUser(
            user: $user,
            data: $request->validated(),
            profileData: $request->validated('profile', []),
        );

        return redirect()
            ->route('admin.users.index')
            ->with('status', 'User berhasil diperbarui.');
    }

    public function destroy(User $user): RedirectResponse
    {
        abort_if($user->role === 'client', 404);
        abort_if($user->id === auth()->id(), 403, 'Anda tidak dapat menghapus akun sendiri.');

        try {
            $this->userService->deleteUser($user);

            return redirect()
                ->route('admin.users.index')
                ->with('status', 'User berhasil dihapus.');

        } catch (QueryException $e) {
            Log::error('Gagal hapus user: QueryException', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $e->getMessage(),
                'admin_id' => auth()->id(),
            ]);

            return back()->with(
                'error',
                'User tidak bisa dihapus karena masih terhubung ke data lain (misal: paket atau booking).'
            );
        }
    }

    public function resetPassword(User $user): RedirectResponse
    {
        abort_if($user->role === 'client', 404);

        $this->userService->resetPassword($user);

        return redirect()
            ->route('admin.users.index')
            ->with('status', 'Tautan reset password telah dikirim ke email user.');
    }
}
