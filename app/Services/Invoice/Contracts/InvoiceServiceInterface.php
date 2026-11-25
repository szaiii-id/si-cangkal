<?php

namespace App\Services\Invoice\Contracts;

use App\Models\Booking;

interface InvoiceServiceInterface
{
    public function pdfForBooking(Booking $booking): string;

    public function fileName(Booking $booking): string;
}
