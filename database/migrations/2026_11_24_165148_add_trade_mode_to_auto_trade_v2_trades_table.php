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
        Schema::table('auto_trade_v2_trades', function (Blueprint $table) {
            $table->string('trade_mode', 20)->default('SELLING')->after('strategy_mode');
            $table->index('trade_mode');
            $table->index('action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('auto_trade_v2_trades', function (Blueprint $table) {
            $table->dropIndex(['trade_mode']);
            $table->dropIndex(['action']);
            $table->dropColumn('trade_mode');
        });
    }
};
