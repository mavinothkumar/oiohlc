<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_trend', function (Blueprint $table) {
            $table->decimal('index_day_range',12,2)->nullable()->after('index_close');
        });
    }

    public function down(): void
    {
        Schema::table('daily_trend', function (Blueprint $table) {
            $table->dropColumn('index_day_range');
        });
    }
};
