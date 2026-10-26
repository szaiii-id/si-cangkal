<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Tabel `payouts` — pengajuan pencairan honor mitra.
     *
     * Catatan Xendit:
     * - `xendit_payout_id` & `xendit_payout_status` disimpan untuk tracking
     *   status transfer dari Xendit (PENDING, SUCCEEDED, FAILED).
     * - Kolom bank_tujuan, no_rekening, nama_pemilik tetap dipakai sebagai
     *   input untuk Xendit Payout API.
     */
    public function up(): void
    {
        Schema::create('payouts', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 50)->unique();
            $table->foreignId('mitra_id')->constrained('users')->restrictOnDelete();
            $table->decimal('jumlah', 12, 2);

            // Info rekening tujuan (dipakai untuk Xendit Payout API)
            $table->string('bank_tujuan', 100);
            $table->string('no_rekening', 50);
            $table->string('nama_pemilik', 255);

            // Status internal (pending | approved | paid | rejected)
            $table->string('status', 20)->default('pending');

            // Menyimpan ID Disbursement & status dari Xendit
            $table->string('xendit_payout_id')->nullable()->after('status');
            $table->string('xendit_payout_status')->nullable()->after('xendit_payout_id');

            $table->timestamp('requested_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();
            $table->index('status');
            $table->index('mitra_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payouts');
    }
};