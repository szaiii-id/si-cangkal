<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ulasan client untuk paket wisata.
     *
     * Satu booking hanya boleh punya satu review (unique booking_id).
     * Rating dijaga juga di level DB lewat CHECK constraint PostgreSQL,
     * supaya ini tetap aman kalau ada penulisan di luar aplikasi.
     */
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->foreignId('package_id')->constrained('packages')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('komentar');
            $table->timestamps();

            // Satu booking = satu review.
            $table->unique('booking_id');

            // Dipakai halaman katalog: urut ulasan terbaru per paket.
            $table->index(['package_id', 'created_at']);
        });

        DB::statement('ALTER TABLE reviews ADD CONSTRAINT reviews_rating_range CHECK (rating BETWEEN 1 AND 5)');
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
