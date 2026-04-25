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
        Schema::table('backtest_trades', function (Blueprint $table) {
            $table->integer('ce_strike')->nullable()->after('strike');
            $table->integer('pe_strike')->nullable()->after('ce_strike');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('backtest_trades', function (Blueprint $table) {
            //
        });
    }
};
