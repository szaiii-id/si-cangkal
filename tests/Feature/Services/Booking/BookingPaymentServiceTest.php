<?php

use App\Enums\BookingStatus;
use App\Events\BookingConfirmed;
use App\Models\Booking;
use App\Models\Package;
use App\Models\Payment;
use App\Models\User;
use App\Repositories\Contracts\BookingRepositoryInterface;
use App\Services\Booking\BookingPaymentService;
use App\Services\Notification\Contracts\NotificationDispatcherInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Psr\Log\LoggerInterface;

uses(RefreshDatabase::class);

/*
|==========================================================================
| SETUP — Integration Test
|==========================================================================
|
| BookingPaymentService — persistence `Payment::create` + event +
| file upload.
|
| Yang di-test di sini (yang di-skip di unit test):
| - `verifyPayment` — `Payment::create` + `BookingConfirmed::dispatch`
|   + booking status CONFIRMED.
| - `uploadProof` — file persistence + path di DB.
| - `rejectPayment` — `payment_reject_reason` persistence.
|
| Catatan:
| - `NotificationDispatcherInterface` di-mock — fokus persistence.
| - `Log::channel('audit')` di-mock — `AuditLogger::writer()` butuh
|   channel `audit` (tidak terdaftar di config test).
| - `Auth::login()` — untuk `auth()->user()` di uploadProof.
| - `Event::fake()` — verifikasi `BookingConfirmed` dispatched.
| - `Storage::fake('local')` — isolasi file system.
| - Property `BookingConfirmed`: `$booking`, `$verifiedByMitraId`.
|
*/

beforeEach(function () {
    Storage::fake('local');

    $this->notifier = Mockery::mock(NotificationDispatcherInterface::class);
    $this->notifier->shouldReceive('send')->byDefault()->andReturnNull();

    $this->service = new BookingPaymentService(
        app(BookingRepositoryInterface::class),
        $this->notifier,
    );

    // Mock Log::channel('audit') — AuditLogger::writer()
    $auditLoggerMock = Mockery::mock(LoggerInterface::class);
    $auditLoggerMock->shouldReceive('info')->byDefault()->andReturnNull();

    Log::shouldReceive('channel')->with('audit')->byDefault()->andReturn($auditLoggerMock);
    Log::shouldReceive('driver')->byDefault()->andReturn($auditLoggerMock);

    $this->client = User::factory()->client()->create([
        'name' => 'Budi Client',
        'must_change_password' => false,
    ]);

    $this->mitra = User::factory()->mitra()->create([
        'name' => 'Mitra Travel',
        'must_change_password' => false,
    ]);
});

/*
|==========================================================================
| HELPER — buat booking
|==========================================================================
*/
function makeBookingForPayment(User $client, User $mitra, array $overrides = []): Booking
{
    $package = Package::factory()->forMitra($mitra)->aktif()->create();

    return Booking::factory()
        ->forPackage($package)
        ->forClient($client)
        ->create(array_merge([
            'mitra_id' => $mitra->id,
            'status' => BookingStatus::PENDING_PAYMENT,
            'payment_proof_path' => null,
            'total_harga' => 1_000_000,
        ], $overrides));
}

/*
|==========================================================================
| KATEGORI 1 — VERIFY PAYMENT (PERSISTENCE)
|==========================================================================
*/

describe('verifyPayment — persistence', function () {
    it('verifyPayment → Payment::create + booking CONFIRMED', function () {
        $booking = makeBookingForPayment($this->client, $this->mitra);

        Event::fake([BookingConfirmed::class]);

        $result = $this->service->verifyPayment($booking, $this->mitra);

        $this->assertDatabaseHas('payments', [
            'booking_id' => $booking->id,
            'jumlah' => 1_000_000,
            'verified_by' => $this->mitra->id,
        ]);

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => BookingStatus::CONFIRMED->value,
        ]);

        expect($result->status)->toBe(BookingStatus::CONFIRMED);
    });

    it('verifyPayment 2x dengan instance booking lama → Payment & event hanya 1', function () {
        $booking = makeBookingForPayment($this->client, $this->mitra);

        Event::fake([BookingConfirmed::class]);

        $this->service->verifyPayment($booking, $this->mitra);

        // Instance lama masih berstatus PENDING_PAYMENT. Guard dibaca ulang
        // dari baris terkunci sehingga verifikasi kedua harus ditolak.
        expect(fn () => $this->service->verifyPayment($booking, $this->mitra))
            ->toThrow(ValidationException::class);

        expect(Payment::where('booking_id', $booking->id)->where('jumlah', '>', 0)->count())->toBe(1);

        Event::assertDispatchedTimes(BookingConfirmed::class, 1);
    });

    it('verifyPayment → BookingConfirmed event dispatched', function () {
        $booking = makeBookingForPayment($this->client, $this->mitra);

        Event::fake([BookingConfirmed::class]);

        $this->service->verifyPayment($booking, $this->mitra);

        Event::assertDispatched(BookingConfirmed::class, function ($event) use ($booking) {
            return $event->booking->id === $booking->id
                && $event->verifiedByMitraId === $this->mitra->id;
        });
    });

    it('verifyPayment → payment_verified_at terisi', function () {
        $booking = makeBookingForPayment($this->client, $this->mitra);

        Event::fake([BookingConfirmed::class]);

        $result = $this->service->verifyPayment($booking, $this->mitra);

        expect($result->payment_verified_at)->not->toBeNull();
    });

    it('verifyPayment → notifikasi ke client dikirim', function () {
        $booking = makeBookingForPayment($this->client, $this->mitra);

        Event::fake([BookingConfirmed::class]);

        $this->notifier->shouldReceive('send')
            ->once()
            ->with(
                user: Mockery::on(fn ($u) => $u->id === $this->client->id),
                tipe: 'booking_confirmed',
                judul: 'Booking Dikonfirmasi',
                pesan: Mockery::type('string'),
                data: Mockery::any(),
            );

        $this->service->verifyPayment($booking, $this->mitra);
    });

    it('verifyPayment → mitra bukan owner → ValidationException', function () {
        $otherMitra = User::factory()->mitra()->create(['must_change_password' => false]);
        $booking = makeBookingForPayment($this->client, $this->mitra);

        expect(fn () => $this->service->verifyPayment($booking, $otherMitra))
            ->toThrow(ValidationException::class);
    });

    it('verifyPayment → status bukan pending → ValidationException', function () {
        $booking = makeBookingForPayment($this->client, $this->mitra, [
            'status' => BookingStatus::CONFIRMED,
        ]);

        expect(fn () => $this->service->verifyPayment($booking, $this->mitra))
            ->toThrow(ValidationException::class);
    });
});

/*
|==========================================================================
| KATEGORI 2 — REJECT PAYMENT (PERSISTENCE)
|==========================================================================
*/

describe('rejectPayment — persistence', function () {
    it('rejectPayment → payment_reject_reason tersimpan', function () {
        $booking = makeBookingForPayment($this->client, $this->mitra);

        $result = $this->service->rejectPayment($booking, $this->mitra, 'Foto buram');

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'payment_reject_reason' => 'Foto buram',
        ]);

        expect($result->payment_reject_reason)->toBe('Foto buram');
    });

    it('rejectPayment → status tetap PENDING_PAYMENT', function () {
        $booking = makeBookingForPayment($this->client, $this->mitra);

        $result = $this->service->rejectPayment($booking, $this->mitra, 'Foto buram');

        expect($result->status)->toBe(BookingStatus::PENDING_PAYMENT);
    });

    it('rejectPayment → notifikasi ke client dikirim', function () {
        $booking = makeBookingForPayment($this->client, $this->mitra);

        $this->notifier->shouldReceive('send')
            ->once()
            ->with(
                user: Mockery::on(fn ($u) => $u->id === $this->client->id),
                tipe: 'payment_rejected',
                judul: 'Bukti Transfer Ditolak',
                pesan: Mockery::on(fn ($p) => str_contains($p, 'Foto buram')),
                data: Mockery::any(),
            );

        $this->service->rejectPayment($booking, $this->mitra, 'Foto buram');
    });

    it('rejectPayment → mitra bukan owner → ValidationException', function () {
        $otherMitra = User::factory()->mitra()->create(['must_change_password' => false]);
        $booking = makeBookingForPayment($this->client, $this->mitra);

        expect(fn () => $this->service->rejectPayment($booking, $otherMitra, 'Alasan'))
            ->toThrow(ValidationException::class);
    });

    it('rejectPayment → status bukan pending → ValidationException', function () {
        $booking = makeBookingForPayment($this->client, $this->mitra, [
            'status' => BookingStatus::CONFIRMED,
        ]);

        expect(fn () => $this->service->rejectPayment($booking, $this->mitra, 'Alasan'))
            ->toThrow(ValidationException::class);
    });
});

/*
|==========================================================================
| KATEGORI 3 — UPLOAD PROOF (PERSISTENCE)
|==========================================================================
*/

describe('uploadProof — persistence', function () {
    it('uploadProof client → file tersimpan di disk local + path di DB', function () {
        Auth::login($this->client);

        $booking = makeBookingForPayment($this->client, $this->mitra);

        $file = UploadedFile::fake()->image('proof.jpg', 800, 600);

        $result = $this->service->uploadProof($booking, $file);

        expect($result->payment_proof_path)->toStartWith('payment_proofs/');
        Storage::disk('local')->assertExists($result->payment_proof_path);

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'payment_reject_reason' => null,
        ]);
    });

    it('uploadProof client → notifikasi ke mitra', function () {
        Auth::login($this->client);

        $booking = makeBookingForPayment($this->client, $this->mitra);

        $this->notifier->shouldReceive('send')
            ->once()
            ->with(
                user: Mockery::on(fn ($u) => $u->id === $this->mitra->id),
                tipe: 'payment_proof_uploaded',
                judul: 'Bukti Transfer Baru',
                pesan: Mockery::type('string'),
                data: Mockery::any(),
            );

        $this->service->uploadProof($booking, UploadedFile::fake()->image('proof.jpg'));
    });

    it('uploadProof mitra → notifikasi ke client', function () {
        Auth::login($this->mitra);

        $booking = makeBookingForPayment($this->client, $this->mitra);

        $this->notifier->shouldReceive('send')
            ->once()
            ->with(
                user: Mockery::on(fn ($u) => $u->id === $this->client->id),
                tipe: 'payment_proof_uploaded',
                judul: 'Bukti Transfer Diunggah',
                pesan: Mockery::type('string'),
                data: Mockery::any(),
            );

        $this->service->uploadProof($booking, UploadedFile::fake()->image('proof.jpg'));
    });

    it('uploadProof → file lama dihapus saat upload baru', function () {
        Auth::login($this->client);

        $booking = makeBookingForPayment($this->client, $this->mitra, [
            'payment_proof_path' => 'payment_proofs/old.jpg',
        ]);
        Storage::disk('local')->put('payment_proofs/old.jpg', 'old-content');

        $this->service->uploadProof($booking, UploadedFile::fake()->image('new.jpg'));

        Storage::disk('local')->assertMissing('payment_proofs/old.jpg');
    });

    it('uploadProof → payment_reject_reason di-reset null', function () {
        Auth::login($this->client);

        $booking = makeBookingForPayment($this->client, $this->mitra, [
            'payment_reject_reason' => 'Foto buram',
        ]);

        $result = $this->service->uploadProof($booking, UploadedFile::fake()->image('new.jpg'));

        expect($result->payment_reject_reason)->toBeNull();
    });

    it('uploadProof → status bukan pending → ValidationException', function () {
        $booking = makeBookingForPayment($this->client, $this->mitra, [
            'status' => BookingStatus::CONFIRMED,
        ]);

        expect(fn () => $this->service->uploadProof($booking, UploadedFile::fake()->image('proof.jpg')))
            ->toThrow(ValidationException::class);
    });
});

/*
|==========================================================================
| KATEGORI 4 — IDEMPOTENCY
|==========================================================================
*/

describe('Idempotency', function () {
    it('uploadProof 2x → file lama diganti, hanya 1 file tersisa', function () {
        Auth::login($this->client);

        $booking = makeBookingForPayment($this->client, $this->mitra);

        $this->service->uploadProof($booking, UploadedFile::fake()->image('proof1.jpg'));

        $booking->refresh();

        $this->service->uploadProof($booking, UploadedFile::fake()->image('proof2.jpg'));

        $files = Storage::disk('local')->files('payment_proofs');
        expect($files)->toHaveCount(1);
    });
});

/*
|==========================================================================
| KATEGORI 5 — EDGE CASES
|==========================================================================
*/

describe('Edge Cases — verifyPayment transaction rollback', function () {
    it('verifyPayment → booking sudah CONFIRMED → tidak ada Payment ganda', function () {
        $booking = makeBookingForPayment($this->client, $this->mitra);

        Event::fake([BookingConfirmed::class]);

        $this->service->verifyPayment($booking, $this->mitra);

        $countBefore = Payment::where('booking_id', $booking->id)->count();
        expect($countBefore)->toBe(1);

        expect(fn () => $this->service->verifyPayment($booking->fresh(), $this->mitra))
            ->toThrow(ValidationException::class);

        $countAfter = Payment::where('booking_id', $booking->id)->count();
        expect($countAfter)->toBe(1);
    });
});
