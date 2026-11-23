<?php

use App\Models\Booking;
use App\Models\Package;
use App\Models\Review;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    if (method_exists($this, 'withoutVite')) {
        $this->withoutVite();
    }

    config(['inertia.testing.ensure_pages_exist' => false]);

    $this->client = User::factory()->client()->create([
        'name' => 'Client Reviewer',
        'must_change_password' => false,
    ]);

    $this->mitra = User::factory()->mitra()->create([
        'name' => 'Mitra Review',
        'must_change_password' => false,
    ]);

    $this->package = Package::factory()->forMitra($this->mitra)->aktif()->create([
        'nama' => 'Paket Review Test',
    ]);

    $this->booking = Booking::factory()
        ->forPackage($this->package)
        ->forClient($this->client)
        ->confirmed()
        ->create();

    Trip::factory()->forBooking($this->booking)->completed()->create();
});

function reviewPayload(array $overrides = []): array
{
    return array_merge([
        'rating' => 5,
        'komentar' => 'Perjalanan sangat menyenangkan dan teratur.',
    ], $overrides);
}

describe('Contract — form review', function () {
    it('GET /client/bookings/{booking}/review render Client/Reviews/Create saat trip selesai', function () {
        $this->actingAs($this->client)
            ->get("/client/bookings/{$this->booking->id}/review")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Client/Reviews/Create')
                ->where('booking.id', $this->booking->id)
                ->where('booking.kode', $this->booking->kode)
                ->where('booking.paket', 'Paket Review Test')
            );
    });

    it('nama route = client.bookings.review.create / .store', function () {
        expect(route('client.bookings.review.create', $this->booking))
            ->toBe(url("/client/bookings/{$this->booking->id}/review"));
    });
});

describe('Security — authorization', function () {
    it('client lain → 403', function () {
        $lain = User::factory()->client()->create(['must_change_password' => false]);

        $this->actingAs($lain)
            ->get("/client/bookings/{$this->booking->id}/review")
            ->assertForbidden();

        $this->actingAs($lain)
            ->post("/client/bookings/{$this->booking->id}/review", reviewPayload())
            ->assertForbidden();
    });

    it('trip masih active → 403', function () {
        $this->booking->trip->update(['status' => 'active']);

        $this->actingAs($this->client)
            ->get("/client/bookings/{$this->booking->id}/review")
            ->assertForbidden();
    });

    it('booking tanpa trip → 403', function () {
        $this->booking->trip->delete();

        $this->actingAs($this->client)
            ->get("/client/bookings/{$this->booking->id}/review")
            ->assertForbidden();
    });

    it('booking sudah direview → 403', function () {
        Review::factory()->forBooking($this->booking)->create();

        $this->actingAs($this->client)
            ->get("/client/bookings/{$this->booking->id}/review")
            ->assertForbidden();
    });

    it('guest di-redirect ke login', function () {
        $this->get("/client/bookings/{$this->booking->id}/review")
            ->assertRedirect(route('login'));
    });
});

describe('Validation', function () {
    it('rating 0 ditolak', function () {
        $this->actingAs($this->client)
            ->post("/client/bookings/{$this->booking->id}/review", reviewPayload(['rating' => 0]))
            ->assertRedirect()
            ->assertSessionHasErrors('rating');

        expect(Review::count())->toBe(0);
    });

    it('rating 6 ditolak', function () {
        $this->actingAs($this->client)
            ->post("/client/bookings/{$this->booking->id}/review", reviewPayload(['rating' => 6]))
            ->assertRedirect()
            ->assertSessionHasErrors('rating');
    });

    it('komentar 5 karakter ditolak', function () {
        $this->actingAs($this->client)
            ->post("/client/bookings/{$this->booking->id}/review", reviewPayload(['komentar' => 'Bagus']))
            ->assertRedirect()
            ->assertSessionHasErrors('komentar');
    });

    it('komentar dengan spasi di pinggir tetap diukur setelah trim', function () {
        $this->actingAs($this->client)
            ->post("/client/bookings/{$this->booking->id}/review", reviewPayload([
                'komentar' => '     Bagus     ',
            ]))
            ->assertRedirect()
            ->assertSessionHasErrors('komentar');
    });
});

describe('Store — data & efek samping', function () {
    it('POST valid menyimpan review, redirect ke detail booking, dan flash status', function () {
        $this->actingAs($this->client)
            ->post("/client/bookings/{$this->booking->id}/review", reviewPayload())
            ->assertRedirect(route('client.bookings.show', $this->booking))
            ->assertSessionHas('status', 'Terima kasih! Review kamu sudah terkirim.');

        $this->assertDatabaseHas('reviews', [
            'booking_id' => $this->booking->id,
            'package_id' => $this->package->id,
            'user_id' => $this->client->id,
            'rating' => 5,
            'komentar' => 'Perjalanan sangat menyenangkan dan teratur.',
        ]);
    });

    it('POST valid membuat notifikasi review_baru untuk mitra', function () {
        $this->actingAs($this->client)
            ->post("/client/bookings/{$this->booking->id}/review", reviewPayload())
            ->assertRedirect();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->mitra->id,
            'tipe' => 'review_baru',
        ]);
    });

    it('POST dua kali hanya menyisakan satu baris review', function () {
        $url = "/client/bookings/{$this->booking->id}/review";

        $this->actingAs($this->client)->post($url, reviewPayload())->assertRedirect();
        $this->actingAs($this->client)->post($url, reviewPayload())->assertForbidden();

        expect(Review::where('booking_id', $this->booking->id)->count())->toBe(1);
    });

    it('email client tidak terverifikasi tidak bisa mengirim review', function () {
        $this->client->forceFill(['email_verified_at' => null])->save();

        $this->actingAs($this->client)
            ->post("/client/bookings/{$this->booking->id}/review", reviewPayload())
            ->assertRedirect(route('verification.notice'));
    });
});

describe('Integritas database — garda terakhir', function () {
    it('unique constraint menolak duplikat booking_id', function () {
        Review::factory()->forBooking($this->booking)->create();

        expect(fn () => Review::factory()->forBooking($this->booking)->create())
            ->toThrow(QueryException::class);
    });

    it('CHECK constraint menolak rating di luar 1–5', function () {
        $insert = fn (int $rating) => DB::table('reviews')->insert([
            'booking_id' => $this->booking->id,
            'package_id' => $this->package->id,
            'user_id' => $this->client->id,
            'rating' => $rating,
            'komentar' => 'Komentar pengujian constraint rating.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        expect(fn () => $insert(0))->toThrow(QueryException::class)
            ->and(fn () => $insert(6))->toThrow(QueryException::class);
    });
});
