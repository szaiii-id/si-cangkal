<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mitra_bank_accounts', function (Blueprint $table): void {
            $table->string('doku_profile_id', 128)->nullable()->after('settlement_verified_at');
            $table->string('doku_account_no', 32)->nullable()->after('doku_profile_id');
            $table->string('doku_split_rule_id', 64)->nullable()->after('doku_account_no');
            $table->timestamp('doku_registered_at')->nullable()->after('doku_split_rule_id');
        });
    }

    public function down(): void
    {
        Schema::table('mitra_bank_accounts', function (Blueprint $table): void {
            $table->dropColumn([
                'doku_profile_id',
                'doku_account_no',
                'doku_split_rule_id',
                'doku_registered_at',
            ]);
        });
    }
};
