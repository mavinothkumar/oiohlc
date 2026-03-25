<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bias_snapshots', function (Blueprint $table) {
            $table->id();

            // ── Identity ─────────────────────────────────────────────
            $table->string('trading_symbol', 20)->default('NIFTY');
            $table->date('date')->index();
            $table->string('expiry_date', 20)->nullable();
            $table->decimal('spot_price', 10, 2)->nullable();
            $table->decimal('atm_strike', 10, 2)->nullable();
            $table->tinyInteger('strikes_range')->default(2);  // e.g. ±2

            // ── Bias Score & Signal ───────────────────────────────────
            $table->integer('bias_score')->default(0);         // -100 to +100
            $table->string('bias', 10)->default('Sideways');   // Bullish / Bearish / Sideways
            $table->string('bias_strength', 10)->default('Weak'); // Strong / Moderate / Weak

            // ── CE Build-Up OI ────────────────────────────────────────
            $table->unsignedBigInteger('ce_long_build_oi')->default(0);
            $table->unsignedBigInteger('ce_short_build_oi')->default(0);
            $table->unsignedBigInteger('ce_short_cover_oi')->default(0);
            $table->unsignedBigInteger('ce_long_unwind_oi')->default(0);

            // ── CE Build-Up Volume ────────────────────────────────────
            $table->unsignedBigInteger('ce_long_build_vol')->default(0);
            $table->unsignedBigInteger('ce_short_build_vol')->default(0);
            $table->unsignedBigInteger('ce_short_cover_vol')->default(0);
            $table->unsignedBigInteger('ce_long_unwind_vol')->default(0);

            // ── PE Build-Up OI ────────────────────────────────────────
            $table->unsignedBigInteger('pe_long_build_oi')->default(0);
            $table->unsignedBigInteger('pe_short_build_oi')->default(0);
            $table->unsignedBigInteger('pe_short_cover_oi')->default(0);
            $table->unsignedBigInteger('pe_long_unwind_oi')->default(0);

            // ── PE Build-Up Volume ────────────────────────────────────
            $table->unsignedBigInteger('pe_long_build_vol')->default(0);
            $table->unsignedBigInteger('pe_short_build_vol')->default(0);
            $table->unsignedBigInteger('pe_short_cover_vol')->default(0);
            $table->unsignedBigInteger('pe_long_unwind_vol')->default(0);

            // ── Aggregated Weighted OI (for prediction strategies) ────
            $table->unsignedBigInteger('bullish_oi')->default(0);  // Long Build×2 + Short Cover×1
            $table->unsignedBigInteger('bearish_oi')->default(0);  // Short Build×2 + Long Unwind×1
            $table->unsignedBigInteger('total_volume')->default(0); // All volume summed

            $table->timestamp('captured_at')->nullable()->index();
            $table->timestamps();

            // ── Indexes ───────────────────────────────────────────────
            $table->index(['trading_symbol', 'date']);
            $table->index(['date', 'captured_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bias_snapshots');
    }
};
