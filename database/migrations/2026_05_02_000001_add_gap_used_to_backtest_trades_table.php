<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('backtest_trades', function (Blueprint $table) {
            $table->decimal('gap_used', 12, 2)->nullable()->after('strike_offset')
                  ->comment('Actual |daily_trend.open_value| on trading_date used for gap filter');
        });
    }

    public function down(): void
    {
        Schema::table('daily_trend', function (Blueprint $table) {
            $table->dropColumn('gap_used');
        });
    }
};
