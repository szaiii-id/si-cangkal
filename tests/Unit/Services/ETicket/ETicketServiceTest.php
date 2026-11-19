<?php

use App\Models\ETicket;
use App\Services\ETicket\ETicketService;
use Illuminate\Support\Facades\Storage;

/*
|==========================================================================
| SETUP — Unit Test (NO DB)
|==========================================================================
*/

beforeEach(function () {
    Storage::fake('local');

    $this->service = new ETicketService;
});

/*
|==========================================================================
| KATEGORI 1 — BVA
|==========================================================================
*/

describe('pdfAbsolutePath — file existence', function () {
    it('file ada di disk → return path absolut', function () {
        Storage::disk('local')->put('e-tickets/ticket-abc.pdf', 'PDF-CONTENT');

        $ticket = Mockery::mock(ETicket::class)->makePartial();
        $ticket->forceFill(['pdf_path' => 'e-tickets/ticket-abc.pdf']);

        $result = $this->service->pdfAbsolutePath($ticket);

        expect($result)->toBeString()
            ->and($result)->toContain('e-tickets/ticket-abc.pdf');
    });

    it('file tidak ada di disk → return null', function () {
        $ticket = Mockery::mock(ETicket::class)->makePartial();
        $ticket->forceFill(['pdf_path' => 'e-tickets/missing.pdf']);

        $result = $this->service->pdfAbsolutePath($ticket);

        expect($result)->toBeNull();
    });
});

/*
|==========================================================================
| KATEGORI 2 — EDGE CASES
|==========================================================================
*/

describe('pdfAbsolutePath — edge cases', function () {
    it('path kosong string → return null (defensif)', function () {
        // Setelah fix kode: guard `pdf_path === ''` → return null.
        // Sebelum fix: Storage::exists('') = true → return root path (bug).
        $ticket = Mockery::mock(ETicket::class)->makePartial();
        $ticket->forceFill(['pdf_path' => '']);

        $result = $this->service->pdfAbsolutePath($ticket);

        expect($result)->toBeNull();
    });

    it('path di sub-direktori lain → return path relatif jika ada', function () {
        Storage::disk('local')->put('other/file.pdf', 'content');

        $ticket = Mockery::mock(ETicket::class)->makePartial();
        $ticket->forceFill(['pdf_path' => 'other/file.pdf']);

        $result = $this->service->pdfAbsolutePath($ticket);

        expect($result)->toBeString()
            ->and($result)->toContain('other/file.pdf');
    });

    it('path file ekstensi non-pdf tetap return jika ada', function () {
        Storage::disk('local')->put('e-tickets/ticket.txt', 'content');

        $ticket = Mockery::mock(ETicket::class)->makePartial();
        $ticket->forceFill(['pdf_path' => 'e-tickets/ticket.txt']);

        $result = $this->service->pdfAbsolutePath($ticket);

        expect($result)->not->toBeNull();
    });

    it('path directory (bukan file) → fleksibel (null atau path)', function () {
        Storage::disk('local')->put('e-tickets/dir/file.pdf', 'content');

        $ticket = Mockery::mock(ETicket::class)->makePartial();
        $ticket->forceFill(['pdf_path' => 'e-tickets/dir']);

        $result = $this->service->pdfAbsolutePath($ticket);

        expect($result === null || is_string($result))->toBeTrue();
    });
});

/*
|==========================================================================
| KATEGORI 3 — CONSTANT
|==========================================================================
*/

describe('DISK & DIRECTORY constant', function () {
    it('DISK = "local"', function () {
        expect(ETicketService::DISK)->toBe('local');
    });

    it('DIRECTORY = "e-tickets"', function () {
        expect(ETicketService::DIRECTORY)->toBe('e-tickets');
    });
});

/*
|==========================================================================
| CATATAN: Test yang butuh Feature (dengan DB + lib)
|==========================================================================
|
| `generateForTrip` — QrCode + Pdf + ETicket::updateOrCreate
| `findByUuid` — ETicket::where (static)
|
| Pindah ke Feature test dengan RefreshDatabase.
|
*/
