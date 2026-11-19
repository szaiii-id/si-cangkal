<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\ETicket;
use App\Models\Package;
use App\Models\Trip;
use App\Models\User;
use App\Services\ETicket\ETicketService;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Psr\Log\LoggerInterface;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

uses(RefreshDatabase::class);

/*
|==========================================================================
| SETUP — Integration Test
|==========================================================================
|
| ETicketService — persistence `ETicket::updateOrCreate` +
| `findByUuid` + QrCode/PDF fake.
|
|   Catatan:
| - `QrCode::generate` & `Pdf::loadView` di-mock via facade.
| - `Log::channel('audit')` di-mock — `AuditLogger::writer()`.
| - `Log::info` di-mock — `ETicketService` panggil langsung.
| - `Storage::fake('local')` — isolasi file system.
|
*/

beforeEach(function () {
    Storage::fake('local');

    // Mock Log::channel('audit') — AuditLogger::writer()
    $auditLoggerMock = Mockery::mock(LoggerInterface::class);
    $auditLoggerMock->shouldReceive('info')->byDefault()->andReturnNull();

    Log::shouldReceive('channel')->with('audit')->byDefault()->andReturn($auditLoggerMock);
    Log::shouldReceive('driver')->byDefault()->andReturn($auditLoggerMock);

    // Mock Log::info — ETicketService panggil Log::info langsung
    Log::shouldReceive('info')->byDefault()->andReturnNull();

    $this->service = new ETicketService;

    $this->mitra = User::factory()->mitra()->create([
        'must_change_password' => false,
    ]);

    $this->client = User::factory()->client()->create([
        'must_change_password' => false,
    ]);
});

/*
|==========================================================================
| HELPER — buat trip lengkap dengan booking
|==========================================================================
*/
function makeTripForETicket(User $client, User $mitra, array $overrides = []): Trip
{
    $package = Package::factory()->forMitra($mitra)->aktif()->create();
    $booking = Booking::factory()
        ->forPackage($package)
        ->forClient($client)
        ->create(array_merge([
            'mitra_id' => $mitra->id,
            'status' => BookingStatus::CONFIRMED,
        ], $overrides));

    return Trip::factory()->forBooking($booking)->create([
        'mitra_id' => $mitra->id,
        'kode' => 'TRP-2026-001',
    ]);
}

/*
|==========================================================================
| KATEGORI 1 — GENERATE FOR TRIP (PERSISTENCE)
|==========================================================================
*/

describe('generateForTrip — persistence', function () {
    it('generateForTrip → ETicket tersimpan di DB', function () {
        $trip = makeTripForETicket($this->client, $this->mitra);

        QrCode::shouldReceive('format->size->generate')->andReturn('QR-CONTENT');

        $pdfMock = Mockery::mock(PdfService::class);
        $pdfMock->shouldReceive('setPaper')->andReturnSelf();
        $pdfMock->shouldReceive('output')->andReturn('PDF-CONTENT');

        Pdf::shouldReceive('loadView')->andReturn($pdfMock);

        $ticket = $this->service->generateForTrip($trip);

        $this->assertDatabaseHas('e_tickets', [
            'id' => $ticket->id,
            'booking_id' => $trip->booking_id,
            'trip_id' => $trip->id,
        ]);
    });

    it('generateForTrip → QR & PDF file tersimpan di disk local', function () {
        $trip = makeTripForETicket($this->client, $this->mitra);

        QrCode::shouldReceive('format->size->generate')->andReturn('QR-CONTENT');

        $pdfMock = Mockery::mock(PdfService::class);
        $pdfMock->shouldReceive('setPaper')->andReturnSelf();
        $pdfMock->shouldReceive('output')->andReturn('PDF-CONTENT');

        Pdf::shouldReceive('loadView')->andReturn($pdfMock);

        $ticket = $this->service->generateForTrip($trip);

        expect($ticket->qr_code)->toStartWith('e-tickets/qr-')
            ->and($ticket->qr_code)->toEndWith('.png');
        Storage::disk('local')->assertExists($ticket->qr_code);

        expect($ticket->pdf_path)->toStartWith('e-tickets/ticket-')
            ->and($ticket->pdf_path)->toEndWith('.pdf');
        Storage::disk('local')->assertExists($ticket->pdf_path);
    });

    it('generateForTrip → uuid + generated_at terisi', function () {
        $trip = makeTripForETicket($this->client, $this->mitra);

        QrCode::shouldReceive('format->size->generate')->andReturn('QR-CONTENT');

        $pdfMock = Mockery::mock(PdfService::class);
        $pdfMock->shouldReceive('setPaper')->andReturnSelf();
        $pdfMock->shouldReceive('output')->andReturn('PDF-CONTENT');

        Pdf::shouldReceive('loadView')->andReturn($pdfMock);

        $ticket = $this->service->generateForTrip($trip);

        expect($ticket->uuid)->toBeString()
            ->and(strlen($ticket->uuid))->toBe(36)
            ->and($ticket->generated_at)->not->toBeNull();
    });

    it('generateForTrip 2x → idempoten (updateOrCreate, bukan duplikat)', function () {
        $trip = makeTripForETicket($this->client, $this->mitra);

        QrCode::shouldReceive('format->size->generate')->andReturn('QR-CONTENT');

        $pdfMock = Mockery::mock(PdfService::class);
        $pdfMock->shouldReceive('setPaper')->andReturnSelf();
        $pdfMock->shouldReceive('output')->andReturn('PDF-CONTENT');

        Pdf::shouldReceive('loadView')->andReturn($pdfMock);

        $ticket1 = $this->service->generateForTrip($trip);
        $ticket2 = $this->service->generateForTrip($trip);

        expect(ETicket::where('booking_id', $trip->booking_id)->count())->toBe(1);
        expect($ticket1->id)->toBe($ticket2->id);
    });

    it('generateForTrip → booking_id sudah ada eticket → update', function () {
        $trip = makeTripForETicket($this->client, $this->mitra);

        $existing = ETicket::factory()->create([
            'booking_id' => $trip->booking_id,
            'uuid' => Str::uuid()->toString(),
            'pdf_path' => 'e-tickets/old.pdf',
        ]);

        QrCode::shouldReceive('format->size->generate')->andReturn('QR-CONTENT');

        $pdfMock = Mockery::mock(PdfService::class);
        $pdfMock->shouldReceive('setPaper')->andReturnSelf();
        $pdfMock->shouldReceive('output')->andReturn('PDF-CONTENT');

        Pdf::shouldReceive('loadView')->andReturn($pdfMock);

        $ticket = $this->service->generateForTrip($trip);

        expect($ticket->id)->toBe($existing->id)
            ->and($ticket->pdf_path)->not->toBe('e-tickets/old.pdf');
    });
});

/*
|==========================================================================
| KATEGORI 2 — FIND BY UUID (PERSISTENCE)
|==========================================================================
*/

describe('findByUuid — persistence', function () {
    it('findByUuid → return ETicket dengan relasi', function () {
        $trip = makeTripForETicket($this->client, $this->mitra);

        $uuid = Str::uuid()->toString();
        ETicket::factory()->create([
            'booking_id' => $trip->booking_id,
            'trip_id' => $trip->id,
            'uuid' => $uuid,
            'pdf_path' => 'e-tickets/ticket-test.pdf',
        ]);

        $result = $this->service->findByUuid($uuid);

        expect($result)->not->toBeNull()
            ->and($result->uuid)->toBe($uuid);
    });

    it('findByUuid → return null jika tidak ada', function () {
        $result = $this->service->findByUuid(Str::uuid()->toString());

        expect($result)->toBeNull();
    });
});

/*
|==========================================================================
| KATEGORI 3 — PDF ABSOLUTE PATH (PERSISTENCE)
|==========================================================================
*/

describe('pdfAbsolutePath — persistence', function () {
    it('pdfAbsolutePath → return path absolut jika file ada', function () {
        $trip = makeTripForETicket($this->client, $this->mitra);

        $pdfPath = 'e-tickets/ticket-test.pdf';
        Storage::disk('local')->put($pdfPath, 'PDF-CONTENT');

        $ticket = ETicket::factory()->create([
            'booking_id' => $trip->booking_id,
            'trip_id' => $trip->id,
            'uuid' => Str::uuid()->toString(),
            'pdf_path' => $pdfPath,
        ]);

        $result = $this->service->pdfAbsolutePath($ticket);

        expect($result)->toBeString()
            ->and($result)->toContain($pdfPath);
    });

    it('pdfAbsolutePath → return null jika file tidak ada', function () {
        $trip = makeTripForETicket($this->client, $this->mitra);

        $ticket = ETicket::factory()->create([
            'booking_id' => $trip->booking_id,
            'trip_id' => $trip->id,
            'uuid' => Str::uuid()->toString(),
            'pdf_path' => 'e-tickets/missing.pdf',
        ]);

        $result = $this->service->pdfAbsolutePath($ticket);

        expect($result)->toBeNull();
    });

    it('pdfAbsolutePath → return null jika pdf_path kosong', function () {
        $trip = makeTripForETicket($this->client, $this->mitra);

        $ticket = ETicket::factory()->create([
            'booking_id' => $trip->booking_id,
            'trip_id' => $trip->id,
            'uuid' => Str::uuid()->toString(),
            'pdf_path' => '',
        ]);

        $result = $this->service->pdfAbsolutePath($ticket);

        expect($result)->toBeNull();
    });
});
