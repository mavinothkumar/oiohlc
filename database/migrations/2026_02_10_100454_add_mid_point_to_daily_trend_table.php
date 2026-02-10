<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_trend', function (Blueprint $table) {
            $table->decimal('mid_point', 12, 2)->nullable()->after('pe_close');
        });
    }

    public function down(): void
    {
        Schema::table('daily_trend', function (Blueprint $table) {
            $table->dropColumn('mid_point');
        });
    }
};
