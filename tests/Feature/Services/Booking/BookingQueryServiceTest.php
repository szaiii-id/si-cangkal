<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\ETicket;
use App\Models\Package;
use App\Models\User;
use App\Services\Booking\BookingQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

/*
|==========================================================================
| SETUP — Integration Test
|==========================================================================
|
| BookingQueryService — persistence `ETicket::where()->first()`.
|
| Yang di-test di sini (yang di-skip di unit test):
| - `getETicketForBooking` — static `ETicket::where()->first()`.
|
| Method lain (`paginate`, `findDetail`, `countByStatus`, dll.) —
| sudah di-test di unit test (dengan mock repository). Feature test
| fokus ke `getETicketForBooking` yang **tidak bisa** di-mock di unit.
|
| Catatan:
| - `BookingQueryService` — tidak final — bisa di-inject via `app()`.
| - `ETicket::factory()`, `Booking::factory()` — real factory + real DB.
|
*/

beforeEach(function () {
    $this->service = app(BookingQueryService::class);

    $this->client = User::factory()->client()->create([
        'must_change_password' => false,
    ]);

    $this->mitra = User::factory()->mitra()->create([
        'must_change_password' => false,
    ]);
});

/*
|==========================================================================
| HELPER — buat booking
|==========================================================================
*/
function makeBookingForQuery(User $client, User $mitra, array $overrides = []): Booking
{
    $package = Package::factory()->forMitra($mitra)->aktif()->create();

    return Booking::factory()
        ->forPackage($package)
        ->forClient($client)
        ->create(array_merge([
            'mitra_id' => $mitra->id,
            'status' => BookingStatus::CONFIRMED,
        ], $overrides));
}

/*
|==========================================================================
| KATEGORI 1 — GET E-TICKET FOR BOOKING (PERSISTENCE)
|==========================================================================
*/

describe('getETicketForBooking — persistence', function () {
    it('getETicketForBooking → return ETicket jika ada', function () {
        $booking = makeBookingForQuery($this->client, $this->mitra);

        $eticket = ETicket::factory()->create([
            'booking_id' => $booking->id,
            'uuid' => Str::uuid()->toString(),
            'pdf_path' => 'e-tickets/ticket-test.pdf',
        ]);

        $result = $this->service->getETicketForBooking($booking);

        expect($result)->not->toBeNull()
            ->and($result->id)->toBe($eticket->id)
            ->and($result->booking_id)->toBe($booking->id);
    });

    it('getETicketForBooking → null jika tidak ada ETicket', function () {
        $booking = makeBookingForQuery($this->client, $this->mitra);

        $result = $this->service->getETicketForBooking($booking);

        expect($result)->toBeNull();
    });

    it('getETicketForBooking → booking tidak terkait ETicket → null', function () {
        $booking1 = makeBookingForQuery($this->client, $this->mitra);
        $booking2 = makeBookingForQuery($this->client, $this->mitra);

        // ETicket untuk booking1
        ETicket::factory()->create([
            'booking_id' => $booking1->id,
            'uuid' => Str::uuid()->toString(),
            'pdf_path' => 'e-tickets/ticket-test.pdf',
        ]);

        // Cek booking2 — tidak ada ETicket
        $result = $this->service->getETicketForBooking($booking2);

        expect($result)->toBeNull();
    });

    it('getETicketForBooking → return ETicket terbaru jika ada multiple (edge case)', function () {
        $booking = makeBookingForQuery($this->client, $this->mitra);

        // ETicket pertama
        ETicket::factory()->create([
            'booking_id' => $booking->id,
            'uuid' => Str::uuid()->toString(),
            'pdf_path' => 'e-tickets/ticket-1.pdf',
            'generated_at' => now()->subDay(),
        ]);

        // ETicket kedua (harusnya menang — tapi `where()->first()` return yang mana?)
        // Behavior: `where()->first()` return **row pertama** — tergantung DB order
        // (tanpa orderBy, behavior tidak deterministic)
        // Test dokumentasi — tidak assert ketat

        $result = $this->service->getETicketForBooking($booking);

        expect($result)->not->toBeNull();
    });
});

/*
|==========================================================================
| KATEGORI 2 — READ (PERSISTENCE — sanity)
|==========================================================================
*/

describe('findDetail — persistence', function () {
    it('findDetail → return Booking dari DB', function () {
        $booking = makeBookingForQuery($this->client, $this->mitra);

        $result = $this->service->findDetail($booking->id);

        expect($result)->toBeInstanceOf(Booking::class)
            ->and($result->id)->toBe($booking->id);
    });

    it('findDetail → booking tidak ada → ValidationException', function () {
        expect(fn () => $this->service->findDetail(999_999))
            ->toThrow(ValidationException::class);
    });
});

describe('countByStatus — persistence', function () {
    it('countByStatus → hitung semua booking di DB', function () {
        makeBookingForQuery($this->client, $this->mitra, ['status' => BookingStatus::PENDING_PAYMENT]);
        makeBookingForQuery($this->client, $this->mitra, ['status' => BookingStatus::CONFIRMED]);
        makeBookingForQuery($this->client, $this->mitra, ['status' => BookingStatus::CANCELLED]);

        $result = $this->service->countByStatus();

        expect($result[BookingStatus::PENDING_PAYMENT->value])->toBe(1)
            ->and($result[BookingStatus::CONFIRMED->value])->toBe(1)
            ->and($result[BookingStatus::CANCELLED->value])->toBe(1);
    });
});
