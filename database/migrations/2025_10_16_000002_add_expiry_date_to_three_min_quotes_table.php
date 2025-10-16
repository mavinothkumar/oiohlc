<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddExpiryDateToThreeMinQuotesTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('three_min_quotes', function (Blueprint $table) {
            $table->date('expiry_date')->nullable()->after('expiry'); // Add after the existing expiry column
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('three_min_quotes', function (Blueprint $table) {
            $table->dropColumn('expiry_date');
        });
    }
}
