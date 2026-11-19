<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();

            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();

            // Relasi Mitra (Denormalisasi)
            $table->foreignId('mitra_id')->nullable()->after('booking_id')->constrained('users')->restrictOnDelete();

            $table->decimal('amount', 12, 2);
            $table->string('currency', 10)->default('IDR');
            $table->string('reason', 30);
            $table->string('status', 20)->default('PENDING');

            $table->string('failure_code', 100)->nullable();
            $table->decimal('refund_fee_amount', 12, 2)->nullable();

            // Diganti dari initiated_by
            $table->foreignId('initiated_by_mitra')
                ->nullable()
                ->after('refund_fee_amount')
                ->constrained('users')
                ->nullOnDelete();

            $table->text('catatan')->nullable();
            $table->jsonb('raw_response')->nullable();
            $table->jsonb('raw_webhook')->nullable();

            $table->timestamps();

            $table->index(['mitra_id', 'status']);
            $table->index(['booking_id', 'status']);
            $table->index('status');
            $table->index('initiated_by_mitra');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};
