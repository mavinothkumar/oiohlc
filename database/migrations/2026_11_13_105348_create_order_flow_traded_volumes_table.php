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
        Schema::create('order_flow_traded_volumes', function (Blueprint $table) {
            $table->id();
            $table->date('trade_date')->index();
            $table->string('trade_time', 12)->index()->comment('e.g. 09:16:30');
            $table->string('symbol', 50)->default('NIFTY')->index();
            $table->string('instrument', 100)->nullable();
            $table->enum('side', ['ASK', 'BID'])->index()->comment('ASK: Aggressive Buyer lifting offer; BID: Aggressive Seller hitting bid');
            $table->decimal('price', 10, 2);
            $table->unsignedBigInteger('quantity');
            $table->string('source', 30)->default('LIVE_TICK')->comment('LIVE_TICK, ALERT_BLOCK, HISTORICAL_BACKFILL, SIMULATOR');
            $table->json('meta')->nullable();
            $table->timestamps();

            // Unique constraint to avoid duplicate prints at same timestamp, price and side
            $table->unique(['trade_date', 'symbol', 'side', 'trade_time', 'price'], 'uniq_of_trade_print');

            // Fast indexing for Top Traded queries & backtesting
            $table->index(['trade_date', 'symbol', 'side', 'quantity'], 'idx_of_top_qty');
            $table->index(['trade_date', 'symbol', 'trade_time'], 'idx_of_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_flow_traded_volumes');
    }
};
