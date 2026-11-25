<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bukti transfer untuk refund manual.
     *
     * Mitra wajib mengunggah bukti sebelum refund dapat berstatus SUCCEEDED.
     * Admin hanya melihat indikator bukti (monitoring read-only).
     */
    public function up(): void
    {
        Schema::table('refunds', function (Blueprint $table): void {
            $table->string('proof_path', 255)->nullable()->after('catatan');
            $table->timestamp('proof_uploaded_at')->nullable()->after('proof_path');
            $table->foreignId('proof_uploaded_by')
                ->nullable()
                ->after('proof_uploaded_at')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('refunds', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('proof_uploaded_by');
            $table->dropColumn(['proof_path', 'proof_uploaded_at']);
        });
    }
};
