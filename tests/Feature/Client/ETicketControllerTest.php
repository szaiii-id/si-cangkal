<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\ETicket;
use App\Models\Package;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/*
|==========================================================================
| SETUP — Integration Test
|==========================================================================
|
| ETicketController (client) — download E-Ticket PDF.
|
| Route (prefix /client):
| - GET /e-tickets/{uuid}/download → download
|
| Middleware: auth, password.changed, role:client, throttle:10,1
| Policy: BookingPolicy@downloadETicket (owner + status CONFIRMED/IN_PROGRESS/COMPLETED)
|
|   Catatan:
| - `Storage::fake('local')` — isolasi file system.
| - E-Ticket PDF disimpan di disk `local` (`e-tickets/`).
| - UUID pakai `Str::uuid()` (36 char) — kolom `CHAR(36)`.
| - Test fokus CONTROLLER (policy, file existence, stream response).
|   `ETicketService` sudah di-test unit (ETicketServiceTest).
|
*/

beforeEach(function () {
    if (method_exists($this, 'withoutVite')) {
        $this->withoutVite();
    }

    Storage::fake('local');

    $this->client = User::factory()->client()->create([
        'must_change_password' => false,
    ]);

    $this->client2 = User::factory()->client()->create([
        'must_change_password' => false,
    ]);

    $this->mitra = User::factory()->mitra()->create([
        'must_change_password' => false,
    ]);
});

/*
|==========================================================================
| HELPER — buat booking + e-ticket
|==========================================================================
*/
function makeBookingWithETicket(
    User $client,
    User $mitra,
    array $bookingOverrides = [],
    array $eticketOverrides = [],
    ?string $pdfContent = null,
): array {
    $package = Package::factory()->forMitra($mitra)->aktif()->create();
    $booking = Booking::factory()
        ->forPackage($package)
        ->forClient($client)
        ->create(array_merge([
            'mitra_id' => $mitra->id,
            'status' => BookingStatus::CONFIRMED,
        ], $bookingOverrides));

    $trip = Trip::factory()->forBooking($booking)->create([
        'mitra_id' => $mitra->id,
        'kode' => 'TRP-2026-001',
    ]);

    $uuid = (string) Str::uuid();
    $pdfPath = 'e-tickets/ticket-'.$uuid.'.pdf';

    $eticket = ETicket::factory()->create(array_merge([
        'booking_id' => $booking->id,
        'trip_id' => $trip->id,
        'uuid' => $uuid,
        'pdf_path' => $pdfPath,
        'qr_code' => 'e-tickets/qr-'.$uuid.'.png',
        'generated_at' => now(),
    ], $eticketOverrides));

    if ($pdfContent !== null) {
        Storage::disk('local')->put($pdfPath, $pdfContent);
    }

    return [$booking, $trip, $eticket, $pdfPath];
}

/*
|==========================================================================
| KATEGORI 1 — CONTRACT / API SCHEMA
|==========================================================================
*/

describe('Contract — download', function () {
    it('GET /client/e-tickets/{uuid}/download valid → stream PDF', function () {
        [$booking, $trip, $eticket, $pdfPath] = makeBookingWithETicket(
            $this->client, $this->mitra, [], [], 'PDF-CONTENT',
        );

        $response = $this->actingAs($this->client)
            ->get("/client/e-tickets/{$eticket->uuid}/download");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $response->assertHeader(
            'Content-Disposition',
            'attachment; filename=e-ticket-TRP-2026-001.pdf'
        );
    });

    it('download filename pakai trip.kode', function () {
        [$booking, $trip, $eticket, $pdfPath] = makeBookingWithETicket(
            $this->client, $this->mitra, [], [], 'PDF-CONTENT',
        );

        $trip->update(['kode' => 'TRP-KHUSUS-999']);

        $response = $this->actingAs($this->client)
            ->get("/client/e-tickets/{$eticket->uuid}/download");

        $response->assertOk();
        $response->assertHeader(
            'Content-Disposition',
            'attachment; filename=e-ticket-TRP-KHUSUS-999.pdf'
        );
    });

    it('route name = client.e-tickets.download', function () {
        expect(route('client.e-tickets.download', 'test-uuid'))
            ->toBe(url('/client/e-tickets/test-uuid/download'));
    });
});

/*
|==========================================================================
| KATEGORI 2 — SECURITY & AUTHORIZATION
|==========================================================================
*/

describe('Security — guest & role', function () {
    it('guest di-redirect ke login', function () {
        $uuid = (string) Str::uuid();

        $this->get("/client/e-tickets/{$uuid}/download")
            ->assertRedirect(route('login'));
    });

    it('admin di-redirect ke admin.dashboard', function () {
        $admin = User::factory()->admin()->create(['must_change_password' => false]);
        $uuid = (string) Str::uuid();

        $this->actingAs($admin)
            ->get("/client/e-tickets/{$uuid}/download")
            ->assertRedirect(route('admin.dashboard'));
    });

    it('mitra di-redirect ke mitra.dashboard', function () {
        $uuid = (string) Str::uuid();

        $this->actingAs($this->mitra)
            ->get("/client/e-tickets/{$uuid}/download")
            ->assertRedirect(route('mitra.dashboard'));
    });

    it('client must_change_password=true di-redirect ke password.change', function () {
        $needChange = User::factory()->client()->create(['must_change_password' => true]);
        $uuid = (string) Str::uuid();

        $this->actingAs($needChange)
            ->get("/client/e-tickets/{$uuid}/download")
            ->assertRedirect(route('password.change'));
    });
});

describe('Security — ownership (Policy downloadETicket)', function () {
    it('client TIDAK bisa download e-ticket client lain → 403', function () {
        [$booking, $trip, $eticket, $pdfPath] = makeBookingWithETicket(
            $this->client2, $this->mitra, [], [], 'PDF-CONTENT',
        );

        $this->actingAs($this->client)
            ->get("/client/e-tickets/{$eticket->uuid}/download")
            ->assertForbidden();
    });
});

describe('Security — policy status guard', function () {
    it('download booking PENDING_PAYMENT → 403', function () {
        [$booking, $trip, $eticket, $pdfPath] = makeBookingWithETicket(
            $this->client, $this->mitra, ['status' => BookingStatus::PENDING_PAYMENT], [], 'PDF-CONTENT',
        );

        $this->actingAs($this->client)
            ->get("/client/e-tickets/{$eticket->uuid}/download")
            ->assertForbidden();
    });

    it('download booking CANCELLED → 403', function () {
        [$booking, $trip, $eticket, $pdfPath] = makeBookingWithETicket(
            $this->client, $this->mitra, ['status' => BookingStatus::CANCELLED], [], 'PDF-CONTENT',
        );

        $this->actingAs($this->client)
            ->get("/client/e-tickets/{$eticket->uuid}/download")
            ->assertForbidden();
    });

    it('download booking CONFIRMED → OK', function () {
        [$booking, $trip, $eticket, $pdfPath] = makeBookingWithETicket(
            $this->client, $this->mitra, ['status' => BookingStatus::CONFIRMED], [], 'PDF-CONTENT',
        );

        $this->actingAs($this->client)
            ->get("/client/e-tickets/{$eticket->uuid}/download")
            ->assertOk();
    });

    it('download booking IN_PROGRESS → OK', function () {
        [$booking, $trip, $eticket, $pdfPath] = makeBookingWithETicket(
            $this->client, $this->mitra, ['status' => BookingStatus::IN_PROGRESS], [], 'PDF-CONTENT',
        );

        $this->actingAs($this->client)
            ->get("/client/e-tickets/{$eticket->uuid}/download")
            ->assertOk();
    });

    it('download booking COMPLETED → OK', function () {
        [$booking, $trip, $eticket, $pdfPath] = makeBookingWithETicket(
            $this->client, $this->mitra, ['status' => BookingStatus::COMPLETED], [], 'PDF-CONTENT',
        );

        $this->actingAs($this->client)
            ->get("/client/e-tickets/{$eticket->uuid}/download")
            ->assertOk();
    });
});

/*
|==========================================================================
| KATEGORI 3 — CONCURRENCY
|==========================================================================
*/

describe('Concurrency — download 2x', function () {
    it('download 2x → sukses 2x', function () {
        [$booking, $trip, $eticket, $pdfPath] = makeBookingWithETicket(
            $this->client, $this->mitra, [], [], 'PDF-CONTENT',
        );

        $this->actingAs($this->client)
            ->get("/client/e-tickets/{$eticket->uuid}/download")
            ->assertOk();

        $this->actingAs($this->client)
            ->get("/client/e-tickets/{$eticket->uuid}/download")
            ->assertOk();
    });
});

/*
|==========================================================================
| KATEGORI 4 — IDEMPOTENCY
|==========================================================================
*/

describe('Idempotency', function () {
    it('download berulang tidak mengubah data', function () {
        [$booking, $trip, $eticket, $pdfPath] = makeBookingWithETicket(
            $this->client, $this->mitra, [], [], 'PDF-CONTENT',
        );

        $countBefore = ETicket::count();

        $this->actingAs($this->client)
            ->get("/client/e-tickets/{$eticket->uuid}/download");
        $this->actingAs($this->client)
            ->get("/client/e-tickets/{$eticket->uuid}/download");

        expect(ETicket::count())->toBe($countBefore);
    });
});

/*
|==========================================================================
| KATEGORI 5 — DATA INTEGRITY
|==========================================================================
*/

describe('Data Integrity — stream content', function () {
    it('download stream mengembalikan konten PDF', function () {
        [$booking, $trip, $eticket, $pdfPath] = makeBookingWithETicket(
            $this->client, $this->mitra, [], [], 'PDF-CONTENT-TEST',
        );

        $response = $this->actingAs($this->client)
            ->get("/client/e-tickets/{$eticket->uuid}/download");

        $response->assertOk();

        // Ambil content dari StreamedResponse
        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        expect($content)->toBe('PDF-CONTENT-TEST');
    });

    it('download tidak mengubah pdf_path di DB', function () {
        [$booking, $trip, $eticket, $pdfPath] = makeBookingWithETicket(
            $this->client, $this->mitra, [], [], 'PDF-CONTENT',
        );

        $eticket->refresh();
        $pdfPathBefore = $eticket->pdf_path;

        $this->actingAs($this->client)
            ->get("/client/e-tickets/{$eticket->uuid}/download");

        $eticket->refresh();
        expect($eticket->pdf_path)->toBe($pdfPathBefore);
    });
});

/*
|==========================================================================
| KATEGORI 6 — ERROR HANDLING
|==========================================================================
*/

describe('Error Handling — 404', function () {
    it('uuid tidak ada → 404', function () {
        $uuid = (string) Str::uuid();

        $this->actingAs($this->client)
            ->get("/client/e-tickets/{$uuid}/download")
            ->assertNotFound();
    });

    it('uuid invalid format → 404', function () {
        $this->actingAs($this->client)
            ->get('/client/e-tickets/uuid-ngawur/download')
            ->assertNotFound();
    });

    it('file PDF tidak ada di storage → 404', function () {
        [$booking, $trip, $eticket, $pdfPath] = makeBookingWithETicket(
            $this->client, $this->mitra, [], [], null,  // ← tidak put file
        );

        $this->actingAs($this->client)
            ->get("/client/e-tickets/{$eticket->uuid}/download")
            ->assertNotFound();
    });

    it('booking deleted (soft) → 404', function () {
        [$booking, $trip, $eticket, $pdfPath] = makeBookingWithETicket(
            $this->client, $this->mitra, [], [], 'PDF-CONTENT',
        );

        $booking->delete();  // soft delete

        $this->actingAs($this->client)
            ->get("/client/e-tickets/{$eticket->uuid}/download")
            ->assertNotFound();
    });
});
