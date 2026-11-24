<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('refunds', function (Blueprint $table): void {
            $table->string('gateway_refund_id', 100)
                ->nullable()
                ->after('refund_fee_amount');

            $table->string('gateway_status', 40)
                ->nullable()
                ->after('gateway_refund_id');

            $table->index('gateway_status');
        });
    }

    public function down(): void
    {
        Schema::table('refunds', function (Blueprint $table): void {
            $table->dropIndex(['gateway_status']);
            $table->dropColumn(['gateway_refund_id', 'gateway_status']);
        });
    }
};
