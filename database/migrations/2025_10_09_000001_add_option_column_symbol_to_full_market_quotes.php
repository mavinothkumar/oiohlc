<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::table('full_market_quotes', function (Blueprint $table) {
            $table->string('symbol_name')->nullable()->after('symbol')->index();

        });
    }

    public function down(): void
    {
        Schema::table('full_market_quotes', function (Blueprint $table) {
            $table->dropColumn('symbol_name');
        });
    }
};

