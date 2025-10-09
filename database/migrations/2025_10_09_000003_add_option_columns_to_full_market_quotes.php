<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('five_min_quotes', function (Blueprint $table) {
            $table->string('symbol_name')->nullable()->index()->after('symbol');
            $table->string('expiry')->nullable()->index()->after('symbol_name');
            $table->string('strike')->nullable()->index()->after('expiry');
            $table->string('option_type')->nullable()->index()->after('strike');
        });
    }

    public function down(): void
    {
        Schema::table('five_min_quotes', function (Blueprint $table) {
            $table->dropColumn('symbol_name');
            $table->dropColumn('expiry');
            $table->dropColumn('strike');
            $table->dropColumn('option_type');
        });
    }
};


