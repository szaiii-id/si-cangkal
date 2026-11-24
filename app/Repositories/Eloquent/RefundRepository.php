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
 * Memakai LikeEscaper untuk mencegah wildcard injection pada
 * pencarian search (S-06).
 *
 * FIX: query pencarian memakai whereRaw(... ESCAPE '\') untuk
 * kolom utama dan relasi nested (booking, mitra, booking.client)
 * agar karakter %, _, \ benar-benar di-escape - bukan dianggap
 * wildcard oleh database.
 */
final class RefundRepository implements RefundRepositoryInterface
{
    /**
     * {@inheritDoc}
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginateForAdmin(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->buildQuery($filters, null)
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * {@inheritDoc}
     *
     * @param  array<string, mixed>  $filters
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
    public function findByIdForUpdate(int $id): ?Refund
    {
        return Refund::query()
            ->whereKey($id)
            ->lockForUpdate()
            ->first();
    }

    /**
     * {@inheritDoc}
     *
     * @return array<string, int>
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
     *
     * @return array<string, int>
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
        $escape = LikeEscaper::ESCAPE_CHAR;

        /** @var literal-string $sqlBookingKode */
        $sqlBookingKode = "kode {$operator} ? ESCAPE '{$escape}'";

        /** @var literal-string $sqlMitraName */
        $sqlMitraName = "name {$operator} ? ESCAPE '{$escape}'";

        /** @var literal-string $sqlClientName */
        $sqlClientName = "name {$operator} ? ESCAPE '{$escape}'";

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
                function (Builder $query) use ($search, $sqlBookingKode, $sqlMitraName, $sqlClientName): void {
                    $escaped = LikeEscaper::contains($search);

                    $query->where(function (Builder $q) use ($escaped, $sqlBookingKode, $sqlMitraName, $sqlClientName): void {
                        $q->whereHas('booking', function (Builder $b) use ($escaped, $sqlBookingKode): void {
                            $b->whereRaw($sqlBookingKode, [$escaped]);
                        })
                            ->orWhereHas('mitra', function (Builder $m) use ($escaped, $sqlMitraName): void {
                                $m->whereRaw($sqlMitraName, [$escaped]);
                            })
                            ->orWhereHas('booking.client', function (Builder $c) use ($escaped, $sqlClientName): void {
                                $c->whereRaw($sqlClientName, [$escaped]);
                            });
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
