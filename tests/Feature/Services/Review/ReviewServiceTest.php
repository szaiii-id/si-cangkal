<?php

use App\Models\Booking;
use App\Models\Package;
use App\Models\Review;
use App\Models\Trip;
use App\Models\User;
use App\Repositories\Contracts\ReviewRepositoryInterface;
use App\Services\Review\ReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(ReviewService::class);
    $this->repository = app(ReviewRepositoryInterface::class);

    $this->mitra = User::factory()->mitra()->create(['must_change_password' => false]);
    $this->client = User::factory()->client()->create(['must_change_password' => false]);
    $this->admin = User::factory()->admin()->create(['must_change_password' => false]);

    $this->package = Package::factory()->forMitra($this->mitra)->aktif()->create();
    $this->booking = Booking::factory()
        ->forPackage($this->package)
        ->forClient($this->client)
        ->confirmed()
        ->create();
});

describe('canReview', function () {
    it('false untuk role mitra dan admin', function () {
        expect($this->service->canReview($this->mitra, $this->booking))->toBeFalse()
            ->and($this->service->canReview($this->admin, $this->booking))->toBeFalse();
    });

    it('false untuk client pemilik booking yang trip-nya belum selesai', function () {
        expect($this->service->canReview($this->client, $this->booking))->toBeFalse();

        $trip = Trip::factory()->forBooking($this->booking)->active()->create();
        expect($this->service->canReview($this->client, $this->booking))->toBeFalse();

        $trip->update(['status' => 'completed']);
        // Booking menyimpan relasi trip di cache; refresh supaya service
        // melihat status terbaru (di request nyata booking selalu fresh).
        $this->booking->refresh();

        expect($this->service->canReview($this->client, $this->booking))->toBeTrue();
    });

    it('false kalau booking sudah punya review', function () {
        Trip::factory()->forBooking($this->booking)->completed()->create();
        Review::factory()->forBooking($this->booking)->create();

        expect($this->service->canReview($this->client, $this->booking))->toBeFalse();
    });

    it('false untuk booking milik client lain', function () {
        Trip::factory()->forBooking($this->booking)->completed()->create();
        $lain = User::factory()->client()->create(['must_change_password' => false]);

        expect($this->service->canReview($lain, $this->booking))->toBeFalse();
    });
});

describe('create', function () {
    it('menolak pembuatan review pada booking yang tidak memenuhi syarat', function () {
        expect(fn () => $this->service->create($this->client, $this->booking, [
            'rating' => 5,
            'komentar' => 'Perjalanan menyenangkan sekali.',
        ]))->toThrow(ValidationException::class);

        expect(Review::count())->toBe(0);
    });
});

describe('statistikForPackage', function () {
    it('mengembalikan 0/0 saat belum ada review', function () {
        expect($this->repository->statistikForPackage($this->package))->toBe([
            'rata_rata' => 0.0,
            'jumlah' => 0,
        ]);
    });

    it('mengembalikan rata-rata dan jumlah yang benar', function () {
        $booking1 = Booking::factory()->forPackage($this->package)->forClient($this->client)->confirmed()->create();
        $booking2 = Booking::factory()->forPackage($this->package)->forClient($this->client)->confirmed()->create();

        Review::factory()->forBooking($booking1)->rating(5)->create();
        Review::factory()->forBooking($booking2)->rating(4)->create();

        $statistik = $this->repository->statistikForPackage($this->package);

        expect($statistik['rata_rata'])->toBeFloat()->toBe(4.5)
            ->and($statistik['jumlah'])->toBeInt()->toBe(2);
    });

    it('hanya menghitung review paket yang diminta', function () {
        $paketLain = Package::factory()->forMitra($this->mitra)->aktif()->create();
        $bookingLain = Booking::factory()->forPackage($paketLain)->forClient($this->client)->confirmed()->create();
        Review::factory()->forBooking($bookingLain)->rating(1)->create();

        $bookingIni = Booking::factory()->forPackage($this->package)->forClient($this->client)->confirmed()->create();
        Review::factory()->forBooking($bookingIni)->rating(5)->create();

        expect($this->repository->statistikForPackage($this->package)['rata_rata'])->toBe(5.0);
    });
});
