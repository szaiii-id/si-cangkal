<?php

namespace App\Services\Booking;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\BookingParticipant;
use App\Models\Package;
use App\Models\User;
use App\Repositories\Contracts\BookingRepositoryInterface;
use App\Services\Notification\Contracts\NotificationDispatcherInterface;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Service pembuatan Booking (client & pesanan manual mitra).
 *
 * Hasil pemecahan BookingService (PRD 5.7). Bertanggung jawab HANYA
 * untuk membuat record Booking baru beserta peserta.
 *
 * FCM: Mengirim notifikasi "Pesanan Baru" ke Mitra saat Client
 * membuat pesanan (di method create).
 */
class BookingCreationService
{
    public function __construct(
        private readonly BookingRepositoryInterface $bookingRepository,
        private readonly NotificationDispatcherInterface $notificationDispatcher,
    ) {}

    /**
     * Buat booking baru dari client.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function create(Package $package, User $client, array $data): Booking
    {
        $this->validateBooking($package, $data);

        $booking = DB::transaction(function () use ($package, $client, $data): Booking {
            $totalHarga = $package->calculateTotalHarga((int) $data['jumlah_peserta']);
            $kode = $this->bookingRepository->generateKode((int) now()->year);
            $expiredAt = now()->addHours(24);

            $booking = $this->bookingRepository->create([
                'kode' => $kode,
                'package_id' => $package->id,
                'user_id' => $client->id,
                'mitra_id' => $package->mitra_id,
                'tanggal_keberangkatan' => $data['tanggal_keberangkatan'],
                'jumlah_peserta' => $data['jumlah_peserta'],
                'total_harga' => $totalHarga,
                'status' => BookingStatus::PENDING_PAYMENT->value,
                'expired_at' => $expiredAt,
                'catatan' => $data['catatan'] ?? null,
            ]);

            $this->insertParticipants($booking, $data['participants']);

            AuditLogger::log('booking.created', [
                'actor_id' => $client->id,
                'actor_role' => 'client',
                'booking_id' => $booking->id,
                'booking_kode' => $booking->kode,
                'package_id' => $package->id,
                'mitra_id' => $booking->mitra_id,
                'jumlah_peserta' => $booking->jumlah_peserta,
                'total_harga' => (float) $booking->total_harga,
                'expired_at' => $expiredAt->toIso8601String(),
            ]);

            return $booking;
        });

        try {
            $this->notifyMitraNewBooking($booking, $package, $client);
        } catch (Throwable $e) {
            Log::warning('BookingCreationService: gagal kirim notifikasi pesanan baru', [
                'booking_id' => $booking->id,
                'booking_kode' => $booking->kode,
                'mitra_id' => $package->mitra_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return $booking->load(['package', 'participants']);
    }

    /**
     * Buat pesanan manual oleh mitra (pelanggan offline / via WhatsApp).
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function createManual(
        Package $package,
        User $client,
        User $mitra,
        array $data,
    ): Booking {
        $this->validateBooking($package, $data);

        return DB::transaction(function () use ($package, $client, $mitra, $data): Booking {
            $totalHarga = $package->calculateTotalHarga((int) $data['jumlah_peserta']);
            $kode = $this->bookingRepository->generateKode((int) now()->year);

            $booking = $this->bookingRepository->create([
                'kode' => $kode,
                'package_id' => $package->id,
                'user_id' => $client->id,
                'mitra_id' => $mitra->id,
                'tanggal_keberangkatan' => $data['tanggal_keberangkatan'],
                'jumlah_peserta' => $data['jumlah_peserta'],
                'total_harga' => $totalHarga,
                'status' => BookingStatus::PENDING_PAYMENT->value,
                'expired_at' => null,
                'catatan' => $data['catatan'] ?? null,
            ]);

            $this->insertParticipants($booking, $data['participants']);

            AuditLogger::log('booking.created_manual', [
                'actor_id' => $mitra->id,
                'actor_role' => 'mitra',
                'booking_id' => $booking->id,
                'booking_kode' => $booking->kode,
                'client_id' => $client->id,
                'client_email' => $client->email,
                'package_id' => $package->id,
                'mitra_id' => $booking->mitra_id,
                'jumlah_peserta' => $booking->jumlah_peserta,
                'total_harga' => (float) $booking->total_harga,
            ]);

            return $booking->load(['package', 'participants']);
        });
    }

    private function notifyMitraNewBooking(Booking $booking, Package $package, User $client): void
    {
        if ($package->mitra === null) {
            return;
        }

        $this->notificationDispatcher->send(
            user: $package->mitra,
            tipe: 'booking_created',
            judul: 'Pesanan Baru Masuk',
            pesan: "Pesanan baru dari {$client->name} untuk paket {$package->nama} ({$booking->kode}).",
            data: [
                'booking_id' => $booking->id,
                'url' => "/mitra/bookings/{$booking->id}",
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function validateBooking(Package $package, array $data): void
    {
        if (! $package->isAktif()) {
            throw ValidationException::withMessages([
                'package' => ['Paket ini sedang tidak tersedia.'],
            ]);
        }

        $jumlahPeserta = (int) ($data['jumlah_peserta'] ?? 0);

        if ($jumlahPeserta < 1) {
            throw ValidationException::withMessages([
                'jumlah_peserta' => ['Jumlah peserta minimal 1 orang.'],
            ]);
        }

        if ($package->isRombongan() && $package->max_peserta !== null && $jumlahPeserta > $package->max_peserta) {
            throw ValidationException::withMessages([
                'jumlah_peserta' => [
                    "Jumlah peserta maksimal untuk paket rombongan ini adalah {$package->max_peserta} orang.",
                ],
            ]);
        }

        $participants = $data['participants'] ?? [];

        if (! is_array($participants) || count($participants) < $jumlahPeserta) {
            throw ValidationException::withMessages([
                'participants' => ['Data peserta kurang.'],
            ]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $participants
     */
    private function insertParticipants(Booking $booking, array $participants): void
    {
        foreach ($participants as $participant) {
            BookingParticipant::create([
                'booking_id' => $booking->id,
                'nama' => (string) $participant['nama'],
                'umur' => isset($participant['umur']) ? (int) $participant['umur'] : null,
                'no_hp' => $participant['no_hp'] ?? null,
                'catatan' => $participant['catatan'] ?? null,
            ]);
        }
    }
}
