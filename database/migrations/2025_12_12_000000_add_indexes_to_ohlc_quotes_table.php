<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ohlc_quotes', function (Blueprint $table) {
            // Keep this if not already indexed
            $table->index('created_at', 'ohlc_quotes_created_at_index');

            // REMOVE this line if index already exists
            // $table->index('instrument_type', 'ohlc_quotes_instrument_type_index');

            $table->index(
                ['trading_symbol', 'strike_price', 'instrument_type', 'expiry_date'],
                'ohlc_quotes_symbol_strike_type_expiry_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('ohlc_quotes', function (Blueprint $table) {
            $table->dropIndex('ohlc_quotes_created_at_index');
            // Drop only if you actually created it in up()
            // $table->dropIndex('ohlc_quotes_instrument_type_index');
            $table->dropIndex('ohlc_quotes_symbol_strike_type_expiry_index');
        });
    }
};
