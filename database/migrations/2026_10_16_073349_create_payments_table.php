<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Tabel `payments` — rekam jejak pembayaran via Xendit.
     *
     * Alur:
     * 1. Client booking → sistem generate Invoice Xendit.
     * 2. Client bayar via halaman Xendit.
     * 3. Xendit kirim Webhook → sistem otomatis insert record di sini.
     * 4. Booking status berubah jadi `payment_verified`.
     *
     * Catatan:
     * - Full otomatis via Xendit, tidak ada lagi upload manual.
     * - ON DELETE CASCADE: kalau booking dihapus, payment ikut terhapus.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('booking_id')
                ->constrained('bookings')
                ->cascadeOnDelete();

            // Jumlah yang dibayar (dalam Rupiah)
            $table->decimal('jumlah', 12, 2);

            // Metode pembayaran (sekarang hanya Xendit)
            $table->string('metode', 30)->default('xendit'); 

            // Verifikasi (Diisi otomatis oleh Webhook Xendit)
            // nullable karena bisa saja webhook belum masuk saat record dibuat
            $table->foreignId('verified_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('verified_at')->nullable();

            // Catatan admin / log dari Xendit (misal: Invoice ID)
            $table->text('catatan')->nullable();

            $table->timestamps();

            // Index
            $table->index('booking_id');
            $table->index('verified_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};