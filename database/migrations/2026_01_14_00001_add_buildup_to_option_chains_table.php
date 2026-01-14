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
            $table->enum('build_up', [
                'Long Build','Short Build','Short Cover','Long Unwind'
            ])->nullable()->index()->after('pcr');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('option_chains', function (Blueprint $table) {
            $table->dropIndex('build_up');
        });
    }
};
