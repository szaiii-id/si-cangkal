<?php

use App\Enums\BookingStatus;
use App\Enums\TripStatus;
use App\Models\Booking;
use App\Models\ETicket;
use App\Models\Package;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/*
|==========================================================================
| VERIFIKASI E-TICKET PUBLIK (HASIL SCAN QR)
|==========================================================================
|
| Halaman sengaja Blade polos: tidak boleh memuat email, nomor HP,
| daftar peserta, atau tautan PDF privat.
|
*/

beforeEach(function () {
    $this->mitra = User::factory()->mitra()->create([
        'must_change_password' => false,
    ]);

    $this->client = User::factory()->client()->create([
        'name' => 'Budi Santoso',
        'email' => 'budi.rahasia@example.test',
        'must_change_password' => false,
    ]);
});

function makeVerifiedTicket(User $client, User $mitra, array $overrides = []): ETicket
{
    $package = Package::factory()->forMitra($mitra)->aktif()->create([
        'nama' => 'Paket Verifikasi QR',
    ]);

    $booking = Booking::factory()
        ->forPackage($package)
        ->forClient($client)
        ->create(array_merge([
            'mitra_id' => $mitra->id,
            'status' => BookingStatus::CONFIRMED,
        ], $overrides['booking'] ?? []));

    $trip = Trip::factory()
        ->forBooking($booking)
        ->create(array_merge([
            'mitra_id' => $mitra->id,
            'package_id' => $package->id,
            'status' => TripStatus::ACTIVE,
            'snapshot_package' => [
                'nama' => 'Paket Verifikasi QR',
                'durasi_hari' => 2,
            ],
        ], $overrides['trip'] ?? []));

    return ETicket::factory()->create([
        'booking_id' => $booking->id,
        'trip_id' => $trip->id,
    ]);
}

describe('halaman verifikasi', function () {
    it('UUID valid → 200 dengan data aman', function () {
        $ticket = makeVerifiedTicket($this->client, $this->mitra);

        $response = $this->get(route('e-tickets.verify', ['uuid' => $ticket->uuid]));

        $response->assertOk()
            ->assertSee('E-Ticket Berlaku')
            ->assertSee('Paket Verifikasi QR')
            ->assertSee($ticket->booking->kode)
            ->assertSee($ticket->trip->kode)
            ->assertSee('Budi S.')
            ->assertDontSee('budi.rahasia@example.test')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')
            ->assertHeader('Referrer-Policy', 'no-referrer');

        // Header Cache-Control dimutakhirkan middleware Laravel; yang penting
        // ada directive no-store.
        expect($response->headers->get('Cache-Control'))->toContain('no-store');
    });

    it('UUID tidak ditemukan → 404', function () {
        $this->get(route('e-tickets.verify', ['uuid' => (string) Str::uuid()]))
            ->assertNotFound();
    });

    it('UUID bukan format UUID → 404 (route constraint)', function () {
        $this->get('/e-tickets/bukan-uuid')->assertNotFound();
    });

    it('booking dibatalkan → tiket ditandai tidak berlaku', function () {
        $ticket = makeVerifiedTicket($this->client, $this->mitra, [
            'booking' => ['status' => BookingStatus::CANCELLED],
        ]);

        $this->get(route('e-tickets.verify', ['uuid' => $ticket->uuid]))
            ->assertOk()
            ->assertSee('E-Ticket Tidak Berlaku');
    });

    it('trip dibatalkan → tiket ditandai tidak berlaku', function () {
        $ticket = makeVerifiedTicket($this->client, $this->mitra, [
            'trip' => ['status' => TripStatus::CANCELLED],
        ]);

        $this->get(route('e-tickets.verify', ['uuid' => $ticket->uuid]))
            ->assertOk()
            ->assertSee('E-Ticket Tidak Berlaku');
    });

    it('halaman tidak memuat daftar peserta maupun tautan PDF', function () {
        $ticket = makeVerifiedTicket($this->client, $this->mitra);

        $html = $this->get(route('e-tickets.verify', ['uuid' => $ticket->uuid]))
            ->assertOk()
            ->getContent();

        expect($html)
            ->not->toContain($ticket->pdf_path)
            ->not->toContain('/client/e-tickets')
            ->not->toContain('Peserta Trip Satu')
            ->not->toContain('Peserta Trip Dua');
    });
});
