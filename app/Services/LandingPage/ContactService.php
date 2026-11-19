<?php

namespace App\Services\LandingPage;

use Illuminate\Support\Facades\Mail;

class ContactService
{
    /**
     * Kirim pesan kontak ke email admin.
     *
     * @param  array<string, mixed>  $data
     */
    public function send(array $data): void
    {
        $recipient = config('mail.contact_recipient');

        Mail::raw(
            $this->buildBody($data),
            function ($message) use ($data, $recipient) {
                $message->to($recipient)
                    ->replyTo($data['email'], $data['nama'])
                    ->subject('Pesan Kontak Baru — '.$data['nama']);
            }
        );
    }

    /**
     * Susun isi email dalam format teks.
     *
     * @param  array<string, mixed>  $data
     */
    protected function buildBody(array $data): string
    {
        return <<<TEXT
        Pesan kontak baru dari halaman landing SI-CANGKAL.

        Nama    : {$data['nama']}
        Email   : {$data['email']}
        Waktu   : {$this->formatTime()}

        Pesan:
        {$data['pesan']}

        ---
        Email ini dikirim otomatis dari form kontak SI-CANGKAL.
        Balas langsung ke email pengirim untuk merespons.
        TEXT;
    }

    /**
     * Format waktu pengiriman.
     *
     *   FIX: pakai timezone Asia/Makassar (WITA) agar label WITA
     * konsisten dengan waktu yang ditampilkan. Sebelumnya `now()`
     * memakai app.timezone (default UTC) → jam UTC + label WIB
     * = salah 7-8 jam.
     *
     * Konteks: konsisten dengan User::DEFAULT_TIMEZONE & Timezone::DEFAULT
     * (Asia/Makassar) yang dipakai di seluruh aplikasi.
     */
    protected function formatTime(): string
    {
        return now()->timezone('Asia/Makassar')->translatedFormat('l, d F Y H:i').' WITA';
    }
}
