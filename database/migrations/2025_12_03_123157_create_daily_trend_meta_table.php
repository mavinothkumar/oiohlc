<?php
// database/migrations/xxxx_xx_xx_create_enhanced_daily_trend_meta_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('daily_trend_meta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_trend_id')->constrained('daily_trend')->onDelete('cascade');
            $table->date('tracked_date'); // Current trading day
            $table->timestamp('recorded_at'); // When recorded

            // Live LTPs
            $table->decimal('ce_ltp', 12, 2)->nullable();
            $table->decimal('pe_ltp', 12, 2)->nullable();
            $table->decimal('index_ltp', 12, 2)->nullable();

            // HLC Strategy Scenarios (from flowchart)
            $table->string('market_scenario')->nullable(); // "CSP-PSPB", "CSPB-PSP", "BOTHPB", "INDECISION"
            $table->json('triggers')->nullable(); // ["spot_break_resistance", "call_seller_panic", "ce_above_pdh"]
            $table->string('trade_signal')->nullable(); // "BUY_CE", "BUY_PE", "BUY_OPPOSITE", "LOW_CHANCE", "SIDEWAYS"

            // Key Flowchart Levels & Checks (PDH/PDL/PDC equivalents)
            $table->decimal('pdh_equiv', 12, 2)->nullable(); // Previous Day High equivalent (max_r?)
            $table->decimal('pdl_equiv', 12, 2)->nullable(); // Previous Day Low equivalent (min_s?)
            $table->decimal('pdc_equiv', 12, 2)->nullable(); // Previous Day Close equivalent (strike?)

            // 6 Levels Status (MinRes, MaxRes, MinSup, MaxSup + Earth)
            $table->json('six_levels_status')->nullable(); // ["min_r"=>{"crossed"=>true,"first_at"=>"2025-12-03 09:30:00"}, ...]
            $table->json('resistance_levels')->nullable(); // ["min_res", "max_res"] - crossed status
            $table->json('support_levels')->nullable();    // ["min_sup", "max_sup"] - crossed status

            // Side Analysis (Call Seller Panic / Put Seller Profit etc.)
            $table->json('call_seller_status')->nullable(); // ["panic"=>true, "profit_booking"=>false]
            $table->json('put_seller_status')->nullable();

            // Spot vs PDC checks
            $table->boolean('spot_above_pdc')->nullable();
            $table->boolean('spot_below_pdc')->nullable();
            $table->boolean('spot_break_resistance')->nullable();
            $table->boolean('spot_break_support')->nullable();

            // CE/PE specific checks
            $table->boolean('ce_above_pdh')->nullable();
            $table->boolean('pe_above_pdh')->nullable();
            $table->boolean('ce_below_6levels')->nullable();
            $table->boolean('pe_below_6levels')->nullable();

            // Dominant Side & Reversal
            $table->string('dominant_side')->nullable(); // "CALL", "PUT", "NEUTRAL"
            $table->boolean('big_reversal_pattern')->nullable();
            $table->string('good_zone')->nullable(); // "CE_ZONE", "PE_ZONE"

            // Live Status Flags
            $table->string('ce_type')->nullable(); // "Profit", "Panic", "Side"
            $table->string('pe_type')->nullable();
            $table->string('broken_status')->nullable(); // "Up", "Down"

            // Timestamps for key events
            $table->timestamp('first_trigger_at')->nullable();
            $table->timestamp('first_broken_at')->nullable();

            // Backtesting fields
            $table->integer('sequence_id')->default(0); // Order within day for sequencing
            $table->timestamps();

            $table->index(['daily_trend_id', 'tracked_date']);
            $table->index(['daily_trend_id', 'recorded_at']);
            $table->index(['market_scenario', 'trade_signal']);
            $table->index('sequence_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('daily_trend_meta');
    }
};
