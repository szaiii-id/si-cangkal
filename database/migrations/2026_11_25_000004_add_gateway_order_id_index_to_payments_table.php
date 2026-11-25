<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Webhook mencocokkan Payment lewat (gateway, gateway_order_id).
     * Tanpa index, tiap webhook payment.capture/failure melakukan scan
     * tabel payments yang terus bertambah.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->index(['gateway', 'gateway_order_id']);
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropIndex(['gateway', 'gateway_order_id']);
        });
    }
};
