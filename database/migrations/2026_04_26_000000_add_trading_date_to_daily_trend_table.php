<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_trend', function (Blueprint $table) {
            $table->date('trading_date')->nullable()->after('quote_date')->index();
        });
    }

    public function down(): void
    {
        Schema::table('daily_trend', function (Blueprint $table) {
            $table->dropIndex(['trading_date']);
            $table->dropColumn('trading_date');
        });
    }
};
