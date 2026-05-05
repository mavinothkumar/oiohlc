<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nse_atm_day_data', function (Blueprint $table) {
            $table->id();
            $table->string('underlying_symbol', 50)->index();
            $table->date('previous_date')->index();
            $table->date('current_date')->index();
            $table->unsignedInteger('atm_strike')->nullable();
            $table->decimal('mid_point', 12, 2)->nullable()->comment('Minimum absolute difference between previous day CE and PE close of same strike');
            $table->date('current_expiry_date')->nullable()->index();
            $table->date('next_expiry_date')->nullable()->index();
            $table->decimal('current_day_index_open', 12, 2)->nullable();
            $table->decimal('previous_day_index_open', 12, 2)->nullable();
            $table->decimal('previous_day_index_high', 12, 2)->nullable();
            $table->decimal('previous_day_index_low', 12, 2)->nullable();
            $table->decimal('previous_day_index_close', 12, 2)->nullable();
            $table->decimal('previous_day_ce_close', 12, 2)->nullable();
            $table->decimal('previous_day_pe_close', 12, 2)->nullable();
            $table->decimal('previous_day_ce_high', 12, 2)->nullable();
            $table->decimal('previous_day_pe_high', 12, 2)->nullable();
            $table->decimal('previous_day_ce_low', 12, 2)->nullable();
            $table->decimal('previous_day_pe_low', 12, 2)->nullable();
            $table->boolean('is_expiry_day_rollover')->default(false)->index();
            $table->timestamps();

            $table->unique(['underlying_symbol', 'current_date'], 'nse_atm_day_data_symbol_current_date_unique');
            $table->index(['underlying_symbol', 'previous_date'], 'nse_atm_day_data_symbol_previous_date_index');
            $table->index(['underlying_symbol', 'current_expiry_date'], 'nse_atm_day_data_symbol_current_expiry_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nse_atm_day_data');
    }
};
