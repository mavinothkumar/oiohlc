<?php
// database/migrations/2025_12_17_000001_create_straddle_entry_slots_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('straddle_entry_slots', function (Blueprint $table) {
            $table->id();

            $table->string('symbol')->default('NIFTY');
            $table->date('expiry_date');

            // static label: 10, 11, 12, 13, 14, 15, 15_30
            $table->string('hour_slot', 10);

            // actual datetime of the 5-min candle used
            $table->dateTime('slot_time');

            $table->integer('atm_strike');

            $table->integer('ce_strike');
            $table->integer('pe_strike');

            $table->decimal('ce_entry_price', 10, 2);
            $table->decimal('pe_entry_price', 10, 2);

            $table->decimal('ce_close_price', 10, 2);
            $table->decimal('pe_close_price', 10, 2);

            $table->decimal('ce_pnl', 10, 2);
            $table->decimal('pe_pnl', 10, 2);
            $table->decimal('total_pnl', 10, 2);



            $table->timestamps();
            $table->date('trade_date');
            $table->index(['symbol', 'trade_date', 'hour_slot']);

            $table->index(['symbol', 'expiry_date']);
            $table->index(['symbol', 'expiry_date', 'hour_slot']);
            $table->index(['symbol', 'expiry_date', 'atm_strike', 'hour_slot', 'slot_time'], 'slot_unique_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('straddle_entry_slots');
    }
};
