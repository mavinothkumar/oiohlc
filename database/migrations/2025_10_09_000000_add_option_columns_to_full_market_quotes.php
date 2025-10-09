<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::table('full_market_quotes', function (Blueprint $table) {
            $table->string('expiry')->nullable()->after('symbol')->index();
            $table->string('strike')->nullable()->after('expiry')->index();
            $table->string('option_type')->nullable()->after('strike')->index();
        });
    }

    public function down(): void
    {
        Schema::table('full_market_quotes', function (Blueprint $table) {
            $table->dropColumn('expiry');
            $table->dropColumn('strike');
            $table->dropColumn('option_type');
        });
    }
};

