<?php

namespace App\Repositories\Eloquent;

use App\Enums\RefundStatus;
use App\Models\Refund;
use App\Models\User;
use App\Repositories\Contracts\RefundRepositoryInterface;
use App\Support\LikeEscaper;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Repository refund.
 *
 * Memakai `LikeEscaper` untuk mencegah wildcard injection pada
 * pencarian `search` (S-06).
 */
final class RefundRepository implements RefundRepositoryInterface
{
    /**
     * {@inheritDoc}
     */
    public function paginateForAdmin(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->buildQuery($filters, null)
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * {@inheritDoc}
     */
    public function paginateForMitra(
        User $mitra,
        array $filters = [],
        int $perPage = 15,
    ): LengthAwarePaginator {
        return $this->buildQuery($filters, $mitra->id)
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * {@inheritDoc}
     */
    public function findDetailById(int $id): ?Refund
    {
        return Refund::with([
            'booking:id,kode,total_harga,status',
            'booking.client:id,name,email',
            'mitra:id,name,email',
            'mitraInitiator:id,name,email',
        ])->find($id);
    }

    /**
     * {@inheritDoc}
     */
    public function countByStatus(): array
    {
        $counts = Refund::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        return $this->normalizeCounts($counts);
    }

    /**
     * {@inheritDoc}
     */
    public function countByStatusForMitra(User $mitra): array
    {
        $counts = Refund::query()
            ->where('mitra_id', $mitra->id)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        return $this->normalizeCounts($counts);
    }

    // ==========================================
    // HELPERS
    // ==========================================

    /**
     * Base query untuk refund dengan filter search & status.
     *
     * @param  array{search?: string|null, status?: string|null}  $filters
     * @param  int|null  $mitraId  Null = admin (semua refund).
     * @return Builder<Refund>
     */
    private function buildQuery(array $filters, ?int $mitraId): Builder
    {
        $search = $filters['search'] ?? null;
        $status = $filters['status'] ?? null;

        $operator = LikeEscaper::operator(DB::connection());

        return Refund::query()
            ->with([
                'booking:id,kode,user_id,total_harga,status',
                'booking.client:id,name,email',
                'mitra:id,name,email',
                'mitraInitiator:id,name,email',
            ])
            ->when($mitraId !== null, fn (Builder $q) => $q->where('mitra_id', $mitraId))
            ->when(
                $search !== null && $search !== '',
                function (Builder $query) use ($search, $operator): void {
                    $escaped = LikeEscaper::contains($search);

                    $query->where(function (Builder $q) use ($escaped, $operator): void {
                        $q->whereHas('booking', fn (Builder $b) => $b->where('kode', $operator, $escaped))
                            ->orWhereHas('mitra', fn (Builder $m) => $m->where('name', $operator, $escaped))
                            ->orWhereHas('booking.client', fn (Builder $c) => $c->where('name', $operator, $escaped));
                    });
                },
            )
            ->when($status, fn (Builder $q, string $s) => $q->where('status', $s))
            ->orderByDesc('created_at');
    }

    /**
     * Normalisasi count per status agar semua enum value muncul (0 jika kosong).
     *
     * @param  array<string, int|string>  $counts
     * @return array<string, int>
     */
    private function normalizeCounts(array $counts): array
    {
        $result = [];

        foreach (RefundStatus::cases() as $case) {
            $result[$case->value] = (int) ($counts[$case->value] ?? 0);
        }

        return $result;
    }
}