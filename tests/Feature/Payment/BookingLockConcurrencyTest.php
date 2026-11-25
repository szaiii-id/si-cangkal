<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Package;
use App\Models\User;
use App\Repositories\Contracts\BookingRepositoryInterface;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

describe('konsistensi konkuren booking', function () {
    it('mencegah dua transaksi mengunci baris booking yang sama bersamaan', function () {
        config([
            'database.connections.pgsql_race' => config('database.connections.pgsql'),
        ]);

        $other = DB::connection('pgsql_race');
        $other->statement("SET lock_timeout = '300ms'");

        // Data dibuat pada koneksi lain dan sudah ter-commit, sehingga
        // kedua koneksi melihat baris yang sama. Baris dibersihkan di finally
        // supaya tidak mencemari test lain.
        $mitra = $client = $package = $booking = null;

        try {
            $mitra = User::factory()->connection('pgsql_race')->mitra()->create([
                'must_change_password' => false,
            ]);
            $client = User::factory()->connection('pgsql_race')->client()->create([
                'must_change_password' => false,
            ]);
            $package = Package::factory()->connection('pgsql_race')
                ->forMitra($mitra)
                ->aktif()
                ->create();

            /** @var Booking $booking */
            $booking = Booking::factory()->connection('pgsql_race')
                ->forPackage($package)
                ->forClient($client)
                ->create(['status' => BookingStatus::PENDING_PAYMENT]);

            DB::transaction(function () use ($booking, $other): void {
                $locked = app(BookingRepositoryInterface::class)
                    ->findByIdForUpdate($booking->id);

                expect($locked)->not->toBeNull();

                expect(fn () => $other->table('bookings')
                    ->where('id', $booking->id)
                    ->lockForUpdate()
                    ->first())->toThrow(QueryException::class);
            });
        } finally {
            // RefreshDatabase membuka transaksi pada koneksi default. Lock
            // baru dilepas setelah transaksi itu di-commit. Tidak ada data
            // default yang dibuat di test ini, jadi aman di-commit lebih awal.
            DB::commit();

            if ($booking !== null) {
                $other->table('bookings')->where('id', $booking->id)->delete();
            }
            if ($package !== null) {
                $other->table('packages')->where('id', $package->id)->delete();
            }
            if ($client !== null) {
                $other->table('users')->where('id', $client->id)->delete();
            }
            if ($mitra !== null) {
                $other->table('users')->where('id', $mitra->id)->delete();
            }
        }
    });
});
