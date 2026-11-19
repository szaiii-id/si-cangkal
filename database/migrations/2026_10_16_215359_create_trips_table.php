<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trips', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 50)->unique();

            $table->foreignId('booking_id')->unique()->constrained('bookings')->restrictOnDelete();
            $table->foreignId('package_id')->constrained('packages')->restrictOnDelete();

            // Relasi Mitra (1 Trip = 1 Mitra)
            $table->foreignId('mitra_id')->constrained('users')->restrictOnDelete();

            $table->jsonb('snapshot_package');
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->unsignedSmallInteger('jumlah_peserta');
            $table->string('timezone', 50);
            $table->string('status', 20)->default('scheduled');

            $table->timestamps();

            $table->index(['mitra_id', 'status']);
            $table->index(['mitra_id', 'tanggal_mulai']);
            $table->index('status');
            $table->index('tanggal_mulai');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trips');
    }
};
