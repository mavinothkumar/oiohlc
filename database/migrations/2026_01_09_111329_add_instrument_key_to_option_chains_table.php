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
        Schema::table('option_chains', function (Blueprint $table) {
            $table->string('instrument_key')->index()->nullable()->after('id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('option_chains', function (Blueprint $table) {
            $table->dropIndex(['instrument_key']);
            $table->dropColumn('instrument_key');
        });
    }
};
