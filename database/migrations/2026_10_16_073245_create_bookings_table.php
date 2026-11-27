<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('kode', 50)->unique();

            $table->foreignId('package_id')->constrained('packages')->restrictOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->restrictOnDelete();

            // Relasi Mitra (Denormalisasi)
            $table->foreignUuid('mitra_id')->constrained('users')->restrictOnDelete();

            $table->date('tanggal_keberangkatan');
            $table->unsignedSmallInteger('jumlah_peserta');
            $table->decimal('total_harga', 12, 2);

            $table->string('status', 30)->default('pending_payment');

            // Pembayaran Manual
            $table->string('payment_proof_path')->nullable()->after('status');
            $table->timestamp('payment_verified_at')->nullable()->after('payment_proof_path');
            $table->text('payment_reject_reason')->nullable()->after('payment_verified_at');

            $table->timestamp('expired_at')->nullable();
            $table->text('catatan')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['mitra_id', 'status']);
            $table->index(['mitra_id', 'created_at']);
            $table->index('status');
            $table->index('user_id');
            $table->index('package_id');
            $table->index('expired_at');
            $table->index('tanggal_keberangkatan');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
