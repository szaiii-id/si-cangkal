<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notifikasi: bukti transfer baru telah diunggah.
 *
 * Dikirim ke mitra saat:
 * - Client mengunggah bukti transfer untuk booking milik mitra tersebut.
 * - Mitra mengunggah bukti transfer atas nama client (pesanan manual).
 *
 * Channel:
 * - `mail` : email pemberitahuan ke mitra.
 * - `database` : catatan audit sekunder di tabel `notifications` Laravel
 *   standar. Tidak ditampilkan di UI Titiktemu (UI memakai `AppNotification`).
 *
 * Catatan penting:
 * - Notifikasi in-app yang tampil di UI mitra TETAP dikirim lewat
 *   `NotificationService::create()` di `BookingPaymentService::uploadProof`.
 *   Notifikasi ini HANYA untuk channel mail + audit sekunder.
 * - Notifikasi TIDAK menyertakan tautan langsung ke file bukti transfer,
 *   karena file disimpan di disk `local` (S-09) dan hanya bisa diakses
 *   lewat route privat ber-otorisasi. Mitra harus login dulu.
 */
final class PaymentUploadedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Booking $booking,
        public readonly string $uploadedByRole,
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
        $mitraName = $booking->mitra?->name ?? 'Mitra';
        $clientName = $booking->client?->name ?? 'Client';
        $packageName = $booking->package?->nama ?? 'Paket Wisata';
        $totalHarga = 'Rp '.number_format((float) $booking->total_harga, 0, ',', '.');

        $uploader = $this->uploadedByRole === 'mitra' ? 'Mitra' : 'Client';

        return (new MailMessage)
            ->subject("Bukti Transfer Baru untuk Booking {$booking->kode}")
            ->greeting("Halo {$mitraName},")
            ->line("{$uploader} telah mengunggah bukti transfer untuk booking berikut.")
            ->line("Kode Booking: **{$booking->kode}**")
            ->line("Paket: {$packageName}")
            ->line("Client: {$clientName}")
            ->line("Jumlah Peserta: {$booking->jumlah_peserta} orang")
            ->line("Total Tagihan: {$totalHarga}")
            ->line('Silakan login ke dashboard mitra untuk memverifikasi bukti transfer tersebut.')
            ->action('Verifikasi Pembayaran', route('mitra.bookings.show', $booking->id))
            ->line('Terima kasih.');
    }

    /**
     * Representasi array untuk channel `database`.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'tipe' => 'payment_proof_uploaded',
            'booking_id' => $this->booking->id,
            'booking_kode' => $this->booking->kode,
            'client_id' => $this->booking->user_id,
            'mitra_id' => $this->booking->mitra_id,
            'uploaded_by_role' => $this->uploadedByRole,
        ];
    }
}