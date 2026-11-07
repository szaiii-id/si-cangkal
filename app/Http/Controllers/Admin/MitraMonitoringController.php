<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Controller monitoring mitra untuk admin (read-only).
 *
 * Perubahan dari versi lama:
 * - Query inline (dengan hardcoded `'ilike'`) -> `UserRepository::paginateMitras()`
 *   + `LikeEscaper` (S-06, portabilitas MySQL/SQLite).
 * - Otorisasi eksplisit `$this->authorize('viewAny', User::class)`.
 */
final class MitraMonitoringController extends Controller
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
    ) {}

    public function index(Request $request): InertiaResponse
    {
        $this->authorize('viewAny', User::class);

        $search = $request->input('search');
        $perPage = (int) $request->input('per_page', 15);

        return Inertia::render('Admin/Mitras/Index', [
            'mitras' => $this->userRepository->paginateMitras($perPage, $search),
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
            ],
        ]);
    }
}