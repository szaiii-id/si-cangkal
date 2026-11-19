<?php

use App\Services\LandingPage\ContactService;
use Carbon\Carbon;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mime\Email;

/*
|==========================================================================
| SETUP — Unit Test (NO DB)
|==========================================================================
*/

beforeEach(function () {
    config(['mail.contact_recipient' => 'admin@sicangkal.test']);

    $this->service = new ContactService;

    // Freeze time di WITA (Asia/Makassar) 11:30
    Carbon::setTestNow(Carbon::create(2026, 1, 15, 11, 30, 0, 'Asia/Makassar'));

    app()->setLocale('id');
});

afterEach(function () {
    Carbon::setTestNow();
});

/*
|==========================================================================
| HELPER
|==========================================================================
*/

function invokeBuildBody(ContactService $service, array $data): string
{
    $ref = new ReflectionMethod($service, 'buildBody');
    $ref->setAccessible(true);

    return $ref->invoke($service, $data);
}

function invokeFormatTime(ContactService $service): string
{
    $ref = new ReflectionMethod($service, 'formatTime');
    $ref->setAccessible(true);

    return $ref->invoke($service);
}

/*
|==========================================================================
| KATEGORI 1 — send
|==========================================================================
*/

describe('send — happy path', function () {
    it('Mail::raw dipanggil dengan recipient dari config', function () {
        $capturedTo = [];

        Mail::shouldReceive('raw')
            ->once()
            ->andReturnUsing(function ($body, $callback) use (&$capturedTo) {
                $message = new Message(new Email);
                $callback($message);
                $capturedTo = array_map(
                    fn ($addr) => $addr->getAddress(),
                    $message->getTo(),
                );
            });

        $this->service->send([
            'nama' => 'Budi',
            'email' => 'budi@example.com',
            'pesan' => 'Pesan minimal 10 karakter',
        ]);

        expect($capturedTo)->toBeArray()->toContain('admin@sicangkal.test');
    });

    it('replyTo diset dengan email + nama pengirim', function () {
        $capturedReplyTo = [];

        Mail::shouldReceive('raw')
            ->once()
            ->andReturnUsing(function ($body, $callback) use (&$capturedReplyTo) {
                $message = new Message(new Email);
                $callback($message);
                $capturedReplyTo = array_map(
                    fn ($addr) => $addr->getAddress(),
                    $message->getReplyTo(),
                );
            });

        $this->service->send([
            'nama' => 'Budi',
            'email' => 'budi@example.com',
            'pesan' => 'Pesan minimal 10 karakter',
        ]);

        expect($capturedReplyTo)->toBeArray()->toContain('budi@example.com');
    });

    it('subject = "Pesan Kontak Baru — {nama}"', function () {
        $capturedSubject = null;

        Mail::shouldReceive('raw')
            ->once()
            ->andReturnUsing(function ($body, $callback) use (&$capturedSubject) {
                $message = new Message(new Email);
                $callback($message);
                $capturedSubject = $message->getSymfonyMessage()->getSubject();
            });

        $this->service->send([
            'nama' => 'Budi Santoso',
            'email' => 'budi@example.com',
            'pesan' => 'Pesan minimal 10 karakter',
        ]);

        expect($capturedSubject)->toBe('Pesan Kontak Baru — Budi Santoso');
    });

    it('recipient dari config (bukan hardcoded)', function () {
        config(['mail.contact_recipient' => 'lain@sicangkal.test']);

        $capturedTo = [];

        Mail::shouldReceive('raw')
            ->once()
            ->andReturnUsing(function ($body, $callback) use (&$capturedTo) {
                $message = new Message(new Email);
                $callback($message);
                $capturedTo = array_map(fn ($addr) => $addr->getAddress(), $message->getTo());
            });

        $this->service->send([
            'nama' => 'Budi',
            'email' => 'budi@example.com',
            'pesan' => 'Pesan minimal 10 karakter',
        ]);

        expect($capturedTo)->toContain('lain@sicangkal.test');
    });
});

/*
|==========================================================================
| KATEGORI 2 — buildBody
|==========================================================================
*/

describe('buildBody — struktur', function () {
    it('berisi nama, email, pesan dari data', function () {
        $result = invokeBuildBody($this->service, [
            'nama' => 'Budi Santoso',
            'email' => 'budi@example.com',
            'pesan' => 'Ini pesan testing',
        ]);

        expect($result)
            ->toContain('Budi Santoso')
            ->toContain('budi@example.com')
            ->toContain('Ini pesan testing');
    });

    it('berisi header title "Pesan kontak baru dari halaman landing SI-CANGKAL"', function () {
        $result = invokeBuildBody($this->service, [
            'nama' => 'X',
            'email' => 'x@y.com',
            'pesan' => 'Test',
        ]);

        expect($result)->toContain('Pesan kontak baru dari halaman landing SI-CANGKAL');
    });

    it('berisi timestamp dari formatTime() dengan label WITA', function () {
        $result = invokeBuildBody($this->service, [
            'nama' => 'X',
            'email' => 'x@y.com',
            'pesan' => 'Test',
        ]);

        expect($result)->toContain('Waktu   :')
            ->and($result)->toContain('WITA');
    });

    it('berisi footer disclaimer', function () {
        $result = invokeBuildBody($this->service, [
            'nama' => 'X',
            'email' => 'x@y.com',
            'pesan' => 'Test',
        ]);

        expect($result)->toContain('Email ini dikirim otomatis dari form kontak SI-CANGKAL');
    });
});

/*
|==========================================================================
| KATEGORI 3 — formatTime
|==========================================================================
*/

describe('formatTime — via reflection', function () {
    it('return format "Hari, tanggal Bulan tahun jam:menit WITA"', function () {
        // Timezone Asia/Makassar (WITA) — 11:30
        $result = invokeFormatTime($this->service);

        expect($result)->toBeString()
            ->and($result)->toContain('15 Januari 2026')
            ->and($result)->toContain('11:30')
            ->and($result)->toContain('WITA');
    });

    it('format lengkap: Kamis, 15 Januari 2026 11:30 WITA', function () {
        $result = invokeFormatTime($this->service);

        expect($result)->toBe('Kamis, 15 Januari 2026 11:30 WITA');
    });
});

/*
|==========================================================================
| KATEGORI 4 — buildBody edge cases
|==========================================================================
*/

describe('buildBody — edge cases', function () {
    it('pesan dengan newline tetap muncul apa adanya', function () {
        $result = invokeBuildBody($this->service, [
            'nama' => 'X',
            'email' => 'x@y.com',
            'pesan' => "Baris 1\nBaris 2",
        ]);

        expect($result)->toContain("Baris 1\nBaris 2");
    });

    it('nama/email dengan karakter khusus (unicode, @) tetap muncul', function () {
        $result = invokeBuildBody($this->service, [
            'nama' => 'Bûdi Sántosø',
            'email' => 'budi+tag@example.co.id',
            'pesan' => 'Test',
        ]);

        expect($result)->toContain('Bûdi Sántosø')
            ->and($result)->toContain('budi+tag@example.co.id');
    });
});
