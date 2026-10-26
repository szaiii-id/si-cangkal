<?php

namespace App\Repositories\Contracts;

use App\Models\Booking;
use App\Models\Payout;

/**
 * Contract untuk payment gateway.
 *
 * Implementasi bisa Xendit, Midtrans, OVO, dsb.
 * Service layer (BookingService, PayoutService) HANYA bergantung
 * pada interface ini, bukan implementasi konkret.
 */
interface PaymentGatewayInterface
{
    /**
     * Buat invoice pembayaran untuk booking (Alur Redirect).
     *
     * Method ini digunakan untuk alur Invoice Redirect.
     * Response yang diharapkan berisi `invoice_url` yang akan
     * digunakan untuk me-redirect client ke halaman Xendit.
     *
     * @return array<string, mixed> Response mentah dari gateway.
     *
     * @throws \RuntimeException Jika gagal.
     */
    public function createInvoice(Booking $booking): array;

    /**
     * Kirim payout (pencairan) ke rekening mitra.
     *
     * @return array<string, mixed> Response mentah dari gateway.
     *
     * @throws \RuntimeException Jika gagal.
     */
    public function createPayout(Payout $payout): array;
}