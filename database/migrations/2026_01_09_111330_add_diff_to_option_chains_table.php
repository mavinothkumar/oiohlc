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
            $table->bigInteger('diff_oi')->index()->nullable()->after('oi');
            $table->bigInteger('diff_volume')->index()->nullable()->after('volume');
            $table->decimal('diff_ltp', 10, 2)->nullable()->after('ltp');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('option_chains', function (Blueprint $table) {
            $table->dropIndex(['diff_oi', 'diff_volume', 'diff_ltp']);
            $table->dropColumn(['diff_oi', 'diff_volume', 'diff_ltp']);
        });
    }
};
