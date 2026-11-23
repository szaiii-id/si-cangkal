<?php

use App\Enums\TripStatus;
use App\Models\Booking;
use App\Models\Package;
use App\Models\Review;
use App\Models\Trip;
use App\Models\User;
use App\Repositories\Contracts\ReviewRepositoryInterface;
use App\Services\Notification\Contracts\NotificationServiceInterface;
use App\Services\Review\ReviewService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/*
|==========================================================================
| SETUP — Unit Test (NO DB)
|==========================================================================
|
| canReview() dan create() diuji murni dengan repository + notifikasi
| yang di-mock. Tidak ada RefreshDatabase di direktori tests/Unit.
|
*/

beforeEach(function () {
    $this->reviewRepo = Mockery::mock(ReviewRepositoryInterface::class);
    $this->notifikasi = Mockery::mock(NotificationServiceInterface::class);
    $this->service = new ReviewService($this->reviewRepo, $this->notifikasi);

    DB::shouldReceive('transaction')
        ->byDefault()
        ->andReturnUsing(fn ($callback) => $callback());

    $this->client = (new User)->forceFill(['id' => 7, 'role' => 'client']);
    $this->mitra = (new User)->forceFill(['id' => 9, 'role' => 'mitra', 'name' => 'Mitra Test']);

    $this->booking = (new Booking)->forceFill([
        'id' => 10,
        'user_id' => 7,
        'package_id' => 3,
    ]);
    $this->booking->setRelation('package', (new Package)->forceFill(['id' => 3, 'nama' => 'Paket Test']));
    $this->booking->setRelation('mitra', $this->mitra);
});

afterEach(function () {
    Mockery::close();
});

/**
 * Bantu pasang relasi trip tanpa menyentuh database.
 */
function pasangTrip(Booking $booking, ?TripStatus $status): void
{
    $trip = $status === null ? null : (new Trip)->forceFill(['status' => $status->value]);
    $booking->setRelation('trip', $trip);
}

/*
|==========================================================================
| KATEGORI 1 — EQUIVALENCE PARTITIONING (ROLE & OWNERSHIP)
|==========================================================================
*/

describe('Equivalence partitioning — peran & kepemilikan', function () {
    it('hanya client yang boleh review', function () {
        pasangTrip($this->booking, TripStatus::COMPLETED);
        $this->reviewRepo->shouldReceive('findByBooking')->andReturnNull();

        foreach (['admin', 'mitra'] as $role) {
            $user = (new User)->forceFill(['id' => 7, 'role' => $role]);
            expect($this->service->canReview($user, $this->booking))->toBeFalse();
        }

        expect($this->service->canReview($this->client, $this->booking))->toBeTrue();
    });

    it('client lain tidak boleh review booking ini', function () {
        pasangTrip($this->booking, TripStatus::COMPLETED);
        $this->reviewRepo->shouldReceive('findByBooking')->andReturnNull();

        $clientLain = (new User)->forceFill(['id' => 99, 'role' => 'client']);

        expect($this->service->canReview($clientLain, $this->booking))->toBeFalse();
    });
});

/*
|==========================================================================
| KATEGORI 2 — BOUNDARY VALUE ANALYSIS (STATUS TRIP & REVIEW)
|==========================================================================
*/

describe('BVA — status trip dan keberadaan review', function () {
    it('hanya trip completed yang lolos', function () {
        $this->reviewRepo->shouldReceive('findByBooking')->andReturnNull();

        foreach ([TripStatus::SCHEDULED, TripStatus::ACTIVE, TripStatus::CANCELLED] as $status) {
            pasangTrip($this->booking, $status);
            expect($this->service->canReview($this->client, $this->booking))->toBeFalse();
        }

        pasangTrip($this->booking, TripStatus::COMPLETED);
        expect($this->service->canReview($this->client, $this->booking))->toBeTrue();
    });

    it('tanpa trip dan review existing keduanya ditolak', function () {
        pasangTrip($this->booking, null);
        // Kasus tanpa trip berhenti lebih awal (short-circuit), jadi
        // repository hanya dipanggil pada kasus review existing.
        $this->reviewRepo->shouldReceive('findByBooking')
            ->once()
            ->andReturn((new Review)->forceFill(['id' => 1, 'booking_id' => 10]));

        expect($this->service->canReview($this->client, $this->booking))->toBeFalse();

        pasangTrip($this->booking, TripStatus::COMPLETED);
        expect($this->service->canReview($this->client, $this->booking))->toBeFalse();
    });
});

/*
|==========================================================================
| KATEGORI 3 — HAPPY & NEGATIVE PATH (CREATE)
|==========================================================================
*/

describe('create() — unit', function () {
    it('negative path — canReview false melempar ValidationException', function () {
        pasangTrip($this->booking, null);
        $this->reviewRepo->shouldReceive('findByBooking')->andReturnNull();
        $this->reviewRepo->shouldNotReceive('create');
        $this->notifikasi->shouldNotReceive('create');

        expect(fn () => $this->service->create($this->client, $this->booking, [
            'rating' => 5,
            'komentar' => 'Perjalanan menyenangkan.',
        ]))->toThrow(ValidationException::class);
    });

    it('happy path — data review dan notifikasi review_baru dikirim', function () {
        pasangTrip($this->booking, TripStatus::COMPLETED);
        $this->reviewRepo->shouldReceive('findByBooking')->andReturnNull();
        $this->reviewRepo->shouldReceive('create')->once()
            ->with(Mockery::on(fn (array $data) => $data === [
                'booking_id' => 10,
                'package_id' => 3,
                'user_id' => 7,
                'rating' => 4,
                'komentar' => 'Perjalanan menyenangkan.',
            ]))
            ->andReturn((new Review)->forceFill(['id' => 55]));

        $this->notifikasi->shouldReceive('create')->once()
            ->with(
                $this->mitra,
                'review_baru',
                'Review baru untuk paket Anda',
                'Paket Paket Test mendapat review 4 bintang.',
                Mockery::on(fn (array $data) => $data === [
                    'review_id' => 55,
                    'booking_id' => 10,
                    'url' => '/mitra/bookings/10',
                ]),
            );

        $review = $this->service->create($this->client, $this->booking, [
            'rating' => 4,
            'komentar' => '   Perjalanan menyenangkan.   ',
        ]);

        expect($review->id)->toBe(55);
    });
});
