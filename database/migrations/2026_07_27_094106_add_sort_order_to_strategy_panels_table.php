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
        Schema::table('strategy_panels', function (Blueprint $table) {
            // Adds the sort_order column after the name column with a default of 0
            $table->integer('sort_order')->default(0)->after('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('strategy_panels', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};
