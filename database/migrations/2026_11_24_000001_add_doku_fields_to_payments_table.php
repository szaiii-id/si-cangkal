<?php

use App\Enums\PaymentGateway;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->string('gateway', 20)
                ->default(PaymentGateway::MANUAL->value)
                ->after('metode');

            $table->string('gateway_order_id', 100)
                ->nullable()
                ->after('gateway');

            $table->string('gateway_request_id', 128)
                ->nullable()
                ->after('gateway_order_id');

            $table->string('gateway_status', 40)
                ->nullable()
                ->after('gateway_request_id');

            $table->jsonb('gateway_payload')
                ->nullable()
                ->after('gateway_status');

            $table->timestamp('paid_at')->nullable()->after('verified_at');
            $table->timestamp('settled_at')->nullable()->after('paid_at');
            $table->timestamp('released_at')->nullable()->after('settled_at');

            $table->index(['gateway', 'gateway_status']);
            $table->unique('gateway_request_id');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropUnique(['gateway_request_id']);
            $table->dropIndex(['gateway', 'gateway_status']);

            $table->dropColumn([
                'gateway',
                'gateway_order_id',
                'gateway_request_id',
                'gateway_status',
                'gateway_payload',
                'paid_at',
                'settled_at',
                'released_at',
            ]);
        });
    }
};
