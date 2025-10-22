<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('three_min_quotes', function (Blueprint $table) {
            $table->enum('build_up', [
                'long_build',
                'short_build',
                'short_cover',
                'long_unwind'
            ])->nullable()->after('option_type');
        });
    }

    public function down(): void
    {
        Schema::table('three_min_quotes', function (Blueprint $table) {
            $table->dropColumn('build_up');
        });
    }
};
