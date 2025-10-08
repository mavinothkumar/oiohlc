<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('instruments', function (Blueprint $table) {
            $table->id();

            // Common fields

            $table->string('name');

            $table->string('instrument_type');
            $table->string('exchange_token');

            $table->timestamps();

            // Nullable/Type-specific fields
            $table->string('isin')->nullable();
            $table->string('short_name')->nullable();
            $table->string('security_type')->nullable();

            $table->integer('lot_size')->nullable();
            $table->float('freeze_quantity')->nullable();
            $table->float('tick_size')->nullable();

            $table->integer('minimum_lot')->nullable();
            $table->string('underlying_symbol')->nullable();
            $table->string('underlying_key')->nullable();
            $table->string('underlying_type')->nullable();

            // Expiry in timestamp format, allow both date and int
            $table->boolean('weekly')->nullable();
            $table->float('strike_price')->nullable();
            $table->string('option_type')->nullable();

            // For index
            // (fields above cover everything needed for index – no extra fields required)

            // For suspended, qty_multiplier
            $table->float('qty_multiplier')->nullable();

            // For MTF
            $table->boolean('mtf_enabled')->nullable();
            $table->float('mtf_bracket')->nullable();

            // For MIS
            $table->float('intraday_margin')->nullable();
            $table->integer('intraday_leverage')->nullable();

            $table->string('instrument_key')->unique();
            $table->string('exchange')->index();
            $table->string('segment')->index();
            $table->string('trading_symbol')->index();
            $table->bigInteger('expiry')->nullable()->index();

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instruments');
    }
};

