<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jejak tambahan untuk event webhook.
     *
     `event` + `status` disimpan terpisah agar dedupe bisa membedakan
     transisi status (mis. refund.failed setelah refund.succeeded) tanpa
     harus mengurai payload JSON. `attempts`/`failed_at` dipakai saat job
     gagal permanen: penanda tidak dihapus, supaya Xendit retry tetap
     terdedupe dan operator bisa melihat event yang macet.
     */
    public function up(): void
    {
        Schema::table('webhook_events', function (Blueprint $table): void {
            $table->string('event', 64)->nullable()->after('request_id');
            $table->string('status', 32)->nullable()->after('event');
            $table->unsignedSmallInteger('attempts')->default(0)->after('payload');
            $table->timestamp('failed_at')->nullable()->after('attempts');
        });
    }

    public function down(): void
    {
        Schema::table('webhook_events', function (Blueprint $table): void {
            $table->dropColumn(['event', 'status', 'attempts', 'failed_at']);
        });
    }
};
