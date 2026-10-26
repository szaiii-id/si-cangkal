<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Tabel `bookings` — pesanan client untuk sebuah paket wisata.
     *
     * Alur status:
     *   pending_payment → payment_verified → confirmed → in_progress → completed
     *                                                       ↘ cancelled
     *
     * Catatan timezone:
     * - `tanggal_keberangkatan` adalah DATE murni (netral timezone).
     * - `expired_at` adalah TIMESTAMP UTC (created_at + 24 jam).
     * - `created_at`, `updated_at`, `deleted_at` UTC.
     *
     * Catatan soft delete:
     * - Booking yang sudah ada tidak boleh dihapus permanen.
     * - Soft delete untuk audit trail.
     *
     * Catatan enum:
     * - `status` disimpan sebagai string + PHP Enum cast di model.
     *
     * Catatan Xendit:
     * - `xendit_invoice_id` & `xendit_invoice_url` disimpan untuk tracking
     *   dan redirect client ke halaman pembayaran Xendit.
     */
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();

            // Identitas
            $table->string('kode', 50)->unique();         // BKG-2026-001

            // Relasi
            $table->foreignId('package_id')
                ->constrained('packages')
                ->restrictOnDelete();

            $table->foreignId('user_id')                  // client
                ->constrained('users')
                ->restrictOnDelete();

            // Detail booking
            $table->date('tanggal_keberangkatan');        // DATE murni
            $table->unsignedSmallInteger('jumlah_peserta');
            $table->decimal('total_harga', 12, 2);

            // Status
            $table->string('status', 30)->default('pending_payment');
            // pending_payment | payment_verified | confirmed | in_progress | completed | cancelled

            // Menyimpan ID Invoice dari Xendit & URL untuk redirect client
            $table->string('xendit_invoice_id')->nullable()->after('status');
            $table->string('xendit_invoice_url', 500)->nullable()->after('xendit_invoice_id');

            // Auto-expire
            $table->timestamp('expired_at')->nullable();  // UTC, created_at + 24h

            // Catatan client
            $table->text('catatan')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Index untuk query umum
            $table->index('status');
            $table->index('user_id');
            $table->index('package_id');
            $table->index('expired_at');
            $table->index('tanggal_keberangkatan');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};