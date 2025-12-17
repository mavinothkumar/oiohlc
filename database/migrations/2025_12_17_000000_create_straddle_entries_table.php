<?php
// database/migrations/2025_12_17_000000_create_straddle_entries_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('straddle_entries', function (Blueprint $table) {
            $table->id();

            $table->string('symbol')->default('NIFTY');
            $table->date('expiry_date');

            $table->dateTime('entry_time');
            $table->decimal('index_at_entry', 10, 2)->nullable();

            $table->integer('atm_strike');

            $table->string('ce_symbol')->nullable();
            $table->string('pe_symbol')->nullable();

            $table->integer('ce_strike');
            $table->integer('pe_strike');

            $table->decimal('ce_entry_price', 10, 2);
            $table->decimal('pe_entry_price', 10, 2);

            $table->date('trade_date');

            $table->timestamps();

            $table->index(['symbol', 'trade_date']);
            $table->index(['symbol', 'expiry_date']);
            $table->index(['symbol', 'expiry_date', 'atm_strike']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('straddle_entries');
    }
};
