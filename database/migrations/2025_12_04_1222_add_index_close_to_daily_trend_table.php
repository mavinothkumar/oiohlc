<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_trend', function (Blueprint $table) {
            // Adjust precision/scale if your index prices differ
            $table->decimal('index_close', 12, 2)->nullable()->after('index_low');
        });
    }

    public function down(): void
    {
        Schema::table('daily_trend', function (Blueprint $table) {
            $table->dropColumn('index_close');
        });
    }
};
