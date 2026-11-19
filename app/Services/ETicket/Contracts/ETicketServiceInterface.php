<?php

namespace App\Services\ETicket\Contracts;

use App\Models\ETicket;
use App\Models\Trip;

/**
 * Kontrak untuk layanan E-Ticket.
 *
 * Interface ini memungkinkan:
 * - Mocking di test tanpa terhalang `final class`.
 * - Dependency Inversion: controller depend on abstraction, bukan concrete.
 * - Implementasi alternatif (mis. testing, mock, atau provider berbeda).
 */
interface ETicketServiceInterface
{
    /**
     * Generate E-Ticket untuk sebuah trip.
     *
     * Wajib idempoten: implementasi harus mencegah duplikat
     * saat dipanggil berulang untuk booking yang sama
     * (mis. job retry).
     *
     * @throws \Throwable
     */
    public function generateForTrip(Trip $trip): ETicket;

    /**
     * Cari E-Ticket berdasarkan UUID.
     */
    public function findByUuid(string $uuid): ?ETicket;

    /**
     * Ambil path absolut file PDF E-Ticket.
     *
     * @return string|null Null bila file tidak ditemukan.
     */
    public function pdfAbsolutePath(ETicket $ticket): ?string;
}
