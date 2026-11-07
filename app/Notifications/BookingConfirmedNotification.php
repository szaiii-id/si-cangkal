<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notifikasi: booking telah dikonfirmasi (pembayaran diverifikasi mitra).
 *
 * Dikirim ke client setelah event `BookingConfirmed` diproses.
 *
 * Channel:
 * - `mail` : email pemberitahuan ke client.
 * - `database` : catatan audit sekunder di tabel `notifications` Laravel
 *   standar. Tidak ditampilkan di UI Titiktemu (UI memakai `AppNotification`).
 *
 * Catatan penting:
 * - Notifikasi in-app yang tampil di UI TIDAK berasal dari sini. Itu dikirim
 *   lewat `NotificationService::create()` di `BookingPaymentService`.
 * - Pesan email sengaja menyebut "E-Ticket sedang diproses", bukan
 *   "E-Ticket siap diunduh", karena E-Ticket di-generate di queue terpisah
 *   dan bisa selesai beberapa detik setelah email terkirim.
 */
final class BookingConfirmedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Booking $booking,
    ) {}

    /**
     * Channel pengiriman.
     *
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Representasi email.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $booking = $this->booking;
        $clientName = $booking->client?->name ?? 'Pelanggan';
        $packageName = $booking->package?->nama ?? 'Paket Wisata';
        $mitraName = $booking->mitra?->name ?? 'Mitra';
        $totalHarga = 'Rp '.number_format((float) $booking->total_harga, 0, ',', '.');
        $tanggal = $booking->tanggal_keberangkatan?->format('d M Y') ?? '-';

        return (new MailMessage)
            ->subject("Booking {$booking->kode} Dikonfirmasi")
            ->greeting("Halo {$clientName},")
            ->line('Pembayaran Anda telah diverifikasi oleh mitra.')
            ->line("Booking **{$booking->kode}** untuk paket **{$packageName}** telah dikonfirmasi.")
            ->line("Mitra penyelenggara: {$mitraName}")
            ->line("Tanggal keberangkatan: {$tanggal}")
            ->line("Jumlah peserta: {$booking->jumlah_peserta} orang")
            ->line("Total pembayaran: {$totalHarga}")
            ->line('E-Ticket Anda sedang diproses dan akan tersedia di halaman detail booking dalam beberapa saat.')
            ->action('Lihat Detail Booking', route('client.bookings.show', $booking->id))
            ->line('Terima kasih telah menggunakan Titiktemu.');
    }

    /**
     * Representasi array untuk channel `database`.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'tipe' => 'booking_confirmed',
            'booking_id' => $this->booking->id,
            'booking_kode' => $this->booking->kode,
            'package_id' => $this->booking->package_id,
            'mitra_id' => $this->booking->mitra_id,
            'total_harga' => (float) $this->booking->total_harga,
        ];
    }
}