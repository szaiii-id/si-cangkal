<?php

use App\Models\Refund;
use App\Repositories\Contracts\RefundRepositoryInterface;
use App\Services\Refund\RefundQueryService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

/*
|==========================================================================
| SETUP — Unit Test (NO DB)
|==========================================================================
|
| Service read-only: semua method delegasi ke repository. Test fokus
| ke delegation (filter/perPage diteruskan) + `findDetail` guard.
|
*/

beforeEach(function () {
    $this->refundRepo = Mockery::mock(RefundRepositoryInterface::class);
    $this->service = new RefundQueryService($this->refundRepo);
});

afterEach(function () {
    Mockery::close();
});

/*
|==========================================================================
| KATEGORI 1 — CONTRACT (`findDetail`)
|==========================================================================
*/

describe('findDetail', function () {
    it('return Refund jika ada', function () {
        $refund = Mockery::mock(Refund::class)->makePartial();
        $refund->forceFill(['id' => 1, 'status' => 'PENDING']);

        $this->refundRepo->shouldReceive('findDetailById')
            ->once()
            ->with(1)
            ->andReturn($refund);

        $result = $this->service->findDetail(1);

        expect($result)->toBe($refund);
    });

    it('throw ValidationException jika refund null', function () {
        $this->refundRepo->shouldReceive('findDetailById')
            ->once()
            ->with(999)
            ->andReturn(null);

        expect(fn () => $this->service->findDetail(999))
            ->toThrow(ValidationException::class);
    });

    it('throw ValidationException jika id = 0', function () {
        $this->refundRepo->shouldReceive('findDetailById')
            ->once()
            ->with(0)
            ->andReturn(null);

        expect(fn () => $this->service->findDetail(0))
            ->toThrow(ValidationException::class);
    });
});

/*
|==========================================================================
| KATEGORI 2 — PASSTHROUGH
|==========================================================================
*/

describe('Passthrough — paginateForAdmin', function () {
    it('meneruskan filters + perPage ke repository', function () {
        $paginator = Mockery::mock(LengthAwarePaginator::class);

        $this->refundRepo->shouldReceive('paginateForAdmin')
            ->once()
            ->with(['status' => 'PENDING', 'search' => 'budi'], 25)
            ->andReturn($paginator);

        $result = $this->service->paginateForAdmin(
            ['status' => 'PENDING', 'search' => 'budi'],
            25,
        );

        expect($result)->toBe($paginator);
    });

    it('pakai default filters = [], perPage = 15', function () {
        $paginator = Mockery::mock(LengthAwarePaginator::class);

        $this->refundRepo->shouldReceive('paginateForAdmin')
            ->once()
            ->with([], 15)
            ->andReturn($paginator);

        $this->service->paginateForAdmin();
    });
});

describe('Passthrough — paginateForMitra', function () {
    it('meneruskan mitra + filters + perPage ke repository', function () {
        $mitra = makeUser(['id' => 99, 'role' => 'mitra']);
        $paginator = Mockery::mock(LengthAwarePaginator::class);

        $this->refundRepo->shouldReceive('paginateForMitra')
            ->once()
            ->with($mitra, ['status' => 'SUCCEEDED'], 10)
            ->andReturn($paginator);

        $result = $this->service->paginateForMitra(
            $mitra,
            ['status' => 'SUCCEEDED'],
            10,
        );

        expect($result)->toBe($paginator);
    });

    it('pakai default filters = [], perPage = 15', function () {
        $mitra = makeUser(['id' => 99]);
        $paginator = Mockery::mock(LengthAwarePaginator::class);

        $this->refundRepo->shouldReceive('paginateForMitra')
            ->once()
            ->with($mitra, [], 15)
            ->andReturn($paginator);

        $this->service->paginateForMitra($mitra);
    });
});

describe('Passthrough — countByStatus', function () {
    it('meneruskan ke repository', function () {
        $this->refundRepo->shouldReceive('countByStatus')
            ->once()
            ->andReturn(['PENDING' => 5, 'SUCCEEDED' => 3, 'FAILED' => 1]);

        $result = $this->service->countByStatus();

        expect($result)->toBe([
            'PENDING' => 5,
            'SUCCEEDED' => 3,
            'FAILED' => 1,
        ]);
    });
});

describe('Passthrough — countByStatusForMitra', function () {
    it('meneruskan mitra ke repository', function () {
        $mitra = makeUser(['id' => 99]);
        $expected = ['PENDING' => 2, 'SUCCEEDED' => 1];

        $this->refundRepo->shouldReceive('countByStatusForMitra')
            ->once()
            ->with($mitra)
            ->andReturn($expected);

        $result = $this->service->countByStatusForMitra($mitra);

        expect($result)->toBe($expected);
    });
});
