<?php
// database/migrations/xxxx_xx_xx_create_daily_trend_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('daily_trend', function (Blueprint $table) {
            $table->id();
            $table->date('quote_date')->index();
            $table->string('symbol_name', 20);
            $table->decimal('index_high', 12, 2);
            $table->decimal('index_low', 12, 2);
            $table->integer('strike');
            $table->decimal('ce_high', 12, 2);
            $table->decimal('ce_low', 12, 2);
            $table->decimal('ce_close', 12, 2);
            $table->decimal('pe_high', 12, 2);
            $table->decimal('pe_low', 12, 2);
            $table->decimal('pe_close', 12, 2);
            $table->decimal('min_r', 12, 2);
            $table->decimal('min_s', 12, 2);
            $table->decimal('max_r', 12, 2);
            $table->decimal('max_s', 12, 2);
            $table->date('expiry_date');

            $table->decimal('earth_value', 12, 2);
            $table->decimal('earth_high', 12, 2)->nullable();
            $table->decimal('earth_low', 12, 2)->nullable();
            $table->string('market_type')->default('REGULAR');
            $table->json('six_levels_broken')->nullable();
            $table->decimal('current_day_index_open', 12, 2)->nullable();
            $table->timestamp('market_open_time')->nullable();
            $table->timestamps();

            $table->unique(['quote_date', 'symbol_name']);
            $table->index(['quote_date', 'symbol_name']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('daily_trend');
    }
};
