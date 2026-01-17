<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('daily_trend', function (Blueprint $table) {
            // Strike price values
            $table->decimal('atm_ce', 12, 2)->nullable()->after('market_type');
            $table->decimal('atm_pe', 12, 2)->nullable()->after('atm_ce');

            // ATM prices (close / high / low)
            $table->decimal('atm_ce_close', 12, 2)->nullable()->after('atm_pe');
            $table->decimal('atm_pe_close', 12, 2)->nullable()->after('atm_ce_close');

            $table->decimal('atm_ce_high', 12, 2)->nullable()->after('atm_pe_close');
            $table->decimal('atm_pe_high', 12, 2)->nullable()->after('atm_ce_high');

            $table->decimal('atm_ce_low', 12, 2)->nullable()->after('atm_pe_high');
            $table->decimal('atm_pe_low', 12, 2)->nullable()->after('atm_ce_low');

            // ATM pivot levels (R/S)
            $table->decimal('atm_r_1', 12, 2)->nullable()->after('atm_pe_low');
            $table->decimal('atm_r_2', 12, 2)->nullable()->after('atm_r_1');
            $table->decimal('atm_r_3', 12, 2)->nullable()->after('atm_r_2');

            $table->decimal('atm_s_1', 12, 2)->nullable()->after('atm_r_3');
            $table->decimal('atm_s_2', 12, 2)->nullable()->after('atm_s_1');
            $table->decimal('atm_s_3', 12, 2)->nullable()->after('atm_s_2');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('daily_trend', function (Blueprint $table) {
            $table->dropColumn([
                'atm_ce',
                'atm_pe',
                'atm_ce_close',
                'atm_pe_close',
                'atm_ce_high',
                'atm_pe_high',
                'atm_ce_low',
                'atm_pe_low',
                'atm_r_1',
                'atm_r_2',
                'atm_r_3',
                'atm_s_1',
                'atm_s_2',
                'atm_s_3',
            ]);
        });
    }
};
