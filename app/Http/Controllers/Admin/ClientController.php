<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListClientRequest;
use App\Http\Resources\UserResource;
use App\Services\User\UserService;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ClientController extends Controller
{
    public function __construct(
        protected UserService $userService,
    ) {}

    /**
     * Daftar client (read-only) dengan search & pagination.
     *
     * Catatan:
     * - `per_page` di-fallback ke 15 jika kosong/null. `validated('per_page', 15)`
     *   TIDAK aman untuk `?per_page=` (empty string) karena rule `nullable`
     *   membuat key ada dengan value null → `(int) null = 0` → `paginate(0)`
     *   yang mengembalikan 0 baris. Gunakan `?? 15`.
     * - Response dibungkus `UserResource::collection()` agar:
     *   (a) konsisten dengan kontrak Resource (field sensitif tidak bocor),
     *   (b) relasi `profile` & `bank_accounts` terserialisasi eksplisit,
     *   (c) paginator menyertakan `meta` + `links` (format Resource).
     */
    public function index(ListClientRequest $request): InertiaResponse
    {
        $perPage = (int) ($request->validated('per_page') ?? 15);

        $clients = $this->userService->paginateClients(
            perPage: $perPage,
            search: $request->validated('search'),
        );

        return Inertia::render('Admin/Clients/Index', [
            'clients' => UserResource::collection($clients),
            'filters' => [
                'search' => $request->validated('search'),
                'per_page' => $perPage,
            ],
        ]);
    }
}
