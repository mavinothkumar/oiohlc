<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddExpiryDateToFullMarketQuotesTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('full_market_quotes', function (Blueprint $table) {
            $table->date('expiry_date')->nullable()->after('expiry'); // Add after the existing expiry column
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('full_market_quotes', function (Blueprint $table) {
            $table->dropColumn('expiry_date');
        });
    }
}
