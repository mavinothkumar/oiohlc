<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('daily_trend', function (Blueprint $table) {
            // Strike price values
            $table->decimal('atm_r', 12, 2)->nullable()->after('atm_pe_low');
            $table->decimal('atm_s', 12, 2)->nullable()->after('atm_r');
            $table->enum('open_type', [
                'Gap Up', 'Gap Down', 'Positive Open', 'Negative Open',
            ])->nullable()->index()->after('atm_s');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('daily_trend', function (Blueprint $table) {
            $table->dropColumn([
                'atm_pe_low',
                'atm_r',
                'open_type',
            ]);
        });
    }
};
