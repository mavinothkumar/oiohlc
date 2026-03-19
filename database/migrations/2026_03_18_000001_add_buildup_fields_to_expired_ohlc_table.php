<?php
// database/migrations/2026_03_18_000001_add_buildup_fields_to_expired_ohlc_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expired_ohlc', function (Blueprint $table) {
            $table->enum('build_up', [
                'Long Build',
                'Short Build',
                'Long Unwind',
                'Short Cover',
                'Neutral',
            ])->nullable()->after('open_interest');

            $table->bigInteger('diff_oi')->nullable()->after('build_up');
            $table->bigInteger('diff_volume')->nullable()->after('diff_oi');
            $table->decimal('diff_ltp', 10, 2)->nullable()->after('diff_volume');
        });

        // Composite index to speed up the command queries
        Schema::table('expired_ohlc', function (Blueprint $table) {
            $table->index(['instrument_key', 'interval', 'timestamp'], 'idx_instrument_interval_ts');
        });
    }

    public function down(): void
    {
        Schema::table('expired_ohlc', function (Blueprint $table) {
            $table->dropIndex('idx_instrument_interval_ts');
            $table->dropColumn(['build_up', 'diff_oi', 'diff_volume', 'diff_ltp']);
        });
    }
};
