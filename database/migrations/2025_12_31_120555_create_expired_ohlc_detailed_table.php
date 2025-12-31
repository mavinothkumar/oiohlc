<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('expired_ohlc_detailed', function (Blueprint $table) {
            $table->bigIncrements('id'); // BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY [web:2]

            $table->string('underlying_symbol', 255); // VARCHAR(255) NOT NULL [web:2]
            $table->string('exchange', 255);          // VARCHAR(255) NOT NULL [web:2]
            $table->date('expiry')->nullable();       // DATE NULL [web:2]

            $table->string('instrument_key', 255);    // VARCHAR(255) NOT NULL [web:2]
            $table->string('instrument_type', 255);   // VARCHAR(255) NOT NULL [web:2]
            $table->integer('strike')->nullable();    // INT NULL [web:2]

            $table->string('interval', 255);          // VARCHAR(255) NOT NULL [web:2]

            $table->decimal('open', 12, 2);           // DECIMAL(12,2) NOT NULL [web:7]
            $table->decimal('high', 12, 2);           // DECIMAL(12,2) NOT NULL [web:7]
            $table->decimal('low', 12, 2);            // DECIMAL(12,2) NOT NULL [web:7]
            $table->decimal('close', 12, 2);          // DECIMAL(12,2) NOT NULL [web:7]

            $table->bigInteger('volume')->nullable();        // BIGINT NULL [web:2]
            $table->bigInteger('open_interest')->nullable(); // BIGINT NULL [web:2]

            $table->dateTime('timestamp'); // DATETIME NOT NULL [web:2]

            // created_at, updated_at as nullable TIMESTAMP (matches your DDL closely)
            $table->timestamp('created_at')->nullable(); // TIMESTAMP NULL [web:8]
            $table->timestamp('updated_at')->nullable(); // TIMESTAMP NULL [web:8]

            // Indexes
            $table->index(
                ['instrument_key', 'interval', 'timestamp'],
                'expired_ohlc_instrument_key_interval_timestamp_index'
            ); // [web:2]

            $table->index(
                ['underlying_symbol', 'expiry', 'instrument_type'],
                'expired_ohlc_underlying_symbol_expiry_instrument_type_index'
            ); // [web:2]

            $table->index(
                ['underlying_symbol', 'expiry', 'strike', 'instrument_type'],
                'ohlc_usym_exp_strike_type_idx'
            ); // [web:2]

            $table->index(
                ['underlying_symbol', 'expiry', 'instrument_type', 'interval', 'timestamp'],
                'idx_ohlc_usym_exp_type_int_ts'
            ); // [web:2]
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expired_ohlc_detailed'); // [web:2]
    }
};
