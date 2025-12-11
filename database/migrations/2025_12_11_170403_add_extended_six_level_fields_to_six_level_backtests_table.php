<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('six_level_backtests', function (Blueprint $table) {
            // Opponent previous-day HIGH and CLOSE
            // When CE breaks lowest low -> check PE
            $table->boolean('ce_opponent_prev_high_broken')->default(false);
            $table->dateTime('ce_opponent_prev_high_break_time')->nullable();
            $table->decimal('ce_opponent_prev_high_break_price', 12, 2)->nullable();

            $table->boolean('ce_opponent_prev_close_crossed')->default(false);
            $table->dateTime('ce_opponent_prev_close_cross_time')->nullable();
            $table->decimal('ce_opponent_prev_close_cross_price', 12, 2)->nullable();

            // When PE breaks lowest low -> check CE
            $table->boolean('pe_opponent_prev_high_broken')->default(false);
            $table->dateTime('pe_opponent_prev_high_break_time')->nullable();
            $table->decimal('pe_opponent_prev_high_break_price', 12, 2)->nullable();

            $table->boolean('pe_opponent_prev_close_crossed')->default(false);
            $table->dateTime('pe_opponent_prev_close_cross_time')->nullable();
            $table->decimal('pe_opponent_prev_close_cross_price', 12, 2)->nullable();

            // Post-break behaviour for CE
            $table->boolean('ce_low_retested')->default(false);
            $table->dateTime('ce_low_retest_time')->nullable();
            $table->decimal('ce_low_retest_price', 12, 2)->nullable();
            $table->decimal('ce_retest_distance_from_low', 12, 2)->nullable();

            $table->decimal('ce_max_high_from_low', 12, 2)->nullable();
            $table->dateTime('ce_max_high_from_low_time')->nullable();

            // Post-break behaviour for PE
            $table->boolean('pe_low_retested')->default(false);
            $table->dateTime('pe_low_retest_time')->nullable();
            $table->decimal('pe_low_retest_price', 12, 2)->nullable();
            $table->decimal('pe_retest_distance_from_low', 12, 2)->nullable();

            $table->decimal('pe_max_high_from_low', 12, 2)->nullable();
            $table->dateTime('pe_max_high_from_low_time')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('six_level_backtests', function (Blueprint $table) {
            $table->dropColumn([
                'ce_opponent_prev_high_broken',
                'ce_opponent_prev_high_break_time',
                'ce_opponent_prev_high_break_price',
                'ce_opponent_prev_close_crossed',
                'ce_opponent_prev_close_cross_time',
                'ce_opponent_prev_close_cross_price',
                'pe_opponent_prev_high_broken',
                'pe_opponent_prev_high_break_time',
                'pe_opponent_prev_high_break_price',
                'pe_opponent_prev_close_crossed',
                'pe_opponent_prev_close_cross_time',
                'pe_opponent_prev_close_cross_price',
                'ce_low_retested',
                'ce_low_retest_time',
                'ce_low_retest_price',
                'ce_retest_distance_from_low',
                'ce_max_high_from_low',
                'ce_max_high_from_low_time',
                'pe_low_retested',
                'pe_low_retest_time',
                'pe_low_retest_price',
                'pe_retest_distance_from_low',
                'pe_max_high_from_low',
                'pe_max_high_from_low_time',
            ]);
        });
    }
};
