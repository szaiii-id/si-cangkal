<?php

namespace Database\Seeders;

use App\Enums\RefundReason;
use App\Enums\RefundStatus;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use Illuminate\Database\Seeder;

class RefundSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@sicangkal.test')->first();

        // Refund untuk BKG-010 (CANCELLED, sudah bayar)
        $this->seedRefund(
            'BKG-'.now()->year.'-010',
            RefundReason::REQUESTED_BY_CUSTOMER,
            RefundStatus::PENDING,
            700000, // 2 peserta × Rp 350.000
            'Client minta refund karena urusan mendadak.',
        );

        // Refund untuk BKG-011 (CANCELLED karena expired, sudah pernah bayar)
        $this->seedRefund(
            'BKG-'.now()->year.'-011',
            RefundReason::CANCELLATION,
            RefundStatus::SUCCEEDED,
            1500000, // 2 peserta × Rp 750.000
            'Auto-cancel karena expired. Refund sudah ditransfer balik.',
        );

        $this->command->info(' RefundSeeder selesai (2 refund).');
    }

    protected function seedRefund(
        string $bookingKode,
        RefundReason $reason,
        RefundStatus $status,
        float $amount,
        string $catatan,
    ): void {
        $booking = Booking::where('kode', $bookingKode)->first();

        if (! $booking) {
            $this->command->warn("  Booking {$bookingKode} tidak ditemukan.");

            return;
        }

        $refund = Refund::updateOrCreate(
            ['booking_id' => $booking->id],
            [
                'mitra_id' => $booking->mitra_id,
                'amount' => $amount,
                'currency' => 'IDR',
                'reason' => $reason->value,
                'status' => $status->value,
                'initiated_by_mitra' => $booking->mitra_id,
                'catatan' => $catatan,
            ]
        );

        // Jika SUCCEEDED, catat Payment negatif
        if ($status === RefundStatus::SUCCEEDED) {
            Payment::updateOrCreate(
                ['booking_id' => $booking->id, 'jumlah' => -$amount],
                [
                    'metode' => 'transfer_manual',
                    'verified_by' => $booking->mitra_id,
                    'verified_at' => now(),
                    'catatan' => "Refund berhasil: {$catatan}",
                ]
            );
        }

        $this->command->line(" Refund dibuat: {$bookingKode} ({$status->value})");
    }
}
