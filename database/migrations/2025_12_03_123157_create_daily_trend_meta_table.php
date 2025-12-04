<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_trend_meta', function (Blueprint $table) {
            $table->id();

            // Link to static daily_trend row (per symbol + quote_date)
            $table->foreignId('daily_trend_id')
                  ->constrained('daily_trend')
                  ->onDelete('cascade');

            // Trading day for this meta snapshot
            $table->date('tracked_date')->index();

            // When this evaluation was done (e.g. every 5 mins)
            $table->timestamp('recorded_at')->index();

            // 5‑min LTPs
            $table->decimal('ce_ltp', 12, 2)->nullable();
            $table->decimal('pe_ltp', 12, 2)->nullable();
            $table->decimal('index_ltp', 12, 2)->nullable();

            // HLC scenario group and signal
            // 1) CSP-PSPB, 2) CSPB-PSP, 3) BOTHPB, 4) INDECISION
            $table->string('market_scenario', 32)->nullable();   // e.g. "CSP-PSPB"
            $table->string('trade_signal', 32)->nullable();      // e.g. "BUY_CE", "BUY_PE", "SIDEWAYS_NO_TRADE"

            // Stored CE/PE state at this 5‑min candle (can differ slightly from static ce_type/pe_type)
            $table->string('ce_type', 32)->nullable();           // "Profit", "Panic", "Side", ...
            $table->string('pe_type', 32)->nullable();

            // Flags / triggers used in decision (compact JSON)
            // Example keys: spot_break_min_res, spot_break_min_sup, spot_near_pdc, ce_above_pdh, pe_above_pdh, cs_panic, ps_pb, cs_pb, ps_panic...
            $table->json('triggers')->nullable();

            // Levels crossed / touched during this 5‑min bar
            // Example structure: {"min_r": {"crossed": true}, "earth_high": {"crossed": false}, ...}
            $table->json('levels_crossed')->nullable();

            // First time this symbol broke out of CE+PE joint range Up/Down, if any
            $table->string('broken_status', 16)->nullable();     // "Up", "Down", null
            $table->timestamp('first_broken_at')->nullable();

            // Dominant side snapshot and zone tags (optional but useful for backtest)
            $table->string('dominant_side', 16)->nullable();     // "CALL", "PUT", "BOTH_PB", "NONE"
            $table->string('good_zone', 16)->nullable();         // "CE_ZONE", "PE_ZONE", null

            // Order within the day (every 5 mins)
            $table->unsignedInteger('sequence_id')->default(0)->index();

            $table->timestamps();

            // Helpful compound indexes
            $table->index(['daily_trend_id', 'tracked_date']);
            $table->index(['daily_trend_id', 'recorded_at']);
            $table->index(['market_scenario', 'trade_signal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_trend_meta');
    }
};
