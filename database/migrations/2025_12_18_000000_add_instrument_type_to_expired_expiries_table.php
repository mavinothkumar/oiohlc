<?php
// database/migrations/2025_12_18_000000_add_instrument_type_to_expired_expiries_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('expired_expiries', function (Blueprint $table) {
            $table->enum('instrument_type', ['FUT', 'OPT'])->default('OPT')->after('underlying_symbol');
        });
    }

    public function down()
    {
        Schema::table('expired_expiries', function (Blueprint $table) {
            $table->dropColumn('instrument_type');
        });
    }
};
