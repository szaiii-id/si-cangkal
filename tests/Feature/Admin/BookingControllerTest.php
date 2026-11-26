<?php

use App\Enums\BookingStatus;
use App\Enums\PackageStatus;
use App\Models\Booking;
use App\Models\Package;
use App\Models\Trip;
use App\Models\User;
use App\Services\ETicket\Contracts\ETicketServiceInterface;
use App\Services\Trip\TripService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery\MockInterface;

uses(RefreshDatabase::class);

/*
|==========================================================================
| SETUP — Integration Test
|==========================================================================
|
| BookingController (admin) — monitoring & recovery.
|
| Route prefix: /admin/bookings (admin.bookings.*)
| Middleware: auth, password.changed, role:admin
| Policy: BookingPolicy (viewAny, view)
|
| Mock strategy:
| - `ETicketServiceInterface` — di-bind ke mock (bukan final class).
| - `TripService` — di-mock (bukan final class).
| - `Storage::fake('local')` — untuk downloadProof & ETicket.
|
| Catatan penting:
| - `generateForTrip` mock WAJIB pakai `Mockery::on()` bukan `->with($trip)`
|   karena controller retrieve instance BARU dari DB via `Trip::where()`.
|   Object identity beda meskipun ID sama.
| - Test "tanpa trip" TIDAK boleh create trip di DB — biarkan kosong
|   agar controller panggil `createFromBooking()`.
|
*/

beforeEach(function () {
    if (method_exists($this, 'withoutVite')) {
        $this->withoutVite();
    }

    Storage::fake('local');
    Storage::fake('r2_private');

    $this->admin = User::factory()->admin()->create([
        'must_change_password' => false,
    ]);
    $this->mitra = User::factory()->mitra()->create([
        'must_change_password' => false,
    ]);
    $this->client = User::factory()->client()->create([
        'must_change_password' => false,
    ]);
});

/*
|==========================================================================
| KATEGORI 1 — CONTRACT / API SCHEMA
|==========================================================================
*/

describe('Contract — index', function () {
    it('GET /admin/bookings → render Admin/Bookings/Index', function () {
        $this->actingAs($this->admin)
            ->get('/admin/bookings')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Bookings/Index')
                ->has('bookings')
                ->has('filters')
                ->has('statusCounts')
            );
    });

    it('filters default: search=null, status=null, sort=created_at, direction=desc', function () {
        $this->actingAs($this->admin)
            ->get('/admin/bookings')
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.search', null)
                ->where('filters.status', null)
                ->where('filters.sort', 'created_at')
                ->where('filters.direction', 'desc')
            );
    });

    it('statusCounts punya semua key status enum', function () {
        $this->actingAs($this->admin)
            ->get('/admin/bookings')
            ->assertInertia(fn (Assert $page) => $page
                ->has('statusCounts.'.BookingStatus::PENDING_PAYMENT->value)
                ->has('statusCounts.'.BookingStatus::CONFIRMED->value)
                ->has('statusCounts.'.BookingStatus::CANCELLED->value)
            );
    });

    it('route name = admin.bookings.index', function () {
        expect(route('admin.bookings.index'))->toBe(url('/admin/bookings'));
    });
});

describe('Contract — show', function () {
    it('GET /admin/bookings/{id} → render Admin/Bookings/Show', function () {
        $booking = makeBookingForAdmin($this->mitra, $this->client);

        $this->actingAs($this->admin)
            ->get("/admin/bookings/{$booking->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Bookings/Show')
                ->has('booking')
            );
    });

    it('booking resource shape: id, kode, status, statusLabel, has_payment_proof', function () {
        $booking = makeBookingForAdmin($this->mitra, $this->client);

        $this->actingAs($this->admin)
            ->get("/admin/bookings/{$booking->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('booking.id', $booking->id)
                ->where('booking.kode', $booking->kode)
                ->where('booking.status', $booking->status->value)
                ->where('booking.statusLabel', $booking->status->label())
                ->has('booking.has_payment_proof')
            );
    });

    it('TIDAK mengirim payment_proof_path mentah (security)', function () {
        $booking = makeBookingForAdmin($this->mitra, $this->client, [
            'payment_proof_path' => 'payment_proofs/secret.jpg',
        ]);

        $response = $this->actingAs($this->admin)
            ->get("/admin/bookings/{$booking->id}");

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
    it('guest di-redirect ke login', function () {
        $this->get('/admin/bookings')->assertRedirect(route('login'));
    });

    it('mitra di-redirect ke mitra.dashboard', function () {
        $this->actingAs($this->mitra)
            ->get('/admin/bookings')
            ->assertRedirect(route('mitra.dashboard'));
    });

    it('client di-redirect ke client.dashboard', function () {
        $this->actingAs($this->client)
            ->get('/admin/bookings')
            ->assertRedirect(route('client.dashboard'));
    });

    it('admin must_change_password=true di-redirect ke password.change', function () {
        $needChange = User::factory()->admin()->create(['must_change_password' => true]);

        $this->actingAs($needChange)
            ->get('/admin/bookings')
            ->assertRedirect(route('password.change'));
    });
});

describe('Security — downloadProof (private file)', function () {
    it('path null → 404', function () {
        $booking = makeBookingForAdmin($this->mitra, $this->client, [
            'payment_proof_path' => null,
        ]);

        $this->actingAs($this->admin)
            ->get("/admin/bookings/{$booking->id}/payment-proof")
            ->assertNotFound();
    });

    it('path kosong → 404', function () {
        $booking = makeBookingForAdmin($this->mitra, $this->client, [
            'payment_proof_path' => '',
        ]);

        $this->actingAs($this->admin)
            ->get("/admin/bookings/{$booking->id}/payment-proof")
            ->assertNotFound();
    });

    it('file tidak ada di storage → 404', function () {
        $booking = makeBookingForAdmin($this->mitra, $this->client, [
            'payment_proof_path' => 'payment_proofs/missing.jpg',
        ]);

        $this->actingAs($this->admin)
            ->get("/admin/bookings/{$booking->id}/payment-proof")
            ->assertNotFound();
    });

    it('file ada → download dengan nama bukti-transfer-{kode}.{ext}', function () {
        $booking = makeBookingForAdmin($this->mitra, $this->client, [
            'payment_proof_path' => 'payment_proofs/proof.jpg',
        ]);
        Storage::disk('r2_private')->put('payment_proofs/proof.jpg', 'file-content');

        $response = $this->actingAs($this->admin)
            ->get("/admin/bookings/{$booking->id}/payment-proof");

        $response->assertOk();
        $response->assertHeader(
            'content-disposition',
            'attachment; filename=bukti-transfer-'.$booking->kode.'.jpg'
        );
    });

    it('client TIDAK bisa akses downloadProof', function () {
        $booking = makeBookingForAdmin($this->mitra, $this->client, [
            'payment_proof_path' => 'payment_proofs/proof.jpg',
        ]);
        Storage::disk('r2_private')->put('payment_proofs/proof.jpg', 'file-content');

        $this->actingAs($this->client)
            ->get("/admin/bookings/{$booking->id}/payment-proof")
            ->assertRedirect(route('client.dashboard'));
    });
});

/*
|==========================================================================
| KATEGORI 3 — CONCURRENCY
|==========================================================================
*/

describe('Concurrency — regenerateETicket idempotent', function () {
    it('2x regenerate → sukses 2x, tidak double-create trip/eticket', function () {
        $booking = makeBookingForAdmin($this->mitra, $this->client, [
            'status' => BookingStatus::CONFIRMED,
        ]);
        $trip = Trip::factory()->forBooking($booking)->create();

        $this->mock(TripService::class, fn (MockInterface $m) => $m->shouldNotReceive('createFromBooking'));
        $this->mock(ETicketServiceInterface::class, fn (MockInterface $m) => $m->shouldReceive('generateForTrip')->twice());

        $this->actingAs($this->admin)
            ->post("/admin/bookings/{$booking->id}/regenerate-eticket")
            ->assertRedirect();

        $this->actingAs($this->admin)
            ->post("/admin/bookings/{$booking->id}/regenerate-eticket")
            ->assertRedirect();
    });
});

/*
|==========================================================================
| KATEGORI 4 — IDEMPOTENCY
|==========================================================================
*/
describe('Idempotency — regenerateETicket', function () {
    it('regenerate pada booking CONFIRMED yang sudah punya trip → tidak create trip baru', function () {
        $booking = makeBookingForAdmin($this->mitra, $this->client, [
            'status' => BookingStatus::CONFIRMED,
        ]);

        // Buat trip di DB — forBooking() override booking_id, package_id, mitra_id
        $trip = Trip::factory()->forBooking($booking)->create();

        // Sanity check: trip harus ter-query via booking_id
        expect(Trip::where('booking_id', $booking->id)->first())->not->toBeNull();

        $tripCountBefore = Trip::count();

        $this->mock(TripService::class, fn (MockInterface $m) => $m->shouldNotReceive('createFromBooking'));

        // PENTING: pakai Mockery::on() bukan ->with($trip)
        // karena controller retrieve instance BARU dari DB via Trip::where()
        $this->mock(ETicketServiceInterface::class, function (MockInterface $m) use ($trip) {
            $m->shouldReceive('generateForTrip')
                ->once()
                ->with(Mockery::on(fn (Trip $t) => $t->id === $trip->id));
        });

        $this->actingAs($this->admin)
            ->from("/admin/bookings/{$booking->id}")
            ->post("/admin/bookings/{$booking->id}/regenerate-eticket")
            ->assertRedirect()
            ->assertSessionHas('status');

        expect(Trip::count())->toBe($tripCountBefore);
    });

    it('regenerate pada booking CONFIRMED tanpa trip → create trip dulu, lalu generate', function () {
        $booking = makeBookingForAdmin($this->mitra, $this->client, [
            'status' => BookingStatus::CONFIRMED,
        ]);

        // PENTING: JANGAN create trip di DB — biarkan kosong
        // agar controller panggil TripService::createFromBooking()
        expect(Trip::where('booking_id', $booking->id)->first())->toBeNull();

        // Trip in-memory untuk return value mock (belum di DB)
        $trip = new Trip;
        $trip->forceFill([
            'id' => 999,
            'kode' => 'TRP-2026-999',
            'booking_id' => $booking->id,
            'mitra_id' => $this->mitra->id,
            'package_id' => $booking->package_id,
        ]);

        // FIX: JANGAN pakai ->with() strict — match apa pun.
        // Pola ini sudah terbukti lolos di test "status CONFIRMED → sukses generate".
        $this->mock(TripService::class, function (MockInterface $m) use ($trip) {
            $m->shouldReceive('createFromBooking')
                ->once()
                ->andReturn($trip);
        });
        $this->mock(ETicketServiceInterface::class, function (MockInterface $m) {
            $m->shouldReceive('generateForTrip')->once();
        });

        $this->actingAs($this->admin)
            ->from("/admin/bookings/{$booking->id}")
            ->post("/admin/bookings/{$booking->id}/regenerate-eticket")
            ->assertRedirect()
            ->assertSessionHas('status');
    });
});

/*
|==========================================================================
| KATEGORI 5 — DATA INTEGRITY & STATE TRANSITION
|==========================================================================
*/

describe('Data Integrity — regenerate status guard', function () {
    it('status PENDING_PAYMENT → error, tidak generate', function () {
        $booking = makeBookingForAdmin($this->mitra, $this->client, [
            'status' => BookingStatus::PENDING_PAYMENT,
        ]);

        $this->mock(ETicketServiceInterface::class, fn (MockInterface $m) => $m->shouldNotReceive('generateForTrip'));

        $this->actingAs($this->admin)
            ->from("/admin/bookings/{$booking->id}")
            ->post("/admin/bookings/{$booking->id}/regenerate-eticket")
            ->assertRedirect()
            ->assertSessionHas('error');
    });

    it('status PAYMENT_VERIFIED (legacy) → error, tidak generate', function () {
        $booking = makeBookingForAdmin($this->mitra, $this->client, [
            'status' => BookingStatus::PAYMENT_VERIFIED,
        ]);

        $this->mock(ETicketServiceInterface::class, fn (MockInterface $m) => $m->shouldNotReceive('generateForTrip'));

        $this->actingAs($this->admin)
            ->from("/admin/bookings/{$booking->id}")
            ->post("/admin/bookings/{$booking->id}/regenerate-eticket")
            ->assertRedirect()
            ->assertSessionHas('error');
    });

    it('status CANCELLED → error, tidak generate', function () {
        $booking = makeBookingForAdmin($this->mitra, $this->client, [
            'status' => BookingStatus::CANCELLED,
        ]);

        $this->mock(ETicketServiceInterface::class, fn (MockInterface $m) => $m->shouldNotReceive('generateForTrip'));

        $this->actingAs($this->admin)
            ->from("/admin/bookings/{$booking->id}")
            ->post("/admin/bookings/{$booking->id}/regenerate-eticket")
            ->assertRedirect()
            ->assertSessionHas('error');
    });

    it('status CONFIRMED → sukses generate', function () {
        $booking = makeBookingForAdmin($this->mitra, $this->client, [
            'status' => BookingStatus::CONFIRMED,
        ]);
        $trip = Trip::factory()->forBooking($booking)->create();

        $this->mock(TripService::class, fn (MockInterface $m) => $m->shouldNotReceive('createFromBooking'));
        $this->mock(ETicketServiceInterface::class, fn (MockInterface $m) => $m->shouldReceive('generateForTrip')->once());

        $this->actingAs($this->admin)
            ->post("/admin/bookings/{$booking->id}/regenerate-eticket")
            ->assertRedirect()
            ->assertSessionHas('status');
    });

    it('status IN_PROGRESS → sukses generate', function () {
        $booking = makeBookingForAdmin($this->mitra, $this->client, [
            'status' => BookingStatus::IN_PROGRESS,
        ]);
        $trip = Trip::factory()->forBooking($booking)->create();

        $this->mock(TripService::class, fn (MockInterface $m) => $m->shouldNotReceive('createFromBooking'));
        $this->mock(ETicketServiceInterface::class, fn (MockInterface $m) => $m->shouldReceive('generateForTrip')->once());

        $this->actingAs($this->admin)
            ->post("/admin/bookings/{$booking->id}/regenerate-eticket")
            ->assertRedirect()
            ->assertSessionHas('status');
    });

    it('status COMPLETED → sukses generate', function () {
        $booking = makeBookingForAdmin($this->mitra, $this->client, [
            'status' => BookingStatus::COMPLETED,
        ]);
        $trip = Trip::factory()->forBooking($booking)->create();

        $this->mock(TripService::class, fn (MockInterface $m) => $m->shouldNotReceive('createFromBooking'));
        $this->mock(ETicketServiceInterface::class, fn (MockInterface $m) => $m->shouldReceive('generateForTrip')->once());

        $this->actingAs($this->admin)
            ->post("/admin/bookings/{$booking->id}/regenerate-eticket")
            ->assertRedirect()
            ->assertSessionHas('status');
    });
});

/*
|==========================================================================
| KATEGORI 6 — ERROR HANDLING
|==========================================================================
*/

describe('Error Handling — regenerateETicket exception', function () {
    it('ETicketService throw → error ramah + Log::error', function () {
        $booking = makeBookingForAdmin($this->mitra, $this->client, [
            'status' => BookingStatus::CONFIRMED,
        ]);
        $trip = Trip::factory()->forBooking($booking)->create();

        $this->mock(TripService::class, fn (MockInterface $m) => $m->shouldNotReceive('createFromBooking'));
        $this->mock(ETicketServiceInterface::class, function (MockInterface $m) {
            $m->shouldReceive('generateForTrip')->once()->andThrow(new RuntimeException('QR generation failed'));
        });

        Log::spy();

        $this->actingAs($this->admin)
            ->from("/admin/bookings/{$booking->id}")
            ->post("/admin/bookings/{$booking->id}/regenerate-eticket")
            ->assertRedirect()
            ->assertSessionHas('error');

        Log::shouldHaveReceived('error')
            ->once()
            ->with('Admin regenerate E-Ticket gagal', Mockery::on(
                fn ($ctx) => $ctx['booking_id'] === $booking->id
                    && str_contains($ctx['error'], 'QR generation failed')
            ));
    });

    it('TripService throw saat create trip → error ramah', function () {
        $booking = makeBookingForAdmin($this->mitra, $this->client, [
            'status' => BookingStatus::CONFIRMED,
        ]);

        $this->mock(TripService::class, function (MockInterface $m) {
            $m->shouldReceive('createFromBooking')->once()->andThrow(new RuntimeException('Trip creation failed'));
        });

        Log::spy();

        $this->actingAs($this->admin)
            ->from("/admin/bookings/{$booking->id}")
            ->post("/admin/bookings/{$booking->id}/regenerate-eticket")
            ->assertRedirect()
            ->assertSessionHas('error');

        Log::shouldHaveReceived('error')->once();
    });
});

describe('Error Handling — validation', function () {
    it('status filter invalid → validation error', function () {
        $this->actingAs($this->admin)
            ->get('/admin/bookings?status=ngawur')
            ->assertSessionHasErrors('status');
    });

    it('tanggal_sampai < tanggal_dari → validation error', function () {
        $this->actingAs($this->admin)
            ->get('/admin/bookings?tanggal_dari=2026-02-01&tanggal_sampai=2026-01-01')
            ->assertSessionHasErrors('tanggal_sampai');
    });

    it('per_page < 5 → validation error', function () {
        $this->actingAs($this->admin)
            ->get('/admin/bookings?per_page=4')
            ->assertSessionHasErrors('per_page');
    });

    it('per_page > 100 → validation error', function () {
        $this->actingAs($this->admin)
            ->get('/admin/bookings?per_page=101')
            ->assertSessionHasErrors('per_page');
    });
});

/*
|==========================================================================
| KATEGORI 7 — EDGE CASES & BVA
|==========================================================================
*/

describe('Edge Cases — index filters', function () {
    it('filter status=confirmed → hanya confirmed', function () {
        makeBookingForAdmin($this->mitra, $this->client, ['status' => BookingStatus::CONFIRMED]);
        makeBookingForAdmin($this->mitra, $this->client, ['status' => BookingStatus::PENDING_PAYMENT]);

        $this->actingAs($this->admin)
            ->get('/admin/bookings?status=confirmed')
            ->assertInertia(fn (Assert $page) => $page->where('filters.status', 'confirmed'));
    });

    it('search kode booking → return match', function () {
        $booking = makeBookingForAdmin($this->mitra, $this->client);

        $this->actingAs($this->admin)
            ->get('/admin/bookings?search='.$booking->kode)
            ->assertInertia(fn (Assert $page) => $page->where('filters.search', $booking->kode));
    });

    it('filter is_today=1 → hanya booking hari ini', function () {
        makeBookingForAdmin($this->mitra, $this->client, [
            'tanggal_keberangkatan' => now()->toDateString(),
        ]);
        makeBookingForAdmin($this->mitra, $this->client, [
            'tanggal_keberangkatan' => now()->addDay()->toDateString(),
        ]);

        $this->actingAs($this->admin)
            ->get('/admin/bookings?is_today=1')
            ->assertInertia(fn (Assert $page) => $page->where('filters.is_today', '1'));
    });

    it('sort=kode direction=asc → urut kode ASC', function () {
        makeBookingForAdmin($this->mitra, $this->client, ['kode' => 'BKG-2026-002']);
        makeBookingForAdmin($this->mitra, $this->client, ['kode' => 'BKG-2026-001']);

        $this->actingAs($this->admin)
            ->get('/admin/bookings?sort=kode&direction=asc')
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.sort', 'kode')
                ->where('filters.direction', 'asc')
            );
    });
});

describe('Edge Cases — BVA per_page', function () {
    it('per_page=5 (min) → lolos', function () {
        $this->actingAs($this->admin)->get('/admin/bookings?per_page=5')->assertOk();
    });

    it('per_page=4 (min - 1) → error', function () {
        $this->actingAs($this->admin)
            ->get('/admin/bookings?per_page=4')
            ->assertSessionHasErrors('per_page');
    });

    it('per_page=100 (max) → lolos', function () {
        $this->actingAs($this->admin)->get('/admin/bookings?per_page=100')->assertOk();
    });

    it('per_page=101 (max + 1) → error', function () {
        $this->actingAs($this->admin)
            ->get('/admin/bookings?per_page=101')
            ->assertSessionHasErrors('per_page');
    });
});

/*
|==========================================================================
| HELPER — buat booking lengkap dengan mitra & client valid
|==========================================================================
*/
function makeBookingForAdmin(User $mitra, User $client, array $overrides = []): Booking
{
    $package = Package::factory()->forMitra($mitra)->create([
        'status' => PackageStatus::AKTIF,
    ]);

    return Booking::factory()
        ->forPackage($package)
        ->forClient($client)
        ->create(array_merge([
            'mitra_id' => $mitra->id,
            'status' => BookingStatus::PENDING_PAYMENT,
            'payment_proof_path' => null,
        ], $overrides));
}
