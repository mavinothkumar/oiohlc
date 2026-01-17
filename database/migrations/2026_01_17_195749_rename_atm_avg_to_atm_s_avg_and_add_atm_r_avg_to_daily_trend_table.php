<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_trend', function (Blueprint $table) {
            // Rename atm_avg to atm_s_avg
            $table->renameColumn('atm_avg', 'atm_s_avg');

            // Add new atm_r_avg column
            $table->decimal('atm_r_avg', 12, 2)->nullable()->after('atm_s_avg');
        });
    }

    public function down(): void
    {
        Schema::table('daily_trend', function (Blueprint $table) {
            // Reverse: drop atm_r_avg then rename back
            $table->dropColumn('atm_r_avg');
            $table->renameColumn('atm_s_avg', 'atm_avg');
        });
    }
};
