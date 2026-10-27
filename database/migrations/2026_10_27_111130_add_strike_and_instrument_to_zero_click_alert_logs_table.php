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
        Schema::table('zero_click_alert_logs', function (Blueprint $table) {
            $table->string('signal', 10)->nullable()->after('candle_time')->comment('Market direction: BUY (Bull) or SELL (Bear)');
            $table->string('instrument', 10)->nullable()->after('symbol')->comment('CE or PE');
            $table->unsignedInteger('strike')->nullable()->after('instrument')->comment('100s ITM strike');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('zero_click_alert_logs', function (Blueprint $table) {
            $table->dropColumn(['signal', 'instrument', 'strike']);
        });
    }
};
