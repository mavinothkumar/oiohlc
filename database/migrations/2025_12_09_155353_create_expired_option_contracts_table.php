<?php

// database/migrations/xxxx_xx_xx_create_expired_option_contracts_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expired_option_contracts', function (Blueprint $table) {
            $table->id();

            // Core identifiers
            $table->string('name');                    // NIFTY
            $table->string('segment');                 // NSE_FO
            $table->string('exchange');                // NSE
            $table->date('expiry');                    // 2025-04-17
            $table->string('instrument_key')->unique();// NSE_FO|47983|17-04-2025
            $table->string('exchange_token');          // 47983
            $table->string('trading_symbol');          // NIFTY 20400 PE 17 APR 25

            // Contract specs
            $table->unsignedInteger('tick_size');      // 5
            $table->unsignedInteger('lot_size');       // 75
            $table->string('instrument_type');         // PE/CE
            $table->unsignedInteger('freeze_quantity')->nullable();
            $table->boolean('weekly')->default(false);

            // Underlying info
            $table->string('underlying_key');          // NSE_INDEX|Nifty 50
            $table->string('underlying_type');         // INDEX
            $table->string('underlying_symbol');       // NIFTY

            // Pricing
            $table->unsignedInteger('strike_price');   // 20400
            $table->unsignedInteger('minimum_lot')->nullable();

            // Link to expiry master (optional but useful)
            $table->foreignId('expired_expiry_id')
                  ->nullable()
                  ->constrained('expired_expiries')
                  ->nullOnDelete();

            $table->timestamps();

            $table->index(['underlying_key', 'expiry']);
            $table->index(['underlying_symbol', 'expiry']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expired_option_contracts');
    }
};

