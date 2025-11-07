<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddExpiryDateToDailyOHLCQuoteTable extends Migration
{
    public function up()
    {
        Schema::table('daily_ohlc_quotes', function (Blueprint $table) {
            $table->date('expiry_date')->nullable()->after('expiry')->index();
        });
    }

    public function down()
    {
        Schema::table('daily_ohlc_quotes', function (Blueprint $table) {
            $table->dropColumn('expiry_date');
        });
    }
}
