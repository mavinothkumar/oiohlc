<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('option_chains_3m', function (Blueprint $table) {
            $table->id();
            $table->string('underlying_key', 50)->index();
            $table->string('trading_symbol', 20)->index();
            $table->date('expiry')->index();
            $table->decimal('strike_price', 10, 2)->index();
            $table->enum('option_type', ['CE', 'PE']);
            $table->decimal('ltp', 10, 2)->nullable();
            $table->bigInteger('volume')->nullable();
            $table->bigInteger('oi')->nullable();
            $table->enum('build_up', [
                'Long Build',
                'Short Build',
                'Short Cover',
                'Long Unwind'
            ])->nullable()->index();
            $table->decimal('close_price', 10, 2)->nullable();
            $table->decimal('bid_price', 10, 2)->nullable();
            $table->bigInteger('bid_qty')->nullable();
            $table->decimal('ask_price', 10, 2)->nullable();
            $table->bigInteger('ask_qty')->nullable();
            $table->bigInteger('prev_oi')->nullable();
            $table->decimal('vega', 10, 4)->nullable();
            $table->decimal('theta', 10, 4)->nullable();
            $table->decimal('gamma', 10, 4)->nullable();
            $table->decimal('delta', 10, 4)->nullable();
            $table->decimal('iv', 10, 2)->nullable();
            $table->decimal('pop', 10, 2)->nullable();
            $table->decimal('underlying_spot_price', 10, 2)->nullable();
            $table->decimal('pcr', 10, 4)->nullable();

            // Difference columns with previous 3-minute snapshot
            $table->decimal('diff_underlying_spot_price', 10, 2)->nullable();
            $table->decimal('diff_ltp', 10, 2)->nullable();
            $table->bigInteger('diff_volume')->nullable();
            $table->bigInteger('diff_oi')->nullable();

            // Time rounded to 3-minute interval for fast filtering
            $table->timestamp('captured_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('option_chains_3m');
    }
};
