<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('backtest_trades', function (Blueprint $table) {
            $table->decimal('previous_day_range', 12, 2)->nullable()->after('strike_offset');
            $table->decimal('gap_pct_prev_range', 12, 4)->nullable()->after('strike_offset');
        });
    }

    public function down(): void
    {
        Schema::table('daily_trend', function (Blueprint $table) {
            $table->dropColumn('previous_day_range');
            $table->dropColumn('gap_pct_prev_range');
        });
    }
};
