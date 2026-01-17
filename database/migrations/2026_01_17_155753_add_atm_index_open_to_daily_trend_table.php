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
        Schema::table('daily_trend', function (Blueprint $table) {
            // Strike price values
            $table->decimal('atm_index_open', 12, 2)->nullable()->after('atm_s_3');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('daily_trend', function (Blueprint $table) {
            $table->dropColumn([
                'atm_index_open',
            ]);
        });
    }
};
