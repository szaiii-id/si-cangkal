<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\ETicket;
use App\Models\MitraBankAccount;
use App\Models\Package;
use App\Models\Profile;
use App\Models\Trip;
use App\Models\User;
use App\Services\Booking\BookingCancellationService;
use App\Services\Booking\BookingCreationService;
use App\Services\Booking\BookingPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery\MockInterface;

uses(RefreshDatabase::class);

/*
|==========================================================================
| SETUP — Integration Test
|==========================================================================
|
| BookingController (client) — MUTASI UANG (create/cancel/upload/download).
|
| Route (prefix /client):
| - GET  /bookings                          → index
| - GET  /bookings/create/{package}         → create
| - POST /bookings/{package}                → store
| - GET  /bookings/{booking}                → show
| - POST /bookings/{booking}/cancel         → cancel
| - POST /bookings/{booking}/upload-proof   → uploadProof
| - GET  /bookings/{booking}/payment-proof  → downloadProof
|
| Middleware: auth, password.changed, role:client
| Policy: BookingPolicy (view, cancelByClient, uploadProof)
| FormRequest: ListBookingRequest, StoreBookingRequest, UploadPaymentProofRequest
|
|   Catatan:
| - `config(['inertia.testing.ensure_pages_exist' => false])` — backend test.
| - `Storage::fake('local')` — untuk upload/download proof.
| - `BookingCreationService`, `BookingPaymentService`, `BookingCancellationService`
|   di-mock — fokus test CONTROLLER.
| - Client TIDAK butuh bank account (beda dengan mitra).
| - UUID pakai `Str::uuid()` (36 char) — kolom `CHAR(36)` di DB
|   akan pad spasi kalau string < 36 char.
| - `index()` sekarang pakai `ListBookingRequest` — invalid input →
|   validation error (bukan 0 hasil).
| - `store()` catch `ValidationException` + `Throwable` → redirect back.
|
*/

beforeEach(function () {
    if (method_exists($this, 'withoutVite')) {
        $this->withoutVite();
    }

    config(['inertia.testing.ensure_pages_exist' => false]);

    Storage::fake('local');

    $this->client = User::factory()->client()->create([
        'name' => 'Budi Client',
        'email' => 'budi@example.com',
        'must_change_password' => false,
    ]);

    $this->client2 = User::factory()->client()->create([
        'must_change_password' => false,
    ]);

    $this->mitra = User::factory()->mitra()->create([
        'name' => 'Mitra Travel',
        'must_change_password' => false,
    ]);

    Profile::factory()->create([
        'user_id' => $this->mitra->id,
        'tipe' => 'mitra',
        'nama_penyedia_usaha' => 'PT Travel Jaya',
        'telepon' => '08123456789',
    ]);

    MitraBankAccount::create([
        'user_id' => $this->mitra->id,
        'tipe' => 'bank',
        'nama_penyedia' => 'BCA',
        'nomor_rekening' => '1234567890',
        'nama_pemilik' => 'Budi',
        'is_primary' => true,
    ]);

    $this->package = Package::factory()->forMitra($this->mitra)->aktif()->create([
        'nama' => 'Paket Test',
        'durasi_hari' => 2,
    ]);
});

/*
|==========================================================================
| HELPER — payload valid
|==========================================================================
*/
function validStoreBookingPayload(array $overrides = []): array
{
    $tanggal = now()->addDays(5)->format('Y-m-d');

    return array_merge([
        'tanggal_keberangkatan' => $tanggal,
        'jumlah_peserta' => 2,
        'catatan' => 'Catatan booking',
        'participants' => [
            ['nama' => 'Peserta 1', 'umur' => 25, 'no_hp' => '0811111111'],
            ['nama' => 'Peserta 2', 'umur' => 30, 'no_hp' => '0822222222'],
        ],
    ], $overrides);
}

function makeBookingForClient(User $client, User $mitra, array $overrides = []): Booking
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
    it('GET /client/bookings → render Client/Bookings/Index', function () {
        $this->actingAs($this->client)
            ->get('/client/bookings')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Client/Bookings/Index')
                ->has('bookings')
                ->has('filters')
                ->has('statusCounts')
            );
    });

    it('hanya menampilkan booking milik client sendiri', function () {
        makeBookingForClient($this->client, $this->mitra, ['kode' => 'BKG-SENDIRI']);
        makeBookingForClient($this->client2, $this->mitra, ['kode' => 'BKG-CLIENT2']);

        $this->actingAs($this->client)
            ->get('/client/bookings')
            ->assertInertia(fn (Assert $page) => $page
                ->where('bookings.total', 1)
                ->where('bookings.data.0.kode', 'BKG-SENDIRI')
            );
    });

    it('route name = client.bookings.index', function () {
        expect(route('client.bookings.index'))->toBe(url('/client/bookings'));
    });
});

describe('Contract — create', function () {
    it('GET /client/bookings/create/{package} → render Client/Bookings/Create', function () {
        $this->actingAs($this->client)
            ->get("/client/bookings/create/{$this->package->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Client/Bookings/Create')
                ->has('package')
                ->where('package.id', $this->package->id)
            );
    });

    it('create paket tidak aktif → 404', function () {
        $draft = Package::factory()->forMitra($this->mitra)->draft()->create();

        $this->actingAs($this->client)
            ->get("/client/bookings/create/{$draft->id}")
            ->assertNotFound();
    });
});

describe('Contract — show', function () {
    it('GET /client/bookings/{id} → render Client/Bookings/Show', function () {
        $booking = makeBookingForClient($this->client, $this->mitra);

        $this->actingAs($this->client)
            ->get("/client/bookings/{$booking->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Client/Bookings/Show')
                ->has('booking')
                ->has('refunds')
                ->where('booking.id', $booking->id)
            );
    });

    it('canReview true untuk trip selesai, false untuk trip active', function () {
        $booking = makeBookingForClient($this->client, $this->mitra);
        $trip = Trip::factory()->forBooking($booking)->active()->create();

        $this->actingAs($this->client)
            ->get("/client/bookings/{$booking->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('canReview', false)
                ->where('review', null)
            );

        $trip->update(['status' => 'completed']);

        $this->actingAs($this->client)
            ->get("/client/bookings/{$booking->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('canReview', true)
                ->where('review', null)
            );
    });

    it('TIDAK mengirim payment_proof_path mentah (security)', function () {
        $booking = makeBookingForClient($this->client, $this->mitra, [
            'payment_proof_path' => 'payment_proofs/secret.jpg',
        ]);

        $response = $this->actingAs($this->client)
            ->get("/client/bookings/{$booking->id}");

        $payload = $response->viewData('page')['props']['booking'];
        expect($payload)->not->toHaveKey('payment_proof_path')
            ->and($payload['has_payment_proof'])->toBeTrue();
    });

    it('show dengan eticket → props eticket terisi', function () {
        $booking = makeBookingForClient($this->client, $this->mitra, [
            'status' => BookingStatus::CONFIRMED,
        ]);

        $uuid = (string) Str::uuid();

        ETicket::factory()->create([
            'booking_id' => $booking->id,
            'uuid' => $uuid,
        ]);

        $this->actingAs($this->client)
            ->get("/client/bookings/{$booking->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->has('eticket')
                ->where('eticket.uuid', $uuid)
            );
    });
});

/*
|==========================================================================
| KATEGORI 2 — SECURITY & AUTHORIZATION
|==========================================================================
*/

describe('Security — guest & role', function () {
    it('guest di-redirect ke login (index)', function () {
        $this->get('/client/bookings')->assertRedirect(route('login'));
    });

    it('admin di-redirect ke admin.dashboard (index)', function () {
        $admin = User::factory()->admin()->create(['must_change_password' => false]);

        $this->actingAs($admin)
            ->get('/client/bookings')
            ->assertRedirect(route('admin.dashboard'));
    });

    it('mitra di-redirect ke mitra.dashboard (index)', function () {
        $this->actingAs($this->mitra)
            ->get('/client/bookings')
            ->assertRedirect(route('mitra.dashboard'));
    });

    it('client must_change_password=true di-redirect ke password.change', function () {
        $needChange = User::factory()->client()->create(['must_change_password' => true]);

        $this->actingAs($needChange)
            ->get('/client/bookings')
            ->assertRedirect(route('password.change'));
    });
});

describe('Security — ownership (Policy)', function () {
    it('client TIDAK bisa show booking client lain → 403', function () {
        $booking = makeBookingForClient($this->client2, $this->mitra);

        $this->actingAs($this->client)
            ->get("/client/bookings/{$booking->id}")
            ->assertForbidden();
    });

    it('client TIDAK bisa cancel booking client lain → 403', function () {
        $booking = makeBookingForClient($this->client2, $this->mitra);

        $this->mock(BookingCancellationService::class, function (MockInterface $m) {
            $m->shouldNotReceive('cancelByClient');
        });

        $this->actingAs($this->client)
            ->post("/client/bookings/{$booking->id}/cancel")
            ->assertForbidden();
    });

    it('client TIDAK bisa download proof booking client lain → 403', function () {
        $booking = makeBookingForClient($this->client2, $this->mitra, [
            'payment_proof_path' => 'payment_proofs/proof.jpg',
        ]);
        Storage::disk('local')->put('payment_proofs/proof.jpg', 'content');

        $this->actingAs($this->client)
            ->get("/client/bookings/{$booking->id}/payment-proof")
            ->assertForbidden();
    });
});

/*
|==========================================================================
| KATEGORI 3 — CONCURRENCY
|==========================================================================
*/

describe('Concurrency — store', function () {
    it('store 2x → mock dipanggil 2x (idempoten di service)', function () {
        $booking = makeBookingForClient($this->client, $this->mitra);

        $this->mock(BookingCreationService::class, function (MockInterface $m) use ($booking) {
            $m->shouldReceive('create')->twice()->andReturn($booking);
        });

        $payload = validStoreBookingPayload();

        $this->actingAs($this->client)->post("/client/bookings/{$this->package->id}", $payload);
        $this->actingAs($this->client)->post("/client/bookings/{$this->package->id}", $payload);
    });
});

/*
|==========================================================================
| KATEGORI 4 — IDEMPOTENCY
|==========================================================================
*/

describe('Idempotency', function () {
    it('cancel 2x → mock service dipanggil 2x', function () {
        $booking = makeBookingForClient($this->client, $this->mitra);

        $this->mock(BookingCancellationService::class, function (MockInterface $m) {
            $m->shouldReceive('cancelByClient')->twice();
        });

        $this->actingAs($this->client)->post("/client/bookings/{$booking->id}/cancel");
        $this->actingAs($this->client)->post("/client/bookings/{$booking->id}/cancel");
    });
});

/*
|==========================================================================
| KATEGORI 5 — DATA INTEGRITY & STATE TRANSITION
|==========================================================================
*/

describe('Data Integrity — store', function () {
    it('store → service dipanggil + redirect ke show', function () {
        $booking = makeBookingForClient($this->client, $this->mitra, ['id' => '00000000-0000-4000-8000-000000000000']);

        $this->mock(BookingCreationService::class, function (MockInterface $m) use ($booking) {
            $m->shouldReceive('create')
                ->once()
                ->with(
                    Mockery::on(fn ($p) => $p->id === $this->package->id),
                    Mockery::on(fn ($c) => $c->id === $this->client->id),
                    Mockery::type('array'),
                )
                ->andReturn($booking);
        });

        $response = $this->actingAs($this->client)
            ->post("/client/bookings/{$this->package->id}", validStoreBookingPayload());

        $response->assertRedirect(route('client.bookings.show', '00000000-0000-4000-8000-000000000000'))
            ->assertSessionHas('status');
    });

    it('store → service throw ValidationException → redirect back + errors', function () {
        $this->mock(BookingCreationService::class, function (MockInterface $m) {
            $m->shouldReceive('create')
                ->once()
                ->andThrow(ValidationException::withMessages([
                    'jumlah_peserta' => 'Melebihi batas maksimal untuk paket ini.',
                ]));
        });

        $this->actingAs($this->client)
            ->from("/client/bookings/create/{$this->package->id}")
            ->post("/client/bookings/{$this->package->id}", validStoreBookingPayload())
            ->assertRedirect()
            ->assertSessionHasErrors('jumlah_peserta');
    });

    it('store → service throw Throwable → redirect back + error message', function () {
        $this->mock(BookingCreationService::class, function (MockInterface $m) {
            $m->shouldReceive('create')
                ->once()
                ->andThrow(new RuntimeException('DB error'));
        });

        Log::spy();

        $this->actingAs($this->client)
            ->from("/client/bookings/create/{$this->package->id}")
            ->post("/client/bookings/{$this->package->id}", validStoreBookingPayload())
            ->assertRedirect()
            ->assertSessionHas('error');

        Log::shouldHaveReceived('error')
            ->once()
            ->with('Client booking creation failed', Mockery::on(
                fn ($ctx) => $ctx['user_id'] === $this->client->id
                    && $ctx['package_id'] === $this->package->id
                    && $ctx['error'] === 'DB error'
            ));
    });
});

describe('Data Integrity — cancel', function () {
    it('cancel → service dipanggil + redirect show', function () {
        $booking = makeBookingForClient($this->client, $this->mitra);

        $this->mock(BookingCancellationService::class, function (MockInterface $m) use ($booking) {
            $m->shouldReceive('cancelByClient')
                ->once()
                ->with(Mockery::on(fn ($b) => $b->id === $booking->id));
        });

        $this->actingAs($this->client)
            ->post("/client/bookings/{$booking->id}/cancel")
            ->assertRedirect(route('client.bookings.show', $booking->id))
            ->assertSessionHas('status');
    });
});

describe('Data Integrity — uploadProof', function () {
    it('upload proof → service dipanggil dengan UploadedFile', function () {
        $booking = makeBookingForClient($this->client, $this->mitra);

        $this->mock(BookingPaymentService::class, function (MockInterface $m) use ($booking) {
            $m->shouldReceive('uploadProof')
                ->once()
                ->with(
                    Mockery::on(fn ($b) => $b->id === $booking->id),
                    Mockery::type(UploadedFile::class),
                );
        });

        $file = UploadedFile::fake()->image('proof.jpg', 800, 600);

        $this->actingAs($this->client)
            ->post("/client/bookings/{$booking->id}/upload-proof", ['payment_proof' => $file])
            ->assertRedirect()
            ->assertSessionHas('status');
    });
});

describe('Data Integrity — downloadProof', function () {
    it('download proof → return file dengan filename bukti-transfer-{kode}', function () {
        $booking = makeBookingForClient($this->client, $this->mitra, [
            'kode' => 'BKG-TEST-001',
            'payment_proof_path' => 'payment_proofs/proof.jpg',
        ]);
        Storage::disk('local')->put('payment_proofs/proof.jpg', 'file-content');

        $response = $this->actingAs($this->client)
            ->get("/client/bookings/{$booking->id}/payment-proof");

        $response->assertOk();
        $response->assertHeader(
            'content-disposition',
            'attachment; filename=bukti-transfer-BKG-TEST-001.jpg'
        );
    });

    it('path null → 404', function () {
        $booking = makeBookingForClient($this->client, $this->mitra, [
            'payment_proof_path' => null,
        ]);

        $this->actingAs($this->client)
            ->get("/client/bookings/{$booking->id}/payment-proof")
            ->assertNotFound();
    });
});

/*
|==========================================================================
| KATEGORI 6 — ERROR HANDLING (Validation)
|==========================================================================
*/

describe('Error Handling — index validation', function () {
    it('status=ngawur → validation error', function () {
        $this->actingAs($this->client)
            ->get('/client/bookings?status=ngawur')
            ->assertSessionHasErrors('status');
    });

    it('per_page < 5 → validation error', function () {
        $this->actingAs($this->client)
            ->get('/client/bookings?per_page=4')
            ->assertSessionHasErrors('per_page');
    });

    it('per_page > 100 → validation error', function () {
        $this->actingAs($this->client)
            ->get('/client/bookings?per_page=101')
            ->assertSessionHasErrors('per_page');
    });
});

describe('Error Handling — store validation', function () {
    it('tanggal < H+1 → error', function () {
        $this->actingAs($this->client)
            ->post("/client/bookings/{$this->package->id}", validStoreBookingPayload([
                'tanggal_keberangkatan' => now($this->package->timezone)->format('Y-m-d'),
            ]))
            ->assertSessionHasErrors('tanggal_keberangkatan');
    });

    it('jumlah_peserta 0 → error', function () {
        $this->actingAs($this->client)
            ->post("/client/bookings/{$this->package->id}", validStoreBookingPayload([
                'jumlah_peserta' => 0,
                'participants' => [],
            ]))
            ->assertSessionHasErrors('jumlah_peserta');
    });

    it('participants count != jumlah_peserta → error', function () {
        $this->actingAs($this->client)
            ->post("/client/bookings/{$this->package->id}", validStoreBookingPayload([
                'jumlah_peserta' => 3,
                'participants' => [
                    ['nama' => 'P1'],
                    ['nama' => 'P2'],
                ],
            ]))
            ->assertSessionHasErrors('participants');
    });
});

describe('Error Handling — uploadProof validation', function () {
    it('payment_proof kosong → error', function () {
        $booking = makeBookingForClient($this->client, $this->mitra);

        $this->actingAs($this->client)
            ->post("/client/bookings/{$booking->id}/upload-proof", [])
            ->assertSessionHasErrors('payment_proof');
    });

    it('payment_proof bukan gambar → error', function () {
        $booking = makeBookingForClient($this->client, $this->mitra);
        $file = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');

        $this->actingAs($this->client)
            ->post("/client/bookings/{$booking->id}/upload-proof", ['payment_proof' => $file])
            ->assertSessionHasErrors('payment_proof');
    });

    it('upload proof booking bukan pending_payment → error', function () {
        $booking = makeBookingForClient($this->client, $this->mitra, [
            'status' => BookingStatus::CONFIRMED,
        ]);
        $file = UploadedFile::fake()->image('proof.jpg', 800, 600);

        $this->actingAs($this->client)
            ->post("/client/bookings/{$booking->id}/upload-proof", ['payment_proof' => $file])
            ->assertSessionHasErrors('payment_proof');
    });
});

/*
|==========================================================================
| KATEGORI 7 — EDGE CASES & BVA
|==========================================================================
*/

describe('Edge Cases — empty DB', function () {
    it('DB tanpa booking → total 0', function () {
        $this->actingAs($this->client)
            ->get('/client/bookings')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('bookings.total', 0)
            );
    });
});

describe('Edge Cases — index filters', function () {
    it('filter status=pending_payment → hanya pending', function () {
        makeBookingForClient($this->client, $this->mitra, ['status' => BookingStatus::PENDING_PAYMENT]);
        makeBookingForClient($this->client, $this->mitra, ['status' => BookingStatus::CONFIRMED]);

        $this->actingAs($this->client)
            ->get('/client/bookings?status=pending_payment')
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.status', 'pending_payment')
            );
    });
});
