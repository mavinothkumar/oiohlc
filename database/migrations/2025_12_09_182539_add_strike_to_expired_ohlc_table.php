<?php

// database/migrations/xxxx_xx_xx_add_strike_to_expired_ohlc_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expired_ohlc', function (Blueprint $table) {
            $table->integer('strike')->nullable()->after('instrument_type');
            $table->index(['underlying_symbol', 'expiry', 'strike', 'instrument_type'], 'ohlc_usym_exp_strike_type_idx');
            $table->index(['underlying_symbol', 'instrument_type', 'interval', 'timestamp'], 'ohlc_index_future_idx');
            $table->index(['expiry', 'open_interest', 'strike', 'interval', 'timestamp'], 'ohlc_option_idx');
        });
    }

    public function down(): void
    {
        Schema::table('expired_ohlc', function (Blueprint $table) {
            $table->dropIndex('ohlc_option_idx');
            $table->dropIndex('ohlc_usym_exp_strike_type_idx');
            $table->dropIndex('ohlc_index_future_idx');
            $table->dropColumn('strike');
        });
    }
};

