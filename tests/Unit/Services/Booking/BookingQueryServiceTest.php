<?php

use App\Models\Booking;
use App\Repositories\Contracts\BookingRepositoryInterface;
use App\Services\Booking\BookingQueryService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

/*
|==========================================================================
| SETUP — Unit Test (NO DB)
|==========================================================================
|
| Catatan:
| - Method `getETicketForBooking` panggil static `ETicket::where()` —
|   butuh DB. Di-skip di unit test (verifikasi di Feature test).
|
*/

beforeEach(function () {
    $this->bookingRepo = Mockery::mock(BookingRepositoryInterface::class);
    $this->service = new BookingQueryService($this->bookingRepo);
});

afterEach(function () {
    Mockery::close();
});

/*
|==========================================================================
| KATEGORI 1 — HAPPY & NEGATIVE PATH (`findDetail`)
|==========================================================================
*/

describe('findDetail', function () {
    it('return Booking jika ada', function () {
        $booking = makeBookingMock(['id' => 1, 'kode' => 'BKG-2026-001']);

        $this->bookingRepo->shouldReceive('findById')
            ->once()
            ->with(1)
            ->andReturn($booking);

        $result = $this->service->findDetail(1);

        expect($result)->toBe($booking);
    });

    it('throw ValidationException jika booking null', function () {
        $this->bookingRepo->shouldReceive('findById')
            ->once()
            ->with(999)
            ->andReturn(null);

        expect(fn () => $this->service->findDetail(999))
            ->toThrow(ValidationException::class);
    });

    it('throw ValidationException jika booking ID tidak valid', function () {
        $this->bookingRepo->shouldReceive('findById')
            ->once()
            ->with(0)
            ->andReturn(null);

        expect(fn () => $this->service->findDetail(0))
            ->toThrow(ValidationException::class);
    });
});

/*
|==========================================================================
| KATEGORI 2 — PASSTHROUGH (repository delegation)
|==========================================================================
*/

describe('Passthrough — paginate', function () {
    it('paginate meneruskan filters + perPage ke repository', function () {
        $paginator = Mockery::mock(LengthAwarePaginator::class);

        $this->bookingRepo->shouldReceive('paginate')
            ->once()
            ->with(['search' => 'budi', 'status' => 'confirmed'], 25)
            ->andReturn($paginator);

        $result = $this->service->paginate(
            ['search' => 'budi', 'status' => 'confirmed'],
            25,
        );

        expect($result)->toBe($paginator);
    });

    it('paginate pakai default perPage = 15, filters = []', function () {
        $paginator = Mockery::mock(LengthAwarePaginator::class);

        $this->bookingRepo->shouldReceive('paginate')
            ->once()
            ->with([], 15)
            ->andReturn($paginator);

        $this->service->paginate();
    });
});

describe('Passthrough — paginateForClient', function () {
    it('paginateForClient meneruskan client + filters + perPage', function () {
        $client = makeUser(['id' => 10, 'role' => 'client']);
        $paginator = Mockery::mock(LengthAwarePaginator::class);

        $this->bookingRepo->shouldReceive('paginateForClient')
            ->once()
            ->with($client, ['status' => 'pending_payment'], 10)
            ->andReturn($paginator);

        $result = $this->service->paginateForClient(
            $client,
            ['status' => 'pending_payment'],
            10,
        );

        expect($result)->toBe($paginator);
    });

    it('paginateForClient pakai default perPage = 15, filters = []', function () {
        $client = makeUser(['id' => 10]);
        $paginator = Mockery::mock(LengthAwarePaginator::class);

        $this->bookingRepo->shouldReceive('paginateForClient')
            ->once()
            ->with($client, [], 15)
            ->andReturn($paginator);

        $this->service->paginateForClient($client);
    });
});

describe('Passthrough — countByStatus', function () {
    it('countByStatus meneruskan ke repository', function () {
        $this->bookingRepo->shouldReceive('countByStatus')
            ->once()
            ->andReturn([
                'pending_payment' => 5,
                'confirmed' => 3,
                'completed' => 10,
            ]);

        $result = $this->service->countByStatus();

        expect($result)->toBe([
            'pending_payment' => 5,
            'confirmed' => 3,
            'completed' => 10,
        ]);
    });
});

describe('Passthrough — countByStatusForClient', function () {
    it('countByStatusForClient meneruskan client ke repository', function () {
        $client = makeUser(['id' => 10]);
        $expected = ['pending_payment' => 2, 'confirmed' => 1];

        $this->bookingRepo->shouldReceive('countByStatusForClient')
            ->once()
            ->with($client)
            ->andReturn($expected);

        $result = $this->service->countByStatusForClient($client);

        expect($result)->toBe($expected);
    });
});

describe('Passthrough — countByStatusForMitra', function () {
    it('countByStatusForMitra meneruskan mitra ke repository', function () {
        $mitra = makeUser(['id' => 99, 'role' => 'mitra']);
        $expected = ['active' => 4, 'completed' => 8];

        $this->bookingRepo->shouldReceive('countByStatusForMitra')
            ->once()
            ->with($mitra)
            ->andReturn($expected);

        $result = $this->service->countByStatusForMitra($mitra);

        expect($result)->toBe($expected);
    });
});

/*
|==========================================================================
| CATATAN: `getETicketForBooking` — Butuh Feature test
|==========================================================================
|
| Method `getETicketForBooking` panggil static `ETicket::where()` yang
| butuh DB. Unit test tidak bisa verifikasi tanpa alias mock (kompleks).
|
| Verifikasi di Feature test:
|
|   it('getETicketForBooking → return ETicket jika ada', function () {
|       $booking = Booking::factory()->create();
|       $eticket = ETicket::factory()->create(['booking_id' => $booking->id]);
|
|       $service = new BookingQueryService(app(BookingRepositoryInterface::class));
|       $result = $service->getETicketForBooking($booking);
|
|       expect($result->id)->toBe($eticket->id);
|   });
|
|   it('getETicketForBooking → null jika tidak ada', function () {
|       $booking = Booking::factory()->create();
|
|       $service = new BookingQueryService(app(BookingRepositoryInterface::class));
|       $result = $service->getETicketForBooking($booking);
|
|       expect($result)->toBeNull();
|   });
|
*/
