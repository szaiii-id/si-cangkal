<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\MitraBankAccount;
use App\Models\Package;
use App\Models\User;
use App\Services\Booking\BookingCancellationService;
use App\Services\Booking\BookingPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery\MockInterface;

uses(RefreshDatabase::class);

/*
|==========================================================================
| SETUP — Integration Test
|==========================================================================
|
| BookingController (mitra) — MUTASI UANG (verify/reject/cancel/upload).
|
| Route (prefix /mitra):
| - GET  /bookings                          → index
| - GET  /bookings/{booking}                → show
| - POST /bookings/{booking}/verify         → verifyPayment
| - POST /bookings/{booking}/reject         → rejectPayment
| - POST /bookings/{booking}/cancel         → cancel
| - POST /bookings/{booking}/upload-proof   → uploadProof
| - GET  /bookings/{booking}/payment-proof  → downloadProof
|
| Middleware: auth, password.changed, role:mitra, mitra.complete
| Policy: BookingPolicy (view, verifyPayment, rejectPayment, cancelByMitra, uploadProof)
| FormRequest: ListBookingRequest, RejectPaymentRequest, CancelBookingRequest, UploadPaymentProofRequest
|
|   Catatan:
| - `config(['inertia.testing.ensure_pages_exist' => false])` — backend test.
| - `Storage::fake('local')` — untuk upload/download proof.
| - `BookingPaymentService` & `BookingCancellationService` di-mock
|   (bukan final) — fokus test CONTROLLER, bukan service.
| - Mitra WAJIB punya bank account (middleware `mitra.complete`).
| - Policy `verifyPayment` return false untuk status != PENDING_PAYMENT
|   → 403, service TIDAK dipanggil.
|
*/

beforeEach(function () {
    if (method_exists($this, 'withoutVite')) {
        $this->withoutVite();
    }

    config(['inertia.testing.ensure_pages_exist' => false]);

    Storage::fake('local');

    $this->mitra = User::factory()->mitra()->create([
        'must_change_password' => false,
    ]);

    MitraBankAccount::create([
        'user_id' => $this->mitra->id,
        'tipe' => 'bank',
        'nama_penyedia' => 'BCA',
        'nomor_rekening' => '1234567890',
        'nama_pemilik' => 'Budi',
        'is_primary' => true,
    ]);

    $this->mitra2 = User::factory()->mitra()->create([
        'must_change_password' => false,
    ]);

    MitraBankAccount::create([
        'user_id' => $this->mitra2->id,
        'tipe' => 'bank',
        'nama_penyedia' => 'BRI',
        'nomor_rekening' => '9999999999',
        'nama_pemilik' => 'Ani',
        'is_primary' => true,
    ]);

    $this->client = User::factory()->client()->create([
        'must_change_password' => false,
    ]);

    $this->package = Package::factory()->forMitra($this->mitra)->aktif()->create();
    $this->package2 = Package::factory()->forMitra($this->mitra2)->aktif()->create();
});

/*
|==========================================================================
| HELPER — buat booking
|==========================================================================
*/
function makeBookingForMitra(User $mitra, User $client, array $overrides = []): Booking
{
    $package = Package::factory()->forMitra($mitra)->aktif()->create();

    return Booking::factory()
        ->forPackage($package)
        ->forClient($client)
        ->create(array_merge([
            'mitra_id' => $mitra->id,
            'status' => BookingStatus::PENDING_PAYMENT,
            'payment_proof_path' => null,
        ], $overrides));
}

/*
|==========================================================================
| KATEGORI 1 — CONTRACT / API SCHEMA
|==========================================================================
*/

describe('Contract — index', function () {
    it('GET /mitra/bookings → render Mitra/Bookings/Index', function () {
        $this->actingAs($this->mitra)
            ->get('/mitra/bookings')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Mitra/Bookings/Index')
                ->has('bookings')
                ->has('filters')
                ->has('statusCounts')
            );
    });

    it('filters default: search=null, status=null', function () {
        $this->actingAs($this->mitra)
            ->get('/mitra/bookings')
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.search', null)
                ->where('filters.status', null)
            );
    });

    it('hanya menampilkan booking milik mitra sendiri', function () {
        makeBookingForMitra($this->mitra, $this->client, ['kode' => 'BKG-SENDIRI']);
        makeBookingForMitra($this->mitra2, $this->client, ['kode' => 'BKG-MITRA2']);

        $this->actingAs($this->mitra)
            ->get('/mitra/bookings')
            ->assertInertia(fn (Assert $page) => $page
                ->where('bookings.total', 1)
                ->where('bookings.data.0.kode', 'BKG-SENDIRI')
            );
    });

    it('route name = mitra.bookings.index', function () {
        expect(route('mitra.bookings.index'))->toBe(url('/mitra/bookings'));
    });
});

describe('Contract — show', function () {
    it('GET /mitra/bookings/{id} → render Mitra/Bookings/Show', function () {
        $booking = makeBookingForMitra($this->mitra, $this->client);

        $this->actingAs($this->mitra)
            ->get("/mitra/bookings/{$booking->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Mitra/Bookings/Show')
                ->has('booking')
                ->where('booking.id', $booking->id)
            );
    });

    it('TIDAK mengirim payment_proof_path mentah (security)', function () {
        $booking = makeBookingForMitra($this->mitra, $this->client, [
            'payment_proof_path' => 'payment_proofs/secret.jpg',
        ]);

        $response = $this->actingAs($this->mitra)
            ->get("/mitra/bookings/{$booking->id}");

        $payload = $response->viewData('page')['props']['booking'];
        expect($payload)->not->toHaveKey('payment_proof_path')
            ->and($payload['has_payment_proof'])->toBeTrue();
    });
});

/*
|==========================================================================
| KATEGORI 2 — SECURITY & AUTHORIZATION
|==========================================================================
*/

describe('Security — guest & role', function () {
    it('guest di-redirect ke login (index)', function () {
        $this->get('/mitra/bookings')->assertRedirect(route('login'));
    });

    it('admin di-redirect ke admin.dashboard (index)', function () {
        $admin = User::factory()->admin()->create(['must_change_password' => false]);

        $this->actingAs($admin)
            ->get('/mitra/bookings')
            ->assertRedirect(route('admin.dashboard'));
    });

    it('client di-redirect ke client.dashboard (index)', function () {
        $this->actingAs($this->client)
            ->get('/mitra/bookings')
            ->assertRedirect(route('client.dashboard'));
    });

    it('mitra must_change_password=true di-redirect ke password.change', function () {
        $needChange = User::factory()->mitra()->create(['must_change_password' => true]);

        $this->actingAs($needChange)
            ->get('/mitra/bookings')
            ->assertRedirect(route('password.change'));
    });
});

describe('Security — ownership (Policy)', function () {
    it('mitra TIDAK bisa show booking mitra lain → 403', function () {
        $booking = makeBookingForMitra($this->mitra2, $this->client);

        $this->actingAs($this->mitra)
            ->get("/mitra/bookings/{$booking->id}")
            ->assertForbidden();
    });

    it('mitra TIDAK bisa verify booking mitra lain → 403', function () {
        $booking = makeBookingForMitra($this->mitra2, $this->client, [
            'payment_proof_path' => 'payment_proofs/proof.jpg',
        ]);

        $this->actingAs($this->mitra)
            ->post("/mitra/bookings/{$booking->id}/verify")
            ->assertForbidden();
    });

    it('mitra TIDAK bisa reject booking mitra lain → 403', function () {
        $booking = makeBookingForMitra($this->mitra2, $this->client);

        $this->actingAs($this->mitra)
            ->post("/mitra/bookings/{$booking->id}/reject", ['reason' => 'Alasan valid'])
            ->assertForbidden();
    });

    it('mitra TIDAK bisa cancel booking mitra lain → 403', function () {
        $booking = makeBookingForMitra($this->mitra2, $this->client);

        $this->actingAs($this->mitra)
            ->post("/mitra/bookings/{$booking->id}/cancel", ['alasan' => 'Alasan valid'])
            ->assertForbidden();
    });

    it('mitra TIDAK bisa download proof booking mitra lain → 403', function () {
        $booking = makeBookingForMitra($this->mitra2, $this->client, [
            'payment_proof_path' => 'payment_proofs/proof.jpg',
        ]);
        Storage::disk('local')->put('payment_proofs/proof.jpg', 'content');

        $this->actingAs($this->mitra)
            ->get("/mitra/bookings/{$booking->id}/payment-proof")
            ->assertForbidden();
    });
});

/*
|==========================================================================
| KATEGORI 3 — CONCURRENCY
|==========================================================================
*/

describe('Concurrency — verify payment', function () {
    it('verify 2x → sukses 2x (mock service)', function () {
        $booking = makeBookingForMitra($this->mitra, $this->client, [
            'payment_proof_path' => 'payment_proofs/proof.jpg',
        ]);

        $this->mock(BookingPaymentService::class, function (MockInterface $m) {
            $m->shouldReceive('verifyPayment')->twice();
        });

        $this->actingAs($this->mitra)
            ->post("/mitra/bookings/{$booking->id}/verify")
            ->assertRedirect();

        $this->actingAs($this->mitra)
            ->post("/mitra/bookings/{$booking->id}/verify")
            ->assertRedirect();
    });
});

/*
|==========================================================================
| KATEGORI 4 — IDEMPOTENCY
|==========================================================================
*/

describe('Idempotency', function () {
    it('verify 2x pada booking sama → mock dipanggil 2x (idempoten di service)', function () {
        $booking = makeBookingForMitra($this->mitra, $this->client, [
            'payment_proof_path' => 'payment_proofs/proof.jpg',
        ]);

        $this->mock(BookingPaymentService::class, function (MockInterface $m) {
            $m->shouldReceive('verifyPayment')->twice();
        });

        $this->actingAs($this->mitra)->post("/mitra/bookings/{$booking->id}/verify");
        $this->actingAs($this->mitra)->post("/mitra/bookings/{$booking->id}/verify");
    });
});

/*
|==========================================================================
| KATEGORI 5 — DATA INTEGRITY & STATE TRANSITION
|==========================================================================
*/

describe('Data Integrity — verifyPayment', function () {
    it('booking tanpa payment_proof_path → error, service TIDAK dipanggil', function () {
        $booking = makeBookingForMitra($this->mitra, $this->client, [
            'payment_proof_path' => null,
        ]);

        $this->mock(BookingPaymentService::class, function (MockInterface $m) {
            $m->shouldNotReceive('verifyPayment');
        });

        $this->actingAs($this->mitra)
            ->from("/mitra/bookings/{$booking->id}")
            ->post("/mitra/bookings/{$booking->id}/verify")
            ->assertRedirect()
            ->assertSessionHas('error');
    });

    it('booking dengan payment_proof_path → service dipanggil', function () {
        $booking = makeBookingForMitra($this->mitra, $this->client, [
            'payment_proof_path' => 'payment_proofs/proof.jpg',
        ]);

        $this->mock(BookingPaymentService::class, function (MockInterface $m) use ($booking) {
            $m->shouldReceive('verifyPayment')
                ->once()
                ->with(
                    Mockery::on(fn ($b) => $b->id === $booking->id),
                    Mockery::on(fn ($u) => $u->id === $this->mitra->id),
                );
        });

        $this->actingAs($this->mitra)
            ->post("/mitra/bookings/{$booking->id}/verify")
            ->assertRedirect()
            ->assertSessionHas('status');
    });
});

describe('Data Integrity — rejectPayment', function () {
    it('reject → service dipanggil dengan reason', function () {
        $booking = makeBookingForMitra($this->mitra, $this->client, [
            'payment_proof_path' => 'payment_proofs/proof.jpg',
        ]);

        $this->mock(BookingPaymentService::class, function (MockInterface $m) {
            $m->shouldReceive('rejectPayment')
                ->once()
                ->with(
                    Mockery::on(fn ($b) => $b->id > 0),
                    Mockery::on(fn ($u) => $u->id > 0),
                    'Foto buram',
                );
        });

        $this->actingAs($this->mitra)
            ->post("/mitra/bookings/{$booking->id}/reject", ['reason' => 'Foto buram'])
            ->assertRedirect()
            ->assertSessionHas('status');
    });
});

describe('Data Integrity — cancel', function () {
    it('cancel → service dipanggil + redirect ke index', function () {
        $booking = makeBookingForMitra($this->mitra, $this->client);

        $this->mock(BookingCancellationService::class, function (MockInterface $m) {
            $m->shouldReceive('cancelByMitra')
                ->once()
                ->with(
                    Mockery::on(fn ($b) => $b->id > 0),
                    Mockery::on(fn ($u) => $u->id > 0),
                    'Cuaca buruk',
                );
        });

        $this->actingAs($this->mitra)
            ->post("/mitra/bookings/{$booking->id}/cancel", ['alasan' => 'Cuaca buruk'])
            ->assertRedirect(route('mitra.bookings.index'))
            ->assertSessionHas('status');
    });
});

describe('Data Integrity — uploadProof', function () {
    it('upload proof → service dipanggil dengan UploadedFile', function () {
        $booking = makeBookingForMitra($this->mitra, $this->client);

        $this->mock(BookingPaymentService::class, function (MockInterface $m) {
            $m->shouldReceive('uploadProof')
                ->once()
                ->with(
                    Mockery::on(fn ($b) => $b->id > 0),
                    Mockery::type(UploadedFile::class),
                );
        });

        $file = UploadedFile::fake()->image('proof.jpg', 800, 600);

        $this->actingAs($this->mitra)
            ->post("/mitra/bookings/{$booking->id}/upload-proof", ['payment_proof' => $file])
            ->assertRedirect()
            ->assertSessionHas('status');
    });
});

describe('Data Integrity — downloadProof', function () {
    it('download proof → return file dengan filename bukti-transfer-{kode}', function () {
        $booking = makeBookingForMitra($this->mitra, $this->client, [
            'kode' => 'BKG-TEST-001',
            'payment_proof_path' => 'payment_proofs/proof.jpg',
        ]);
        Storage::disk('local')->put('payment_proofs/proof.jpg', 'file-content');

        $response = $this->actingAs($this->mitra)
            ->get("/mitra/bookings/{$booking->id}/payment-proof");

        $response->assertOk();
        $response->assertHeader(
            'content-disposition',
            'attachment; filename=bukti-transfer-BKG-TEST-001.jpg'
        );
    });

    it('path null → 404', function () {
        $booking = makeBookingForMitra($this->mitra, $this->client, [
            'payment_proof_path' => null,
        ]);

        $this->actingAs($this->mitra)
            ->get("/mitra/bookings/{$booking->id}/payment-proof")
            ->assertNotFound();
    });

    it('file tidak ada di storage → 404', function () {
        $booking = makeBookingForMitra($this->mitra, $this->client, [
            'payment_proof_path' => 'payment_proofs/missing.jpg',
        ]);

        $this->actingAs($this->mitra)
            ->get("/mitra/bookings/{$booking->id}/payment-proof")
            ->assertNotFound();
    });
});

/*
|==========================================================================
| KATEGORI 6 — ERROR HANDLING (Validation)
|==========================================================================
*/

describe('Error Handling — rejectPayment validation', function () {
    it('reason kosong → error', function () {
        $booking = makeBookingForMitra($this->mitra, $this->client);

        $this->actingAs($this->mitra)
            ->post("/mitra/bookings/{$booking->id}/reject", ['reason' => ''])
            ->assertSessionHasErrors('reason');
    });

    it('reason < 5 karakter → error', function () {
        $booking = makeBookingForMitra($this->mitra, $this->client);

        $this->actingAs($this->mitra)
            ->post("/mitra/bookings/{$booking->id}/reject", ['reason' => 'abc'])
            ->assertSessionHasErrors('reason');
    });
});

describe('Error Handling — cancel validation', function () {
    it('alasan kosong → error', function () {
        $booking = makeBookingForMitra($this->mitra, $this->client);

        $this->actingAs($this->mitra)
            ->post("/mitra/bookings/{$booking->id}/cancel", ['alasan' => ''])
            ->assertSessionHasErrors('alasan');
    });

    it('alasan < 5 karakter → error', function () {
        $booking = makeBookingForMitra($this->mitra, $this->client);

        $this->actingAs($this->mitra)
            ->post("/mitra/bookings/{$booking->id}/cancel", ['alasan' => 'abc'])
            ->assertSessionHasErrors('alasan');
    });
});

describe('Error Handling — uploadProof validation', function () {
    it('payment_proof kosong → error', function () {
        $booking = makeBookingForMitra($this->mitra, $this->client);

        $this->actingAs($this->mitra)
            ->post("/mitra/bookings/{$booking->id}/upload-proof", [])
            ->assertSessionHasErrors('payment_proof');
    });

    it('payment_proof bukan gambar → error', function () {
        $booking = makeBookingForMitra($this->mitra, $this->client);
        $file = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');

        $this->actingAs($this->mitra)
            ->post("/mitra/bookings/{$booking->id}/upload-proof", ['payment_proof' => $file])
            ->assertSessionHasErrors('payment_proof');
    });

    it('payment_proof > 5MB → error', function () {
        $booking = makeBookingForMitra($this->mitra, $this->client);
        $file = UploadedFile::fake()->image('big.jpg')->size(6000);

        $this->actingAs($this->mitra)
            ->post("/mitra/bookings/{$booking->id}/upload-proof", ['payment_proof' => $file])
            ->assertSessionHasErrors('payment_proof');
    });
});

/*
|==========================================================================
| KATEGORI 7 — EDGE CASES & BVA
|==========================================================================
*/

describe('Edge Cases — verify state guard (Policy)', function () {
    it('verify booking status CONFIRMED → 403 (policy guard)', function () {
        $booking = makeBookingForMitra($this->mitra, $this->client, [
            'status' => BookingStatus::CONFIRMED,
            'payment_proof_path' => 'payment_proofs/proof.jpg',
        ]);

        // Policy verifyPayment return false untuk status != PENDING_PAYMENT
        // → 403, service TIDAK dipanggil
        $this->mock(BookingPaymentService::class, function (MockInterface $m) {
            $m->shouldNotReceive('verifyPayment');
        });

        $this->actingAs($this->mitra)
            ->post("/mitra/bookings/{$booking->id}/verify")
            ->assertForbidden();
    });
});

describe('Edge Cases — index filters', function () {
    it('filter status=pending_payment → hanya pending', function () {
        makeBookingForMitra($this->mitra, $this->client, ['status' => BookingStatus::PENDING_PAYMENT]);
        makeBookingForMitra($this->mitra, $this->client, ['status' => BookingStatus::CONFIRMED]);

        $this->actingAs($this->mitra)
            ->get('/mitra/bookings?status=pending_payment')
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.status', 'pending_payment')
            );
    });

    it('search kode booking → return match', function () {
        makeBookingForMitra($this->mitra, $this->client, ['kode' => 'BKG-SEARCH-001']);

        $this->actingAs($this->mitra)
            ->get('/mitra/bookings?search=BKG-SEARCH-001')
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.search', 'BKG-SEARCH-001')
            );
    });
});
